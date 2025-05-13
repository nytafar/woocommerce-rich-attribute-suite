<?php
/**
 * Plugin Name: WooCommerce Rich Attribute Suite
 * Plugin URI: https://jellum.net
 * Description: Enhance WooCommerce product attribute taxonomy pages with rich, translatable, and fully editable content using native WordPress tools.
 * Version: 1.0.2
 * Author: Lasse Jellum
 * Author URI: https://jellum.net
 * Text Domain: wc-rich-attribute-suite
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 * Requires PHP: 7.4
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('WC_RAS_VERSION', '1.0.2');
define('WC_RAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_RAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function wc_ras_is_woocommerce_active() {
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
}

// Include files only if WooCommerce is active
function wc_ras_init() {
    if (!wc_ras_is_woocommerce_active()) {
        add_action('admin_notices', 'wc_ras_woocommerce_missing_notice');
        return;
    }

    // Include core files
    require_once WC_RAS_PLUGIN_DIR . 'includes/cpt-attribute-page.php';
    require_once WC_RAS_PLUGIN_DIR . 'includes/frontend-hooks.php';
    require_once WC_RAS_PLUGIN_DIR . 'includes/admin-hooks.php';
    require_once WC_RAS_PLUGIN_DIR . 'includes/variation-improvements.php';
}
add_action('plugins_loaded', 'wc_ras_init');

// Admin notice for missing WooCommerce
function wc_ras_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WooCommerce Rich Attribute Suite requires WooCommerce to be installed and activated.', 'wc-rich-attribute-suite'); ?></p>
    </div>
    <?php
}

// Plugin activation hook
register_activation_hook(__FILE__, 'wc_ras_activate');
function wc_ras_activate() {
    // Flush rewrite rules on activation
    flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'wc_ras_deactivate');
function wc_ras_deactivate() {
    // Flush rewrite rules on deactivation
    flush_rewrite_rules();
}
