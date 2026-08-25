<?php
/**
 * Order integration:
 *  - Store pack contents as order item meta (shown in admin and emails)
 *  - Reduce bundle stock on payment (idempotent)
 *  - Restore stock when an order is cancelled
 */

defined( 'ABSPATH' ) || exit;

class WBP_Order {

	public static function init() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hidden_meta' ) );

		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'maybe_reduce_stock' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'maybe_reduce_stock' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'maybe_reduce_stock' ) );

		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'maybe_restore_stock' ) );
	}

	/**
	 * Save the pack contents on the order item.
	 */
	public static function save_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['wbp_contents'] ) || ! is_array( $values['wbp_contents'] ) ) {
			return;
		}

		$bundles = WBP_Items::get_for_pack( $values['product_id'] );

		foreach ( $values['wbp_contents'] as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$b  = isset( $bundles[ $id ] ) ? $bundles[ $id ] : null;

			// Name and weight — fall back to the product itself if the bundle
			// is no longer part of the current allowed list.
			if ( $b ) {
				$name     = $b['name'];
				$weight_g = $b['weight_g'];
			} else {
				$product  = wc_get_product( $id );
				$name     = $product ? $product->get_name() : ( '#' . $id );
				$weight_g = $product ? WBP_Items::to_grams( $product->get_weight() ) : 0;
			}

			$qty = isset( $row['qty'] ) ? (int) $row['qty'] : 0;

			$item->add_meta_data(
				$name,
				sprintf(
					/* translators: 1: quantity, 2: per-bundle weight, 3: total weight */
					__( '%1$s × %2$s (%3$s)', 'weight-based-product-packs' ),
					number_format_i18n( $qty ),
					WBP_Items::weight_label( $weight_g ),
					WBP_Items::weight_label( $weight_g * $qty )
				),
				true
			);
		}

		// Raw data for stock handling and later processing.
		$item->add_meta_data( '_wbp_contents', $values['wbp_contents'], true );
		$item->add_meta_data( '_wbp_pack_id', absint( $values['product_id'] ), true );
	}

	/**
	 * Hide raw meta from the order item display.
	 */
	public static function hidden_meta( $hidden ) {
		$hidden[] = '_wbp_contents';
		$hidden[] = '_wbp_pack_id';
		return $hidden;
	}

	/**
	 * Reduce bundle stock (once per order).
	 */
	public static function maybe_reduce_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_wbp_stock_reduced' ) ) {
			return;
		}

		$reduced_any = false;

		foreach ( $order->get_items() as $item ) {
			$contents = $item->get_meta( '_wbp_contents' );
			if ( empty( $contents ) || ! is_array( $contents ) ) {
				continue;
			}

			$line_qty = (int) $item->get_quantity();

			foreach ( $contents as $row ) {
				$product = wc_get_product( isset( $row['id'] ) ? (int) $row['id'] : 0 );
				if ( ! $product ) {
					continue;
				}
				$reduce = (int) $row['qty'] * $line_qty;
				if ( $reduce > 0 ) {
					wc_update_product_stock( $product, $reduce, 'decrease' );
					$order->add_order_note(
						sprintf(
							/* translators: 1: reduced amount, 2: product name */
							__( 'Weight-based pack: reduced "%2$s" stock by %1$s.', 'weight-based-product-packs' ),
							number_format_i18n( $reduce ),
							$product->get_name()
						)
					);
					$reduced_any = true;
				}
			}
		}

		if ( $reduced_any ) {
			$order->update_meta_data( '_wbp_stock_reduced', gmdate( 'Y-m-d H:i:s' ) );
			$order->save();
		}
	}

	/**
	 * Restore stock when an order is cancelled (if it was reduced before).
	 */
	public static function maybe_restore_stock( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( '_wbp_stock_reduced' ) ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$contents = $item->get_meta( '_wbp_contents' );
			if ( empty( $contents ) || ! is_array( $contents ) ) {
				continue;
			}

			$line_qty = (int) $item->get_quantity();

			foreach ( $contents as $row ) {
				$product = wc_get_product( isset( $row['id'] ) ? (int) $row['id'] : 0 );
				if ( ! $product ) {
					continue;
				}
				$increase = (int) $row['qty'] * $line_qty;
				if ( $increase > 0 ) {
					wc_update_product_stock( $product, $increase, 'increase' );
				}
			}
		}

		$order->delete_meta_data( '_wbp_stock_reduced' );
		$order->add_order_note( __( 'Weight-based pack: stock restored for this order.', 'weight-based-product-packs' ) );
		$order->save();
	}
}
