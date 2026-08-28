<?php
/**
 * Frontend: the pack builder on the product page, assets, and archive texts.
 */

defined( 'ABSPATH' ) || exit;

class WBPP_Frontend {

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

		wp_enqueue_style( 'wbpp-pack-builder', WBPP_URL . 'assets/css/pack-builder.css', array(), WBPP_VERSION );
		wp_enqueue_script( 'wbpp-pack-builder', WBPP_URL . 'assets/js/pack-builder.js', array(), WBPP_VERSION, true );
		wp_localize_script( 'wbpp-pack-builder', 'wbppData', self::builder_data( $product ) );
	}

	/**
	 * Data needed by the pack builder script.
	 *
	 * @param WBPP_Product_Pack $product
	 * @return array
	 */
	public static function builder_data( $product ) {
		$bundles = WBPP_Items::get_for_pack( $product );

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
			'nonce'          => wp_create_nonce( 'wbpp_add_pack' ),
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
	 * Sibling packs sharing the same source category (e.g. 1/2/5 kg sizes),
	 * sorted by capacity. Used by the pack-size switcher.
	 *
	 * @param WBPP_Product_Pack $pack
	 * @return array[] Array of [id, capacity_g, url, is_current].
	 */
	public static function get_pack_sizes( $pack ) {
		if ( ! $pack instanceof WBPP_Product_Pack ) {
			return array();
		}
		$cat = $pack->get_source_cat();
		if ( ! $cat ) {
			return array();
		}

		$sizes   = array();
		$packs = wc_get_products(
			array(
				'type'   => 'pack',
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		foreach ( $packs as $p ) {
			/** @var WBPP_Product_Pack $p */
			$capacity = $p->get_capacity_g();
			if ( $capacity <= 0 || (int) $p->get_source_cat() !== (int) $cat ) {
				continue;
			}
			$sizes[] = array(
				'id'         => $p->get_id(),
				'capacity_g' => $capacity,
				'url'        => $p->get_permalink(),
				'is_current' => $p->get_id() === $pack->get_id(),
			);
		}

		usort(
			$sizes,
			function ( $a, $b ) {
				return $a['capacity_g'] - $b['capacity_g'];
			}
		);

		return $sizes;
	}

	/**
	 * Render the pack builder (replaces the default add-to-cart form).
	 */
	public static function render_builder() {
		global $product;
		if ( ! $product || 'pack' !== $product->get_type() ) {
			return;
		}

		$bundles = WBPP_Items::get_for_pack( $product );

		// Group bundles by parent product (one card per nut with weight rows).
		$groups = array();
		foreach ( $bundles as $b ) {
			$pid = $b['parent_id'];
			if ( ! isset( $groups[ $pid ] ) ) {
				$groups[ $pid ] = array(
					'parent_id' => $pid,
					'name'      => $b['parent_name'],
					'image_url' => $b['parent_image'],
					'rows'      => array(),
				);
			}
			$groups[ $pid ]['rows'][] = $b;
		}

		wc_get_template(
			'single-product/add-to-cart/pack-builder.php',
			array(
				'product'        => $product,
				'wbpp_bundles'   => $bundles,
				'wbpp_groups'    => array_values( $groups ),
				'wbpp_sizes'     => self::get_pack_sizes( $product ),
				'wbpp_problems'  => WBPP_Items::config_problems( $product ),
			),
			'',
			WBPP_DIR . 'templates/'
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
