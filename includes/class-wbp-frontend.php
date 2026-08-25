<?php
/**
 * Frontend: the pack builder on the product page, assets, and archive texts.
 */

defined( 'ABSPATH' ) || exit;

class WBP_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Standard WooCommerce per-type action: woocommerce_{type}_add_to_cart
		add_action( 'woocommerce_pack_add_to_cart', array( __CLASS__, 'render_builder' ) );

		// In shop archives, link the pack button to the product page.
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_button_text' ), 10, 2 );
		add_filter( 'woocommerce_product_add_to_cart_url', array( __CLASS__, 'loop_button_url' ), 10, 2 );
	}

	/**
	 * Enqueue CSS/JS only on pack product pages.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$product = wc_get_product( get_queried_object_id() );
		if ( ! $product || 'pack' !== $product->get_type() ) {
			return;
		}

		wp_enqueue_style( 'wbp-pack-builder', WBP_URL . 'assets/css/pack-builder.css', array(), WBP_VERSION );
		wp_enqueue_script( 'wbp-pack-builder', WBP_URL . 'assets/js/pack-builder.js', array(), WBP_VERSION, true );
		wp_localize_script( 'wbp-pack-builder', 'wbpData', self::builder_data( $product ) );
	}

	/**
	 * Data needed by the pack builder script.
	 *
	 * @param WBP_Product_Pack $product
	 * @return array
	 */
	public static function builder_data( $product ) {
		$bundles = WBP_Items::get_for_pack( $product );

		$items = array();
		foreach ( $bundles as $b ) {
			$items[] = array(
				'id'     => (int) $b['id'],
				'weight' => (int) $b['weight_g'],
				'price'  => (float) $b['price'],
				'stock'  => null === $b['stock'] ? null : (int) $b['stock'],
			);
		}

		return array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'wbp_add_pack' ),
			'packId'         => (int) $product->get_id(),
			'capacity'       => (int) $product->get_capacity_g(),
			'boxCost'        => (float) $product->get_box_cost(),
			'items'          => $items,
			'locale'         => str_replace( '_', '-', get_locale() ),
			'currencySymbol' => html_entity_decode( wp_strip_all_tags( get_woocommerce_currency_symbol() ), ENT_QUOTES, 'UTF-8' ),
			'priceDecimals'  => (int) wc_get_price_decimals(),
			'currencyPos'    => get_option( 'woocommerce_currency_pos', 'right' ),
			'i18n'           => array(
				/* translators: %s: remaining weight in grams */
				'remaining' => __( '%s g remaining', 'weight-based-product-packs' ),
				/* translators: %s: excess weight in grams */
				'over'      => __( '%s g over capacity; remove some items', 'weight-based-product-packs' ),
				'complete'  => __( 'Pack is full ✓', 'weight-based-product-packs' ),
				'empty'     => __( 'Select bundles to start filling the pack', 'weight-based-product-packs' ),
				'adding'    => __( 'Adding…', 'weight-based-product-packs' ),
				'error'     => __( 'Server communication error; please try again.', 'weight-based-product-packs' ),
				'boxCost'   => __( 'Box cost', 'weight-based-product-packs' ),
				'grams'     => __( 'g', 'weight-based-product-packs' ),
			),
		);
	}

	/**
	 * Render the pack builder (replaces the default add-to-cart form).
	 */
	public static function render_builder() {
		global $product;
		if ( ! $product || 'pack' !== $product->get_type() ) {
			return;
		}

		$bundles  = WBP_Items::get_for_pack( $product );
		$problems = WBP_Items::config_problems( $product );

		wc_get_template(
			'single-product/add-to-cart/pack-builder.php',
			array(
				'product'  => $product,
				'bundles'  => $bundles,
				'problems' => $problems,
			),
			'',
			WBP_DIR . 'templates/'
		);
	}

	/**
	 * Pack button text in shop listings.
	 */
	public static function loop_button_text( $text, $product = null ) {
		if ( $product && 'pack' === $product->get_type() ) {
			$text = __( 'Build pack', 'weight-based-product-packs' );
		}
		return $text;
	}

	/**
	 * Pack button URL in shop listings → product page.
	 */
	public static function loop_button_url( $url, $product = null ) {
		if ( $product && 'pack' === $product->get_type() ) {
			$url = $product->get_permalink();
		}
		return $url;
	}
}
