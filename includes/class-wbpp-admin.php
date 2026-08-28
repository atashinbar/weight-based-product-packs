<?php
/**
 * The "Pack Settings" panel on the admin product edit screen.
 */

defined( 'ABSPATH' ) || exit;

class WBPP_Admin {

	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
	}

	/**
	 * Add the "Pack Settings" tab and hide the General (price) tab for packs.
	 */
	public static function tabs( $tabs ) {
		$tabs['wbpp_pack'] = array(
			'label'    => __( 'Pack Settings', 'weight-based-product-packs' ),
			'target'   => 'wbpp_pack_data',
			'class'    => array( 'show_if_pack' ),
			'priority' => 15,
		);

		// A fixed price makes no sense for a pack; the price comes from its contents.
		if ( isset( $tabs['general'] ) ) {
			$tabs['general']['class'][] = 'hide_if_pack';
		}

		return $tabs;
	}

	/**
	 * Render the pack settings panel.
	 */
	public static function panel() {
		global $post, $product_object;

		$product_id = 0;
		if ( $product_object instanceof WC_Product ) {
			$product_id = $product_object->get_id();
		} elseif ( $post ) {
			$product_id = $post->ID;
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;

		$capacity = $product ? $product->get_meta( '_wbpp_capacity_g', true ) : '';
		$box_cost = $product ? $product->get_meta( '_wbpp_box_cost', true ) : '';
		$cat      = $product ? (int) $product->get_meta( '_wbpp_source_cat', true ) : 0;
		$excluded = $product ? (string) $product->get_meta( '_wbpp_exclude_ids', true ) : '';

		include WBPP_DIR . 'includes/admin/views/pack-panel.php';
	}

	/**
	 * Save the pack meta (only when the product type is `pack`).
	 */
	public static function save( $post_id ) {
		$product_type = isset( $_POST['product-type'] ) ? sanitize_title( wp_unslash( $_POST['product-type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by WooCommerce before this hook fires.
		if ( 'pack' !== $product_type ) {
			return;
		}

		// Extra guard on top of WooCommerce's own checks.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		$capacity = isset( $_POST['_wbpp_capacity_g'] ) ? absint( $_POST['_wbpp_capacity_g'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$box_cost = isset( $_POST['_wbpp_box_cost'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_wbpp_box_cost'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$cat      = isset( $_POST['_wbpp_source_cat'] ) ? absint( $_POST['_wbpp_source_cat'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$excluded = isset( $_POST['_wbpp_exclude_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_wbpp_exclude_ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$product->update_meta_data( '_wbpp_capacity_g', $capacity );
		$product->update_meta_data( '_wbpp_box_cost', $box_cost );
		$product->update_meta_data( '_wbpp_source_cat', $cat );
		$product->update_meta_data( '_wbpp_exclude_ids', $excluded );

		// Packs have no fixed base price; keep the price fields empty.
		$product->set_price( '' );
		$product->set_regular_price( '' );
		$product->set_sale_price( '' );

		$product->save();
	}
}
