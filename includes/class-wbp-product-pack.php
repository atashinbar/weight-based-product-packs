<?php
/**
 * The "weight-based pack" product type — a container the customer fills
 * with pre-defined weight bundles until it exactly matches its capacity.
 */

defined( 'ABSPATH' ) || exit;

class WBP_Product_Pack extends WC_Product_Simple {

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
		return (int) $this->get_meta( '_wbp_capacity_g', true, $context );
	}

	/**
	 * Optional fixed box/packaging cost.
	 */
	public function get_box_cost( $context = 'view' ) {
		return (float) $this->get_meta( '_wbp_box_cost', true, $context );
	}

	/**
	 * Term ID of the product category bundles are sourced from.
	 */
	public function get_source_cat( $context = 'view' ) {
		return (int) $this->get_meta( '_wbp_source_cat', true, $context );
	}

	/**
	 * Product/variation IDs excluded from this pack.
	 */
	public function get_exclude_ids( $context = 'view' ) {
		$raw = $this->get_meta( '_wbp_exclude_ids', true, $context );
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
		return WBP_Items::from_grams( $this->get_capacity_g() );
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
	}

	/**
	 * Map the `pack` type to our product class.
	 */
	public static function map_product_class( $classname, $product_type, $post_type, $product_id ) {
		if ( 'pack' === $product_type ) {
			return 'WBP_Product_Pack';
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
			$html = '<span class="wbp-price-hint">' .
				esc_html__( 'Final price is calculated based on the selected contents.', 'weight-based-product-packs' ) .
				'</span>';
		}
		return $html;
	}
}
