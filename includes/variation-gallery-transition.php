<?php
/**
 * Variation Gallery Transition
 *
 * Crossfades the product gallery's main image when a variation is selected.
 * Pure presentation layer — listens to WooCommerce's `found_variation` event,
 * snapshots the current image as a ghost overlay, then fades the ghost once
 * the new image is decoded. Theme tunes timing/easing via CSS custom
 * properties on the gallery wrapper.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Registers and conditionally enqueues the gallery transition assets.
 */
class WC_RAS_Variation_Gallery_Transition {

    public function __construct() {
        if (!apply_filters('wc_ras_enable_variation_gallery_transition', true)) {
            return;
        }
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Registers the stylesheet and script. Kept separate from enqueue so
     * other code can opt into the asset without re-declaring it.
     */
    public function register_assets() {
        $css_path = WC_RAS_PLUGIN_DIR . 'assets/css/variation-gallery-transition.css';
        $js_path  = WC_RAS_PLUGIN_DIR . 'assets/js/variation-gallery-transition.js';

        wp_register_style(
            'wc-ras-variation-gallery-transition',
            WC_RAS_PLUGIN_URL . 'assets/css/variation-gallery-transition.css',
            array(),
            file_exists($css_path) ? filemtime($css_path) : WC_RAS_VERSION
        );

        wp_register_script(
            'wc-ras-variation-gallery-transition',
            WC_RAS_PLUGIN_URL . 'assets/js/variation-gallery-transition.js',
            array('jquery', 'wc-add-to-cart-variation'),
            file_exists($js_path) ? filemtime($js_path) : WC_RAS_VERSION,
            true
        );
    }

    /**
     * Enqueues the registered assets only on single product pages.
     */
    public function enqueue_assets() {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }
        wp_enqueue_style('wc-ras-variation-gallery-transition');
        wp_enqueue_script('wc-ras-variation-gallery-transition');
    }
}

new WC_RAS_Variation_Gallery_Transition();
