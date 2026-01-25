<?php
/**
 * Plugin Name: WooCommerce Rich Attribute Suite
 * Plugin URI: https://jellum.net
 * Description: Enhance WooCommerce product attribute taxonomy pages with rich, translatable, and fully editable content using native WordPress tools.
 * Version: 1.3.0
 * Author: Lasse Jellum
 * Author URI: https://jellum.net
 * Text Domain: wc-rich-attribute-suite
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 9.6
 * Requires PHP: 7.4
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined( 'ABSPATH' ) || exit;

// Declare HPOS (High-Performance Order Storage) compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

// Define plugin constants.
define( 'WC_RAS_VERSION', '1.3.0' );
define( 'WC_RAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_RAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active.
 *
 * @since 1.0.0
 *
 * @return bool True if WooCommerce is active.
 */
function wc_ras_is_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function wc_ras_init() {
	if ( ! wc_ras_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wc_ras_woocommerce_missing_notice' );
		return;
	}

	// Include class files.
	require_once WC_RAS_PLUGIN_DIR . 'includes/class-wc-ras-attribute-page-cpt.php';
	require_once WC_RAS_PLUGIN_DIR . 'includes/class-wc-ras-frontend.php';
	require_once WC_RAS_PLUGIN_DIR . 'includes/class-wc-ras-admin.php';
	require_once WC_RAS_PLUGIN_DIR . 'includes/class-wc-ras-variation-improvements.php';
	require_once WC_RAS_PLUGIN_DIR . 'includes/class-wc-ras-inline-variation-description.php';

	// Initialize classes.
	$cpt = new WC_RAS_Attribute_Page_CPT();
	$cpt->init();

	$frontend = new WC_RAS_Frontend();
	$frontend->init();

	$admin = new WC_RAS_Admin();
	$admin->init();

	// Note: WC_RAS_Variation_Improvements and WC_RAS_Inline_Variation_Description
	// self-initialize in their constructors.
}
add_action( 'plugins_loaded', 'wc_ras_init' );

/**
 * Admin notice for missing WooCommerce.
 *
 * @since 1.0.0
 */
function wc_ras_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'WooCommerce Rich Attribute Suite requires WooCommerce to be installed and activated.', 'wc-rich-attribute-suite' ); ?></p>
	</div>
	<?php
}

/**
 * Plugin activation.
 *
 * @since 1.0.0
 */
function wc_ras_activate() {
	update_option( 'wc_ras_flush_rewrite_rules', true );
}
register_activation_hook( __FILE__, 'wc_ras_activate' );

/**
 * Plugin deactivation.
 *
 * @since 1.0.0
 */
function wc_ras_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_ras_deactivate' );

/**
 * Get an attribute page by term slug.
 *
 * Backward-compatible wrapper for WC_RAS_Attribute_Page_CPT::get_attribute_page().
 *
 * @since 1.0.0
 *
 * @param string $term_slug The term slug.
 *
 * @return WP_Post|null The attribute page post or null if not found.
 */
function wc_ras_get_cached_attribute_page( $term_slug ) {
	return WC_RAS_Attribute_Page_CPT::get_attribute_page( $term_slug );
}

/**
 * Check if current page is a product attribute archive.
 *
 * Backward-compatible wrapper for WC_RAS_Frontend::is_product_attribute().
 *
 * @since 1.0.0
 *
 * @return bool True if current page is a product attribute taxonomy.
 */
function wc_ras_is_product_attribute() {
	return WC_RAS_Frontend::is_product_attribute();
}
