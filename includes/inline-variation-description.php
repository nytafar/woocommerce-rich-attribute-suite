<?php
/**
 * Inline Variation Description
 *
 * Renders variation descriptions inline within the variations table,
 * eliminating CLS and DOM manipulation issues.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

/**
 * Class for inline variation description rendering
 * 
 * This feature must be explicitly enabled by the theme using:
 * add_filter('wc_ras_enable_inline_variation_description', '__return_true');
 * 
 * When enabled, this class:
 * 1. Uses JavaScript to inject a placeholder row into the variations table
 * 2. Provides a custom WooCommerce variation template that omits the description div
 * 3. Updates the placeholder content on variation change without DOM manipulation
 * 
 * This approach eliminates:
 * - Cumulative Layout Shift (CLS) from DOM manipulation
 * - Description duplication issues
 * - Race conditions between WooCommerce's template rendering and theme JS
 */
class WC_RAS_Inline_Variation_Description {

    /**
     * Whether the feature is enabled (cached after first check)
     * 
     * @var bool|null
     */
    private $is_enabled = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Defer initialization to 'init' hook to ensure theme filters are registered
        add_action('init', array($this, 'maybe_init'), 20);
    }

    /**
     * Check if feature is enabled and initialize if so
     */
    public function maybe_init() {
        if (!$this->is_enabled()) {
            return;
        }

        $this->init();
    }

    /**
     * Check if the feature is enabled
     * 
     * @return bool
     */
    private function is_enabled() {
        if ($this->is_enabled === null) {
            $this->is_enabled = apply_filters('wc_ras_enable_inline_variation_description', false);
        }
        return $this->is_enabled;
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Override WooCommerce variation template to remove description
        add_filter('wc_get_template', array($this, 'override_variation_template'), 10, 5);

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add variation description data to AJAX response
        add_filter('woocommerce_available_variation', array($this, 'add_description_data_to_variation'), 15, 3);
    }

    /**
     * Override WooCommerce variation template to remove description div
     *
     * @param string $template      Template path
     * @param string $template_name Template name
     * @param array  $args          Template arguments
     * @param string $template_path Template path
     * @param string $default_path  Default path
     * @return string Modified template path
     */
    public function override_variation_template($template, $template_name, $args, $template_path, $default_path) {
        if ($template_name === 'single-product/add-to-cart/variation.php') {
            $plugin_template = WC_RAS_PLUGIN_DIR . 'templates/variation-no-description.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }

    /**
     * Enqueue scripts for inline description updates
     */
    public function enqueue_scripts() {
        if (!is_product()) {
            return;
        }

        // Get configuration from filter
        $config = apply_filters('wc_ras_inline_variation_description_config', array(
            'target_attribute' => '', // Empty = auto-detect or first attribute
            'auto_detect' => true,    // Auto-detect attribute with description
        ));

        wp_enqueue_script(
            'wc-ras-inline-description',
            WC_RAS_PLUGIN_URL . 'assets/js/inline-variation-description.js',
            array('jquery', 'wc-add-to-cart-variation'),
            WC_RAS_VERSION,
            true
        );

        // Pass configuration to JS
        wp_localize_script('wc-ras-inline-description', 'wcRasInlineDesc', array(
            'animationDuration' => apply_filters('wc_ras_inline_description_animation_duration', 200),
            'targetAttribute' => $config['target_attribute'],
            'autoDetect' => $config['auto_detect'],
        ));
    }

    /**
     * Add description data to variation response
     * 
     * This ensures the description is available in the variation data
     * even when using the no-description template
     *
     * @param array                $variation_data The variation data
     * @param WC_Product_Variable  $product       The parent product
     * @param WC_Product_Variation $variation     The variation product
     * @return array Modified variation data
     */
    public function add_description_data_to_variation($variation_data, $product, $variation) {
        // The description should already be populated by variation_description_fallback
        // We just need to ensure it's marked for inline display
        $variation_data['wc_ras_inline_description'] = true;
        
        return $variation_data;
    }
}

// Initialize the class
new WC_RAS_Inline_Variation_Description();
