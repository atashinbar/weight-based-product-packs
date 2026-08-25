<?php
/**
 * Plugin Name:       Weight-Based Product Packs for WooCommerce
 * Description:       Let customers fill weight-based packs (e.g. 1 kg boxes) with pre-defined weight bundles. A pack can only be purchased when its total weight exactly matches its capacity.
 * Version:           1.0.0
 * Author:            Your Name
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

define( 'WBP_VERSION', '1.0.0' );
define( 'WBP_FILE', __FILE__ );
define( 'WBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBP_URL', plugin_dir_url( __FILE__ ) );

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
function wbp_activate() {
	if ( ! term_exists( 'pack', 'product_type' ) ) {
		wp_insert_term( 'pack', 'product_type' );
	}
}
register_activation_hook( __FILE__, 'wbp_activate' );

/**
 * Load translations.
 *
 * Intentionally kept although discouraged for WordPress.org-hosted plugins
 * (translations load automatically there): bundled .mo files should also work
 * on self-hosted installs that use this plugin outside the directory.
 */
function wbp_load_textdomain() {
	load_plugin_textdomain( 'weight-based-product-packs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wbp_load_textdomain' );

/**
 * Boot the plugin once WooCommerce is fully loaded.
 *
 * Note: in recent WooCommerce versions the WooCommerce class is not available
 * during `plugins_loaded`, so we hook `woocommerce_loaded` instead.
 */
function wbp_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once WBP_DIR . 'includes/class-wbp-product-pack.php';
	require_once WBP_DIR . 'includes/class-wbp-items.php';
	require_once WBP_DIR . 'includes/class-wbp-admin.php';
	require_once WBP_DIR . 'includes/class-wbp-cart.php';
	require_once WBP_DIR . 'includes/class-wbp-order.php';
	require_once WBP_DIR . 'includes/class-wbp-frontend.php';

	WBP_Product_Pack::init();
	WBP_Items::init();
	WBP_Admin::init();
	WBP_Cart::init();
	WBP_Order::init();
	WBP_Frontend::init();
}
add_action( 'woocommerce_loaded', 'wbp_boot' );

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
