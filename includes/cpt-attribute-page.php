<?php
/**
 * Custom Post Type: Attribute Page
 *
 * Registers the attribute_page CPT and related meta fields.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Register the attribute_page custom post type
 */
function wc_ras_register_attribute_page_cpt() {
    $labels = array(
        'name'                  => _x('Attribute Pages', 'Post type general name', 'wc-rich-attribute-suite'),
        'singular_name'         => _x('Attribute Page', 'Post type singular name', 'wc-rich-attribute-suite'),
        'menu_name'             => _x('Attribute Pages', 'Admin Menu text', 'wc-rich-attribute-suite'),
        'name_admin_bar'        => _x('Attribute Page', 'Add New on Toolbar', 'wc-rich-attribute-suite'),
        'add_new'               => __('Add New', 'wc-rich-attribute-suite'),
        'add_new_item'          => __('Add New Attribute Page', 'wc-rich-attribute-suite'),
        'new_item'              => __('New Attribute Page', 'wc-rich-attribute-suite'),
        'edit_item'             => __('Edit Attribute Page', 'wc-rich-attribute-suite'),
        'view_item'             => __('View Attribute Page', 'wc-rich-attribute-suite'),
        'all_items'             => __('All Attribute Pages', 'wc-rich-attribute-suite'),
        'search_items'          => __('Search Attribute Pages', 'wc-rich-attribute-suite'),
        'parent_item_colon'     => __('Parent Attribute Pages:', 'wc-rich-attribute-suite'),
        'not_found'             => __('No attribute pages found.', 'wc-rich-attribute-suite'),
        'not_found_in_trash'    => __('No attribute pages found in Trash.', 'wc-rich-attribute-suite'),
        'featured_image'        => _x('Attribute Image', 'Overrides the "Featured Image" phrase', 'wc-rich-attribute-suite'),
        'set_featured_image'    => _x('Set attribute image', 'Overrides the "Set featured image" phrase', 'wc-rich-attribute-suite'),
        'remove_featured_image' => _x('Remove attribute image', 'Overrides the "Remove featured image" phrase', 'wc-rich-attribute-suite'),
        'use_featured_image'    => _x('Use as attribute image', 'Overrides the "Use as featured image" phrase', 'wc-rich-attribute-suite'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => false,  // Content is accessed via attribute archive
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => false,  // No direct URL access, content shown on attribute archives
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => null,
        'menu_icon'          => 'dashicons-tag',
        'supports'           => array('title', 'editor', 'thumbnail', 'revisions'),
        'show_in_rest'       => true,  // Enable block editor
    );

    register_post_type('attribute_page', $args);
}
add_action('init', 'wc_ras_register_attribute_page_cpt');

/**
 * Register meta fields for attribute_page CPT
 */
function wc_ras_register_attribute_page_meta() {
    // Register region meta field
    register_post_meta('attribute_page', 'region', array(
        'type'              => 'string',
        'description'       => __('Region information for the attribute', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // Register smak (taste) meta field
    register_post_meta('attribute_page', 'smak', array(
        'type'              => 'string',
        'description'       => __('Taste profile for the attribute', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    /**
     * Hook for registering additional meta fields
     * 
     * @param string $post_type The post type (attribute_page)
     */
    do_action('wc_ras_register_attribute_page_meta_fields', 'attribute_page');
}
add_action('init', 'wc_ras_register_attribute_page_meta');

/**
 * Auto-create attribute_page CPT for each relevant attribute term.
 * This creates a matching attribute page for new terms.
 *
 * @param int    $term_id  Term ID
 * @param string $taxonomy Taxonomy name
 */
function wc_ras_sync_attribute_pages_on_term_create($term_id, $taxonomy) {
    // Check if the taxonomy is a product attribute
    if (strpos($taxonomy, 'pa_') !== 0) {
        return;
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return;
    }

    // Check if an attribute page already exists with this slug
    $existing = get_page_by_path($term->slug, OBJECT, 'attribute_page');
    if (!$existing) {
        // Create new attribute page
        $post_id = wp_insert_post(array(
            'post_title'   => $term->name,
            'post_name'    => $term->slug,
            'post_type'    => 'attribute_page',
            'post_status'  => 'publish',
            'post_content' => '',
        ));

        if (!is_wp_error($post_id)) {
            // Store the taxonomy this page is related to
            update_post_meta($post_id, '_attribute_taxonomy', $taxonomy);
            update_post_meta($post_id, '_attribute_term_id', $term_id);
        }
    }
}

// Hook into all product attribute taxonomy term creation
function wc_ras_register_attribute_term_hooks() {
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    
    if (!empty($attribute_taxonomies)) {
        foreach ($attribute_taxonomies as $taxonomy) {
            $taxonomy_name = wc_attribute_taxonomy_name($taxonomy->attribute_name);
            add_action("created_{$taxonomy_name}", 'wc_ras_sync_attribute_pages_on_term_create', 10, 2);
        }
    }
}
add_action('init', 'wc_ras_register_attribute_term_hooks', 20);

/**
 * Get or create an attribute page for a specific term
 *
 * @param string $term_slug The term slug
 * @return WP_Post|null The attribute page post or null if not found/created
 */
function wc_ras_get_attribute_page($term_slug) {
    $cache_key = 'attribute_page_' . md5($term_slug);
    $page_id = wp_cache_get($cache_key, 'wc_ras_attribute_pages');

    if (false === $page_id) {
        $page = get_page_by_path($term_slug, OBJECT, 'attribute_page');
        $page_id = $page ? $page->ID : 0;
        wp_cache_set($cache_key, $page_id, 'wc_ras_attribute_pages');
    }

    return $page_id ? get_post($page_id) : null;
}
