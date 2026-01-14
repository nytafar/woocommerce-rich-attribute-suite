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
        'menu_name'             => _x('Attribute Suite', 'Admin Menu text', 'wc-rich-attribute-suite'),
        'name_admin_bar'        => _x('Attribute Page', 'Add New on Toolbar', 'wc-rich-attribute-suite'),
        'add_new'               => __('Add New', 'wc-rich-attribute-suite'),
        'add_new_item'          => __('Add New Attribute Page', 'wc-rich-attribute-suite'),
        'new_item'              => __('New Attribute Page', 'wc-rich-attribute-suite'),
        'edit_item'             => __('Edit Attribute Page', 'wc-rich-attribute-suite'),
        'view_item'             => __('View Attribute Page', 'wc-rich-attribute-suite'),
        'all_items'             => __('All Pages', 'wc-rich-attribute-suite'),
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
 * Add custom admin menu structure for Attribute Suite
 */
function wc_ras_customize_admin_menu() {
    global $submenu;
    
    // Get all product attributes
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    
    if (!empty($attribute_taxonomies) && isset($submenu['edit.php?post_type=attribute_page'])) {
        // Remove the "Add New" submenu item
        foreach ($submenu['edit.php?post_type=attribute_page'] as $key => $item) {
            if ($item[2] === 'post-new.php?post_type=attribute_page') {
                unset($submenu['edit.php?post_type=attribute_page'][$key]);
                break;
            }
        }
        
        // Insert attribute term configuration links before "All Pages"
        $new_submenu = array();
        $inserted = false;
        
        foreach ($submenu['edit.php?post_type=attribute_page'] as $key => $item) {
            // Insert attribute links before "All Pages"
            if (!$inserted && $item[2] === 'edit.php?post_type=attribute_page') {
                // Add separator comment
                foreach ($attribute_taxonomies as $tax) {
                    $taxonomy_name = wc_attribute_taxonomy_name($tax->attribute_name);
                    $attribute_label = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;
                    
                    $new_submenu[] = array(
                        $attribute_label,
                        'manage_product_terms',
                        'edit-tags.php?taxonomy=' . $taxonomy_name . '&post_type=product'
                    );
                }
                $inserted = true;
            }
            
            $new_submenu[] = $item;
        }
        
        $submenu['edit.php?post_type=attribute_page'] = $new_submenu;
    }
}
add_action('admin_menu', 'wc_ras_customize_admin_menu', 999);

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
 * Pre-fill attribute page data when creating from term edit screen
 * 
 * Captures URL parameters and sets up default title/slug
 */
function wc_ras_prefill_attribute_page_from_url() {
    global $pagenow;
    
    // Only on new post screen for attribute_page
    if ($pagenow !== 'post-new.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'attribute_page') {
        return;
    }
    
    // Check for attribute term parameters
    if (!isset($_GET['attribute_term']) || !isset($_GET['attribute_taxonomy'])) {
        return;
    }
    
    $term_slug = sanitize_text_field($_GET['attribute_term']);
    $taxonomy = sanitize_text_field($_GET['attribute_taxonomy']);
    
    // Get the term
    $term = get_term_by('slug', $term_slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
        return;
    }
    
    // Store in transient for use when post is saved
    set_transient('wc_ras_pending_attribute_page_' . get_current_user_id(), array(
        'term_id' => $term->term_id,
        'term_slug' => $term_slug,
        'term_name' => $term->name,
        'taxonomy' => $taxonomy,
    ), HOUR_IN_SECONDS);
    
    // Pre-fill title using JavaScript
    add_action('admin_footer', function() use ($term) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // For classic editor
            if ($('#title').length && !$('#title').val()) {
                $('#title').val(<?php echo wp_json_encode($term->name); ?>);
            }
            // For block editor - set default title
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                if (!currentTitle) {
                    wp.data.dispatch('core/editor').editPost({ title: <?php echo wp_json_encode($term->name); ?> });
                }
            }
        });
        </script>
        <?php
    });
}
add_action('admin_init', 'wc_ras_prefill_attribute_page_from_url');

/**
 * Save attribute term meta when attribute page is created manually
 *
 * @param int $post_id Post ID
 */
function wc_ras_save_attribute_page_term_link($post_id) {
    // Check if this is an attribute_page
    if (get_post_type($post_id) !== 'attribute_page') {
        return;
    }
    
    // Check for pending attribute page data
    $pending_data = get_transient('wc_ras_pending_attribute_page_' . get_current_user_id());
    if (!$pending_data) {
        return;
    }
    
    // Only save if meta doesn't already exist
    if (!get_post_meta($post_id, '_attribute_term_id', true)) {
        update_post_meta($post_id, '_attribute_term_id', $pending_data['term_id']);
        update_post_meta($post_id, '_attribute_taxonomy', $pending_data['taxonomy']);
        
        // Also set the post slug to match the term slug if not already set
        $post = get_post($post_id);
        if ($post && empty($post->post_name)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_name' => $pending_data['term_slug'],
            ));
        }
    }
    
    // Clean up transient
    delete_transient('wc_ras_pending_attribute_page_' . get_current_user_id());
}
add_action('save_post_attribute_page', 'wc_ras_save_attribute_page_term_link', 5);

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
