<?php
/**
 * Origin module — frontend enqueue + template fallback.
 *
 * Owns:
 *   - CPT stylesheet enqueue (origin.css) on CPT archive, CPT single,
 *     origin_country archive, certification archive. No product-page
 *     styling — theme owns that in this pass.
 *   - origin-radar.js registration for modal use in fase 3.
 *   - Template-include fallback so CPT archive/single use plugin
 *     templates when the theme does not override.
 *
 * Variation description enrichment is handled by
 * variation-improvements.php, which builds the HTML via
 * templates/parts/variation-description.php — not here.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Enqueue origin stylesheet on CPT contexts only. Product pages intentionally
 * omitted — variation description enrichment is theme-styled.
 */
function wc_ras_origin_enqueue_styles() {
    $should_load = is_post_type_archive('attribute_page')
        || is_singular('attribute_page')
        || is_tax('origin_country')
        || is_tax('certification');

    if (!$should_load) {
        return;
    }

    wp_enqueue_style(
        'wc-ras-origin',
        WC_RAS_PLUGIN_URL . 'assets/css/origin.css',
        array(),
        WC_RAS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wc_ras_origin_enqueue_styles');

/**
 * Register the radar script. Callers enqueue on demand (modal in fase 3).
 */
function wc_ras_origin_register_radar_script() {
    wp_register_script(
        'wc-ras-origin-radar',
        WC_RAS_PLUGIN_URL . 'assets/js/origin-radar.js',
        array(),
        WC_RAS_VERSION,
        true
    );

    if (function_exists('wc_ras_taste_axes')) {
        wp_localize_script('wc-ras-origin-radar', 'wcRasTasteAxes', wc_ras_taste_axes());
    }
}
add_action('wp_enqueue_scripts', 'wc_ras_origin_register_radar_script');

/**
 * Fall back to plugin templates when the active theme does not provide
 * archive/single templates for the attribute_page CPT.
 *
 * Theme override order (first match wins):
 *   1. {theme}/single-attribute_page.php / archive-attribute_page.php
 *   2. {plugin}/templates/single-attribute_page.php / archive-attribute_page.php
 *
 * @param string $template Original template path resolved by WP.
 * @return string
 */
function wc_ras_origin_template_include($template) {
    if (is_singular('attribute_page')) {
        $theme_hit = locate_template(array('single-attribute_page.php'));
        if ($theme_hit) {
            return $theme_hit;
        }
        $plugin_template = WC_RAS_PLUGIN_DIR . 'templates/single-attribute_page.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    if (is_post_type_archive('attribute_page')) {
        $theme_hit = locate_template(array('archive-attribute_page.php'));
        if ($theme_hit) {
            return $theme_hit;
        }
        $plugin_template = WC_RAS_PLUGIN_DIR . 'templates/archive-attribute_page.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }

    return $template;
}
add_filter('template_include', 'wc_ras_origin_template_include', 20);
