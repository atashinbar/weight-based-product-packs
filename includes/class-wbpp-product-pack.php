<?php
/**
 * The "weight-based pack" product type — a container the customer fills
 * with pre-defined weight bundles until it exactly matches its capacity.
 */

defined( 'ABSPATH' ) || exit;

class WBPP_Product_Pack extends WC_Product_Simple {

	/**
	 * Product type identifier.
	 */
	public function get_type() {
		return 'pack';
	}

	/**
	 * Pack capacity in grams.
	 */
	public function get_capacity_g( $context = 'view' ) {
		return (int) $this->get_meta( '_wbpp_capacity_g', true, $context );
	}

	/**
	 * Optional fixed box/packaging cost.
	 */
	public function get_box_cost( $context = 'view' ) {
		return (float) $this->get_meta( '_wbpp_box_cost', true, $context );
	}

	/**
	 * Term ID of the product category bundles are sourced from.
	 */
	public function get_source_cat( $context = 'view' ) {
		return (int) $this->get_meta( '_wbpp_source_cat', true, $context );
	}

	/**
	 * Product/variation IDs excluded from this pack.
	 */
	public function get_exclude_ids( $context = 'view' ) {
		$raw = $this->get_meta( '_wbpp_exclude_ids', true, $context );
		if ( empty( $raw ) ) {
			return array();
		}
		$ids = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Pack capacity converted to the store weight unit (used for shipping).
	 */
	public function get_capacity_in_store_unit() {
		return WBPP_Items::from_grams( $this->get_capacity_g() );
	}

	/* ---------------------------------------------------------------------
	 * Product type registration
	 * ------------------------------------------------------------------- */

	public static function init() {
		add_filter( 'woocommerce_product_class', array( __CLASS__, 'map_product_class' ), 10, 4 );

		/*
		 * The product type dropdown uses the legacy `product_type_selector` filter
		 * (see wc_get_product_types()); newer docs mention the `woocommerce_`-prefixed
		 * form, so both are hooked for compatibility.
		 */
		add_filter( 'product_type_selector', array( __CLASS__, 'add_type_option' ) );
		add_filter( 'woocommerce_product_type_selector', array( __CLASS__, 'add_type_option' ) );

		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'filter_is_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_price_html' ), 10, 2 );

		// REST API: keep type=pack on create/update (belt and suspenders; see rest_force_type).
		add_action( 'woocommerce_rest_insert_product_object', array( __CLASS__, 'rest_force_type' ), 10, 3 );
	}

	/**
	 * Map the `pack` type to our product class.
	 */
	public static function map_product_class( $classname, $product_type, $post_type, $product_id ) {
		if ( 'pack' === $product_type ) {
			return 'WBPP_Product_Pack';
		}
		return $classname;
	}

	/**
	 * Add "Weight-Based Pack" to the product type dropdown in admin.
	 */
	public static function add_type_option( $types ) {
		$types['pack'] = __( 'Weight-Based Pack', 'weight-based-product-packs' );
		return $types;
	}

	/**
	 * Packs have no fixed price; purchasability depends on configuration, not price.
	 */
	public static function filter_is_purchasable( $purchasable, $product ) {
		if ( $product instanceof self ) {
			return 'publish' === $product->get_status() && $product->get_capacity_g() > 0;
		}
		return $purchasable;
	}

	/**
	 * Show a pricing hint instead of a fixed price.
	 */
	public static function filter_price_html( $html, $product ) {
		if ( $product instanceof self ) {
			$html = '<span class="wbpp-price-hint">' .
				esc_html__( 'Final price is calculated based on the selected contents.', 'weight-based-product-packs' ) .
				'</span>';
		}
		return $html;
	}

	/**
	 * REST API: make sure a product created/updated with type=pack keeps the
	 * `pack` product_type term.
	 *
	 * The products controller resolves the class name via
	 * WC_Product_Factory::get_classname_from_product_type() (WC_Product_{type});
	 * the WBPP_Product_Pack bridge class below satisfies that convention, but this
	 * hook guarantees the term even if the controller instantiated a simple product.
	 */
	public static function rest_force_type( $product, $request, $creating ) {
		$type = $request->get_param( 'type' );
		if ( 'pack' !== $type || ! $product || 'pack' === $product->get_type() ) {
			return;
		}
		wp_set_object_terms( $product->get_id(), 'pack', 'product_type' );
		clean_post_cache( $product->get_id() );
		wc_delete_product_transients( $product->get_id() );
	}
}

if ( ! class_exists( 'WC_Product_Pack' ) ) {
	/**
	 * Bridge class for the WooCommerce product class-name convention
	 * (WC_Product_{type}) used by WC_Product_Factory and the REST products
	 * controller, so `type: pack` resolves to the real pack class.
	 */
	class WC_Product_Pack extends WBPP_Product_Pack {}
}
