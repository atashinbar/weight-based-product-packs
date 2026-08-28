<?php
/**
 * Plugin Name:       Weight-Based Product Packs for WooCommerce
 * Description:       Let customers fill weight-based packs (e.g. 1 kg boxes) with pre-defined weight bundles. A pack can only be purchased when its total weight exactly matches its capacity.
 * Version:           1.0.0
 * Author:            Ali Atashinbar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       weight-based-product-packs
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.4
 * Tested up to:      7.1
 * WC requires at least: 6.0
 * WC tested up to:   11.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WBPP_VERSION', '1.0.0' );
define( 'WBPP_FILE', __FILE__ );
define( 'WBPP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBPP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS compatibility (WooCommerce custom order tables).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * On activation: register the `pack` term in WooCommerce's product_type taxonomy.
 */
function wbpp_activate() {
	if ( ! term_exists( 'pack', 'product_type' ) ) {
		wp_insert_term( 'pack', 'product_type' );
	}
}
register_activation_hook( __FILE__, 'wbpp_activate' );

/**
 * One-time migration: copy pack meta from the pre-review `_wbp_` prefix to `_wbpp_`.
 * (Keeps existing stores working after the prefix was lengthened for review.)
 */
function wbpp_migrate_legacy_meta() {
	if ( get_option( 'wbpp_legacy_meta_migrated' ) ) {
		return;
	}

	$keys = array( '_wbp_capacity_g', '_wbp_box_cost', '_wbp_source_cat', '_wbp_exclude_ids' );

	$packs = wc_get_products(
		array(
			'type'   => 'pack',
			'status' => 'any',
			'limit'  => -1,
		)
	);

	foreach ( $packs as $pack ) {
		$updated = false;
		foreach ( $keys as $old_key ) {
			$new_key = str_replace( '_wbp_', '_wbpp_', $old_key );
			if ( $pack->get_meta( $new_key ) && '' !== $pack->get_meta( $new_key ) ) {
				continue;
			}
			$old = $pack->get_meta( $old_key );
			if ( '' !== $old && null !== $old ) {
				$pack->update_meta_data( $new_key, $old );
				$updated = true;
			}
		}
		if ( $updated ) {
			$pack->save();
		}
	}

	update_option( 'wbpp_legacy_meta_migrated', 1 );
}

/**
 * Boot the plugin once WooCommerce is fully loaded.
 *
 * Note: in recent WooCommerce versions the WooCommerce class is not available
 * during `plugins_loaded`, so we hook `woocommerce_loaded` instead.
 */
function wbpp_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once WBPP_DIR . 'includes/class-wbpp-product-pack.php';
	require_once WBPP_DIR . 'includes/class-wbpp-items.php';
	require_once WBPP_DIR . 'includes/class-wbpp-admin.php';
	require_once WBPP_DIR . 'includes/class-wbpp-cart.php';
	require_once WBPP_DIR . 'includes/class-wbpp-order.php';
	require_once WBPP_DIR . 'includes/class-wbpp-frontend.php';

	WBPP_Product_Pack::init();
	WBPP_Items::init();
	WBPP_Admin::init();
	WBPP_Cart::init();
	WBPP_Order::init();
	WBPP_Frontend::init();

	add_action( 'init', 'wbpp_migrate_legacy_meta' );
}
add_action( 'woocommerce_loaded', 'wbpp_boot' );

/**
 * Admin notice when WooCommerce is missing.
 */
add_action( 'admin_notices', function () {
	if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="error"><p>' .
		esc_html__( 'Weight-Based Product Packs requires WooCommerce to be installed and activated.', 'weight-based-product-packs' ) .
		'</p></div>';
} );
