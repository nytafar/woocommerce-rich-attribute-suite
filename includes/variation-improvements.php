<?php
/**
 * Variation Improvements
 *
 * Enhances WooCommerce variations with additional functionality.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Class for managing variation improvements
 */
class WC_RAS_Variation_Improvements {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if enabled
        if (apply_filters('wc_ras_enable_variation_improvements', true)) {
            $this->init();
        }
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Enable description fallback if the feature is enabled
        if (apply_filters('wc_ras_enable_variation_description_fallback', true)) {
            add_filter('woocommerce_available_variation', array($this, 'variation_description_fallback'), 10, 3);
        }

        // Enable Mix and Match support if the feature is enabled
        if (apply_filters('wc_ras_enable_mnm_description_support', true) && class_exists('WC_Mix_and_Match')) {
            add_action('wc_mnm_child_item_details', array($this, 'mnm_variation_description_support'), 105, 2);
        }
    }

    /**
     * Fallback to Attribute Term Description When Variation Description Is Absent
     * 
     * When a variation does not have a product-level description defined, this function
     * will provide a fallback to display the selected attribute term's description.
     *
     * @param array                $variation_data The variation data
     * @param WC_Product_Variable  $product       The parent product
     * @param WC_Product_Variation $variation     The variation product
     * @return array Modified variation data
     */
    public function variation_description_fallback($variation_data, $product, $variation) {
        // Only proceed if variation description is empty
        if (empty($variation_data['variation_description'])) {
            $term_descriptions = array();
            $term_page_links = array();
            
            // Get all attributes for this variation
            $attributes = $variation->get_attributes();
            
            if (!empty($attributes)) {
                foreach ($attributes as $attribute_name => $attribute_value) {
                    // Skip if attribute value is empty
                    if (empty($attribute_value)) {
                        continue;
                    }
                    
                    // Get the attribute taxonomy name (e.g., 'pa_color')
                    $taxonomy = str_replace('attribute_', '', $attribute_name);
                    
                    // Skip non-taxonomy attributes
                    if (!taxonomy_exists($taxonomy)) {
                        continue;
                    }
                    
                    // Get the term object
                    $term = get_term_by('slug', $attribute_value, $taxonomy);
                    
                    // Skip if term doesn't exist
                    if (!$term || is_wp_error($term)) {
                        continue;
                    }
                    
                    // First check if we have a rich attribute page for this term
                    $attribute_page = wc_ras_get_cached_attribute_page($term->slug);
                    
                    if ($attribute_page) {
                        // Use the attribute page excerpt if available, otherwise use the content
                        $content = !empty($attribute_page->post_excerpt) ? $attribute_page->post_excerpt : $attribute_page->post_content;
                        
                        if (!empty($content)) {
                            $term_descriptions[] = wp_trim_words($content, 30, '...');
                            
                            // Add link to attribute page
                            $term_page_links[] = '<a href="' . esc_url(get_term_link($term)) . '" class="term-page-link">' . 
                                                 esc_html__('Learn more', 'wc-rich-attribute-suite') . '</a>';
                        }
                    } else {
                        // Fallback to term description if no attribute page exists
                        $term_description = trim($term->description);
                        
                        // Get the term's linked page if any (legacy support)
                        $page_id = get_term_meta($term->term_id, 'linked_page_id', true);
                        $custom_url = get_term_meta($term->term_id, 'custom_page_url', true);
                        
                        // Add to our collection if description exists
                        if (!empty($term_description)) {
                            $term_descriptions[] = $term_description;
                            
                            // Add page link if available
                            if (!empty($page_id) || !empty($custom_url)) {
                                $link_url = !empty($page_id) ? get_permalink($page_id) : $custom_url;
                                $link_text = !empty($page_id) ? get_the_title($page_id) : __('Learn more', 'wc-rich-attribute-suite');
                                
                                $term_page_links[] = '<a href="' . esc_url($link_url) . '" class="term-page-link">' . esc_html($link_text) . '</a>';
                            } else {
                                // Default to term archive link
                                $term_page_links[] = '<a href="' . esc_url(get_term_link($term)) . '" class="term-page-link">' . 
                                                     esc_html__('Learn more', 'wc-rich-attribute-suite') . '</a>';
                            }
                        }
                    }
                }
            }
            
            // If we found any term descriptions, use the first one as fallback
            if (!empty($term_descriptions)) {
                // Format with paragraph tags to match standard variation descriptions
                $description = '<p>' . $term_descriptions[0] . '</p>';
                
                // Add page link if available and enabled
                if (!empty($term_page_links[0]) && apply_filters('wc_ras_show_variation_description_links', true)) {
                    $description .= '<p class="term-page-link-wrapper">' . $term_page_links[0] . '</p>';
                }
                
                $variation_data['variation_description'] = $description;
                
                // Allow combining all term descriptions if enabled
                if (apply_filters('wc_ras_combine_all_term_descriptions', false) && count($term_descriptions) > 1) {
                    $variation_data['variation_description'] = '<p>' . implode('</p><p>', $term_descriptions) . '</p>';
                    
                    // Add all links if showing links is enabled
                    if (apply_filters('wc_ras_show_variation_description_links', true) && !empty($term_page_links)) {
                        $variation_data['variation_description'] .= '<p class="term-page-link-wrapper">' . 
                                                                   implode(' | ', $term_page_links) . '</p>';
                    }
                }
            }
        }
        
        return $variation_data;
    }

    /**
     * Add support for Mix and Match products to use attribute term descriptions
     * when variation descriptions are absent
     *
     * @param WC_MNM_Child_Item $child_item The child item
     * @param WC_Product        $product    The parent product
     */
    public function mnm_variation_description_support($child_item, $product) {
        // Check if short descriptions are enabled in Mix and Match settings
        if (!function_exists('wc_string_to_bool') || !wc_string_to_bool(get_option('wc_mnm_display_short_description', 'no'))) {
            return;
        }

        $child_product = $child_item->get_product();
        if (!$child_product || !$child_product->is_type('variation')) {
            return;
        }

        // Get variation description using WooCommerce's method
        $variation_description = $child_product->get_description();
        
        // If variation description is empty, try to get attribute term descriptions
        if (empty($variation_description)) {
            $term_descriptions = array();
            $term_page_links = array();
            
            // Get all attributes for this variation
            $attributes = $child_product->get_attributes();
            
            if (!empty($attributes)) {
                foreach ($attributes as $attribute_name => $attribute_value) {
                    // Skip if attribute value is empty
                    if (empty($attribute_value)) {
                        continue;
                    }
                    
                    // Get the attribute taxonomy name (e.g., 'pa_color')
                    $taxonomy = str_replace('attribute_', '', $attribute_name);
                    
                    // Skip non-taxonomy attributes
                    if (!taxonomy_exists($taxonomy)) {
                        continue;
                    }
                    
                    // Get the term object
                    $term = get_term_by('slug', $attribute_value, $taxonomy);
                    
                    // Skip if term doesn't exist
                    if (!$term || is_wp_error($term)) {
                        continue;
                    }
                    
                    // First check if we have a rich attribute page for this term
                    $attribute_page = wc_ras_get_cached_attribute_page($term->slug);
                    
                    if ($attribute_page) {
                        // Use the attribute page excerpt if available, otherwise use the content
                        $content = !empty($attribute_page->post_excerpt) ? $attribute_page->post_excerpt : $attribute_page->post_content;
                        
                        if (!empty($content)) {
                            $term_descriptions[] = wp_trim_words($content, 30, '...');
                            
                            // Add link to attribute page
                            $term_page_links[] = '<a href="' . esc_url(get_term_link($term)) . '" class="term-page-link">' . 
                                                 esc_html__('Learn more', 'wc-rich-attribute-suite') . '</a>';
                        }
                    } else {
                        // Fallback to term description if no attribute page exists
                        $term_description = trim($term->description);
                        
                        // Get the term's linked page if any (legacy support)
                        $page_id = get_term_meta($term->term_id, 'linked_page_id', true);
                        $custom_url = get_term_meta($term->term_id, 'custom_page_url', true);
                        
                        // Add to our collection if description exists
                        if (!empty($term_description)) {
                            $term_descriptions[] = $term_description;
                            
                            // Add page link if available
                            if (!empty($page_id) || !empty($custom_url)) {
                                $link_url = !empty($page_id) ? get_permalink($page_id) : $custom_url;
                                $link_text = !empty($page_id) ? get_the_title($page_id) : __('Learn more', 'wc-rich-attribute-suite');
                                
                                $term_page_links[] = '<a href="' . esc_url($link_url) . '" class="term-page-link">' . esc_html($link_text) . '</a>';
                            } else {
                                // Default to term archive link
                                $term_page_links[] = '<a href="' . esc_url(get_term_link($term)) . '" class="term-page-link">' . 
                                                     esc_html__('Learn more', 'wc-rich-attribute-suite') . '</a>';
                            }
                        }
                    }
                }
            }
            
            // If we found any term descriptions, use the first one as fallback
            if (!empty($term_descriptions)) {
                // Format with paragraph tags to match standard variation descriptions
                $variation_description = '<p>' . $term_descriptions[0] . '</p>';
                
                // Add page link if available and enabled
                if (!empty($term_page_links[0]) && apply_filters('wc_ras_show_variation_description_links', true)) {
                    $variation_description .= '<p class="term-page-link-wrapper">' . $term_page_links[0] . '</p>';
                }
                
                // Allow combining all term descriptions if enabled
                if (apply_filters('wc_ras_combine_all_term_descriptions', false) && count($term_descriptions) > 1) {
                    $variation_description = '<p>' . implode('</p><p>', $term_descriptions) . '</p>';
                    
                    // Add all links if showing links is enabled
                    if (apply_filters('wc_ras_show_variation_description_links', true) && !empty($term_page_links)) {
                        $variation_description .= '<p class="term-page-link-wrapper">' . 
                                                 implode(' | ', $term_page_links) . '</p>';
                    }
                }
            }
        }
        
        // Display the description if we have one
        if (!empty($variation_description)) {
            echo '<div class="mnm-child-item-short-description">';
            echo wp_kses_post($variation_description);
            echo '</div>';
        }
    }
}

// Initialize the class
new WC_RAS_Variation_Improvements();
