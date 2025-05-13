<?php
/**
 * Frontend Hooks
 *
 * Handles frontend integration for attribute pages.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Check if current page is a product attribute archive
 *
 * @return bool True if current page is a product attribute taxonomy
 */
function wc_ras_is_product_attribute() {
    if (!is_tax()) {
        return false;
    }
    
    $taxonomy = get_queried_object()->taxonomy;
    return substr($taxonomy, 0, 3) === 'pa_';
}

/**
 * Get cached attribute page by slug
 *
 * @param string $slug The attribute term slug
 * @return WP_Post|null The attribute page or null if not found
 */
function wc_ras_get_cached_attribute_page($slug) {
    $key = 'attribute_page_' . md5($slug);
    $cached = wp_cache_get($key, 'wc_ras_attribute_page');

    if ($cached === false) {
        $post = get_page_by_path($slug, OBJECT, 'attribute_page');
        $cached = $post ? $post->ID : 0;
        wp_cache_set($key, $cached, 'wc_ras_attribute_page', HOUR_IN_SECONDS);
    }

    return $cached ? get_post($cached) : null;
}

/**
 * Override WooCommerce attribute archive template
 *
 * @param string $template Original template path
 * @return string Modified template path
 */
function wc_ras_override_attribute_archive_template($template) {
    if (is_tax() && wc_ras_is_product_attribute()) {
        $term = get_queried_object();
        
        // Check if we have a matching attribute page
        $attribute_page = wc_ras_get_cached_attribute_page($term->slug);
        
        if ($attribute_page) {
            // Check for custom template in theme
            $theme_template = locate_template(array(
                'woocommerce/taxonomy-' . $term->taxonomy . '.php',
                'taxonomy-' . $term->taxonomy . '.php'
            ));
            
            if ($theme_template) {
                // Use theme template if one exists
                return $theme_template;
            } else {
                // Fall back to plugin template
                $plugin_template = WC_RAS_PLUGIN_DIR . 'templates/taxonomy-product-attribute.php';
                if (file_exists($plugin_template)) {
                    return $plugin_template;
                }
            }
        }
    }
    
    return $template;
}
add_filter('template_include', 'wc_ras_override_attribute_archive_template', 99);

/**
 * Add attribute page content to archive description
 *
 * @param string $description Original archive description
 * @return string Modified archive description with rich content
 */
function wc_ras_add_attribute_content_to_archive($description) {
    // Only modify on product attribute archives
    if (!is_tax() || !wc_ras_is_product_attribute()) {
        return $description;
    }
    
    $term = get_queried_object();
    $attribute_page = wc_ras_get_cached_attribute_page($term->slug);
    
    if (!$attribute_page) {
        return $description;
    }
    
    // Get the rich content
    $rich_content = apply_filters('the_content', $attribute_page->post_content);
    
    // Get metadata for display
    $region = get_post_meta($attribute_page->ID, 'region', true);
    $smak = get_post_meta($attribute_page->ID, 'smak', true);
    
    // Build content with term description and rich content
    $content = '';
    
    // Add original term description if it exists
    if (!empty($term->description)) {
        $content .= '<div class="term-description">' . wpautop(wptexturize($term->description)) . '</div>';
    }
    
    // Add metadata if available
    if (!empty($region) || !empty($smak)) {
        $content .= '<div class="attribute-meta">';
        
        if (!empty($region)) {
            $content .= '<div class="attribute-region"><strong>' . esc_html__('Region:', 'wc-rich-attribute-suite') . '</strong> ' . esc_html($region) . '</div>';
        }
        
        if (!empty($smak)) {
            $content .= '<div class="attribute-smak"><strong>' . esc_html__('Smak:', 'wc-rich-attribute-suite') . '</strong> ' . esc_html($smak) . '</div>';
        }
        
        $content .= '</div>';
    }
    
    // Add rich content
    $content .= '<div class="attribute-rich-content">' . $rich_content . '</div>';
    
    return $content;
}
add_filter('woocommerce_taxonomy_archive_description', 'wc_ras_add_attribute_content_to_archive', 20);

/**
 * Get attribute meta for a specific variation
 *
 * @param int    $variation_id Variation ID
 * @param string $attribute    Attribute name (default: pa_opprinnelse)
 * @return array Attribute metadata
 */
function wc_ras_get_attribute_meta_for_variation($variation_id, $attribute = 'pa_opprinnelse') {
    $product = wc_get_product($variation_id);
    
    if (!$product || !$product->is_type('variation')) {
        return array();
    }
    
    $attributes = $product->get_attributes();
    
    // Check if variation has this attribute
    if (!isset($attributes[$attribute])) {
        return array();
    }
    
    $attribute_value = $attributes[$attribute];
    
    // Get the term slug
    $term = get_term_by('slug', $attribute_value, $attribute);
    
    if (!$term) {
        return array();
    }
    
    // Get attribute page by slug
    $attribute_page = wc_ras_get_cached_attribute_page($term->slug);
    
    if (!$attribute_page) {
        return array();
    }
    
    // Get metadata
    return array(
        'region' => get_post_meta($attribute_page->ID, 'region', true),
        'smak' => get_post_meta($attribute_page->ID, 'smak', true),
    );
}

/**
 * Add attribute meta to variation data
 *
 * @param array                $data      Variation data
 * @param WC_Product_Variable  $product   Product object
 * @param WC_Product_Variation $variation Variation object
 * @return array Modified variation data
 */
function wc_ras_add_attribute_meta_to_variation($data, $product, $variation) {
    $attribute_meta = wc_ras_get_attribute_meta_for_variation($variation->get_id());
    
    if (!empty($attribute_meta)) {
        foreach ($attribute_meta as $key => $value) {
            if (!empty($value)) {
                $data['attribute_' . $key] = $value;
            }
        }
    }
    
    return $data;
}
add_filter('woocommerce_available_variation', 'wc_ras_add_attribute_meta_to_variation', 10, 3);

/**
 * Add attribute meta to AJAX product variations response
 */
function wc_ras_enqueue_variation_scripts() {
    // Only on product pages and only if variation meta display is enabled
    if (!is_product() || !apply_filters('wc_ras_enable_variation_meta_display', false)) {
        return;
    }
    
    // Add script to handle variation changes
    wp_enqueue_script(
        'wc-ras-variation-display',
        WC_RAS_PLUGIN_URL . 'assets/js/variation-display.js',
        array('jquery', 'wc-add-to-cart-variation'),
        WC_RAS_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'wc_ras_enqueue_variation_scripts');

/**
 * Create directory for assets if it doesn't exist
 */
function wc_ras_create_assets_directory() {
    // Check if 'assets/js' directory exists, create if it doesn't
    $js_dir = WC_RAS_PLUGIN_DIR . 'assets/js';
    if (!file_exists($js_dir)) {
        wp_mkdir_p($js_dir);
    }
}
add_action('plugins_loaded', 'wc_ras_create_assets_directory');
