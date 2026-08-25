<?php
/**
 * Cart logic for weight-based packs:
 *  - AJAX add-to-cart with server-side validation
 *  - Dynamic pricing: sum(bundle price x qty) + box cost
 *  - Re-validation in cart and checkout (contents + stock)
 *  - Contents breakdown display in cart/checkout
 */

defined( 'ABSPATH' ) || exit;

class WBP_Cart {

	/**
	 * Internal flag: an add is in progress via the pack builder.
	 *
	 * @var bool
	 */
	private static $adding_via_builder = false;

	public static function init() {
		// AJAX add-to-cart.
		add_action( 'wp_ajax_wbp_add_pack', array( __CLASS__, 'ajax_add' ) );
		add_action( 'wp_ajax_nopriv_wbp_add_pack', array( __CLASS__, 'ajax_add' ) );

		// Block direct/classic add-to-cart of a pack without contents.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'block_standard_add' ), 10, 2 );

		// Price and weight of pack lines.
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'recalc_prices' ) );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'session_restore' ), 10, 2 );

		// Contents display in cart/checkout.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data_display' ), 10, 2 );

		// Final cart validation.
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'validate_cart' ) );
	}

	/* ---------------------------------------------------------------------
	 * Add to cart (AJAX)
	 * ------------------------------------------------------------------- */

	public static function ajax_add() {
		check_ajax_referer( 'wbp_add_pack', 'nonce' );

		$pack_id = isset( $_POST['pack_id'] ) ? absint( $_POST['pack_id'] ) : 0;
		$pack    = $pack_id ? wc_get_product( $pack_id ) : null;

		if ( ! $pack || 'pack' !== $pack->get_type() ) {
			wp_send_json_error( array( 'message' => __( 'Pack not found.', 'weight-based-product-packs' ) ) );
		}

		$capacity = $pack->get_capacity_g();
		if ( $capacity <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'This pack is not configured correctly.', 'weight-based-product-packs' ) ) );
		}

		$raw = isset( $_POST['contents'] ) ? json_decode( wp_unslash( $_POST['contents'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded and validated below.
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		// Only bundles allowed for this pack are accepted; prices and weights are always read from the server.
		$allowed = WBP_Items::get_for_pack( $pack );

		$contents = array();
		$total_g  = 0;
		foreach ( $raw as $row ) {
			$id  = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$qty = isset( $row['qty'] ) ? absint( $row['qty'] ) : 0;
			if ( $qty <= 0 || ! $id || ! isset( $allowed[ $id ] ) ) {
				continue;
			}
			$contents[ $id ] = array(
				'id'  => $id,
				'qty' => $qty,
			);
			$total_g        += (int) $allowed[ $id ]['weight_g'] * $qty;
		}

		if ( empty( $contents ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose the pack contents before purchasing.', 'weight-based-product-packs' ) ) );
		}

		// Core rule: total weight must EXACTLY match the capacity.
		if ( $total_g !== $capacity ) {
			$diff = $capacity - $total_g;
			if ( $diff > 0 ) {
				$message = sprintf(
					/* translators: %s: remaining weight in grams */
					__( 'The pack is not full yet; %s g remaining.', 'weight-based-product-packs' ),
					number_format_i18n( $diff )
				);
			} else {
				$message = sprintf(
					/* translators: %s: excess weight in grams */
					__( 'The pack is over capacity by %s g; remove some items.', 'weight-based-product-packs' ),
					number_format_i18n( - $diff )
				);
			}
			wp_send_json_error( array( 'message' => $message ) );
		}

		// Stock check (including other items already in the cart).
		$required = array();
		foreach ( $contents as $id => $row ) {
			$required[ $id ] = $row['qty'];
		}
		$required = self::accumulate_cart_quantities( $required );
		foreach ( $required as $id => $need ) {
			if ( ! isset( $allowed[ $id ] ) ) {
				continue; // non-pack line; verified in validate_cart.
			}
			$stock = $allowed[ $id ]['stock'];
			if ( null !== $stock && $need > $stock ) {
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: 1: product name, 2: current stock */
							__( 'Insufficient stock for "%1$s" (current stock: %2$s).', 'weight-based-product-packs' ),
							$allowed[ $id ]['name'],
							number_format_i18n( $stock )
						),
					)
				);
			}
		}

		// Normalize contents so identical packs merge in the cart.
		ksort( $contents, SORT_NUMERIC );
		$contents = array_values( $contents );

		self::$adding_via_builder = true;
		$added = WC()->cart->add_to_cart(
			$pack_id,
			1,
			0,
			array(),
			array(
				'wbp_contents' => $contents,
				'wbp_capacity' => $capacity,
			)
		);
		self::$adding_via_builder = false;

		if ( ! $added ) {
			wp_send_json_error( array( 'message' => __( 'Could not add the pack to the cart.', 'weight-based-product-packs' ) ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Pack added to the cart successfully.', 'weight-based-product-packs' ),
				'redirect' => wc_get_cart_url(),
			)
		);
	}

	/**
	 * Prevent buying a pack directly without choosing contents (e.g. add-to-cart=ID links).
	 */
	public static function block_standard_add( $valid, $product_id ) {
		if ( self::$adding_via_builder ) {
			return $valid;
		}
		$product = wc_get_product( $product_id );
		if ( $product && 'pack' === $product->get_type() ) {
			wc_add_notice( __( 'To purchase this pack, please select its contents on the product page first.', 'weight-based-product-packs' ), 'error' );
			return false;
		}
		return $valid;
	}

	/* ---------------------------------------------------------------------
	 * Pricing and weight
	 * ------------------------------------------------------------------- */

	/**
	 * Pack price = sum(current bundle price x qty) + box cost.
	 *
	 * @param WC_Product $pack_product
	 * @param array      $contents
	 * @return float|null Null when contents are invalid.
	 */
	public static function compute_price( $pack_product, $contents ) {
		if ( ! is_array( $contents ) ) {
			return null;
		}
		$sum = 0.0;
		foreach ( $contents as $row ) {
			$item = wc_get_product( isset( $row['id'] ) ? (int) $row['id'] : 0 );
			if ( ! $item ) {
				return null;
			}
			$qty = max( 1, isset( $row['qty'] ) ? (int) $row['qty'] : 1 );
			$sum += (float) $item->get_price() * $qty;
		}
		if ( $pack_product instanceof WBP_Product_Pack ) {
			$sum += $pack_product->get_box_cost();
		}
		return $sum;
	}

	/**
	 * Apply price/weight to pack lines whenever totals are calculated.
	 */
	public static function recalc_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['wbp_contents'] ) ) {
				continue;
			}
			self::apply_pack_pricing( $cart_item['data'], $cart_item['wbp_contents'], isset( $cart_item['wbp_capacity'] ) ? $cart_item['wbp_capacity'] : 0 );
		}
	}

	/**
	 * Restore price/weight of pack lines loaded from the session.
	 */
	public static function session_restore( $cart_item, $values ) {
		if ( isset( $values['wbp_contents'] ) ) {
			self::apply_pack_pricing(
				$cart_item['data'],
				$values['wbp_contents'],
				isset( $values['wbp_capacity'] ) ? $values['wbp_capacity'] : 0
			);
		}
		return $cart_item;
	}

	/**
	 * Apply the computed price and the capacity weight to a cart line product object.
	 */
	private static function apply_pack_pricing( $pack_product, $contents, $capacity_g = 0 ) {
		$price = self::compute_price( $pack_product, $contents );
		if ( null !== $price ) {
			$pack_product->set_price( $price );
		}
		if ( (int) $capacity_g > 0 ) {
			// Shipping weight = pack capacity (for weight-based shipping methods).
			$pack_product->set_weight( WBP_Items::from_grams( (int) $capacity_g ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Display
	 * ------------------------------------------------------------------- */

	/**
	 * Show the pack contents breakdown under the cart/checkout line.
	 */
	public static function cart_item_data_display( $item_data, $cart_item ) {
		if ( empty( $cart_item['wbp_contents'] ) || ! is_array( $cart_item['wbp_contents'] ) ) {
			return $item_data;
		}

		$bundles = WBP_Items::get_for_pack( $cart_item['product_id'] );
		$total_g = 0;

		foreach ( $cart_item['wbp_contents'] as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			$b  = isset( $bundles[ $id ] ) ? $bundles[ $id ] : null;
			if ( ! $b ) {
				continue;
			}
			$qty      = (int) $row['qty'];
			$total_g += $b['weight_g'] * $qty;
			$item_data[] = array(
				'key'   => $b['name'],
				'value' => sprintf(
					'%1$s × %2$s (%3$s)',
					number_format_i18n( $qty ),
					WBP_Items::weight_label( $b['weight_g'] ),
					WBP_Items::weight_label( $b['weight_g'] * $qty )
				),
			);
		}

		$item_data[] = array(
			'key'   => __( 'Pack total weight', 'weight-based-product-packs' ),
			'value' => WBP_Items::weight_label( $total_g ),
		);

		return $item_data;
	}

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------- */

	/**
	 * Final cart validation (runs on the cart page and checkout).
	 */
	public static function validate_cart() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$required = array(); // bundle_id => total required quantity.

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			// A pack line without contents (e.g. added programmatically) is invalid.
			if ( isset( $cart_item['product_id'] ) && empty( $cart_item['wbp_contents'] ) ) {
				$maybe_pack = wc_get_product( $cart_item['product_id'] );
				if ( $maybe_pack && 'pack' === $maybe_pack->get_type() ) {
					wc_add_notice(
						__( 'A pack without contents is in your cart; please remove it and build it again from the product page.', 'weight-based-product-packs' ),
						'error'
					);
					continue;
				}
			}

			if ( ! empty( $cart_item['wbp_contents'] ) && is_array( $cart_item['wbp_contents'] ) ) {
				$pack     = wc_get_product( $cart_item['product_id'] );
				$capacity = ! empty( $cart_item['wbp_capacity'] )
					? (int) $cart_item['wbp_capacity']
					: ( $pack ? $pack->get_capacity_g() : 0 );

				$total_g = 0;
				foreach ( $cart_item['wbp_contents'] as $row ) {
					$item = wc_get_product( isset( $row['id'] ) ? (int) $row['id'] : 0 );
					if ( ! $item ) {
						continue;
					}
					$qty      = (int) $row['qty'];
					$total_g += WBP_Items::to_grams( $item->get_weight() ) * $qty;
					$required[ $item->get_id() ] = ( isset( $required[ $item->get_id() ] ) ? $required[ $item->get_id() ] : 0 ) + $qty * (int) $cart_item['quantity'];
				}

				if ( $total_g !== $capacity ) {
					wc_add_notice(
						__( 'One of the packs in your cart is invalid; please remove it and build it again from the product page.', 'weight-based-product-packs' ),
						'error'
					);
				}
			} else {
				// Direct purchases of the same bundles also consume stock.
				$pid = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
				$required[ $pid ] = ( isset( $required[ $pid ] ) ? $required[ $pid ] : 0 ) + (int) $cart_item['quantity'];
			}
		}

		foreach ( $required as $id => $need ) {
			$item = wc_get_product( $id );
			if ( ! $item || ! $item->managing_stock() ) {
				continue;
			}
			$stock = (int) $item->get_stock_quantity();
			if ( $need > $stock ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: product name, 2: current stock */
						__( 'Insufficient stock for "%1$s" for this order (current stock: %2$s).', 'weight-based-product-packs' ),
						$item->get_name(),
						number_format_i18n( max( 0, $stock ) )
					),
					'error'
				);
			}
		}
	}

	/**
	 * Accumulate the required quantity of each bundle, including packs already
	 * in the cart and direct purchases.
	 *
	 * @param array $required bundle_id => qty (new quantity being added).
	 * @return array
	 */
	private static function accumulate_cart_quantities( $required ) {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return $required;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['wbp_contents'] ) && is_array( $cart_item['wbp_contents'] ) ) {
				foreach ( $cart_item['wbp_contents'] as $row ) {
					$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
					if ( ! $id ) {
						continue;
					}
					$required[ $id ] = ( isset( $required[ $id ] ) ? $required[ $id ] : 0 ) + (int) $row['qty'] * (int) $cart_item['quantity'];
				}
			} else {
				$pid = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
				$required[ $pid ] = ( isset( $required[ $pid ] ) ? $required[ $pid ] : 0 ) + (int) $cart_item['quantity'];
			}
		}

		return $required;
	}
}
