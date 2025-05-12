<?php
/**
 * Admin Hooks
 *
 * Handles admin UI integration for attribute pages.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Add a link from attribute term edit screen to its content page
 *
 * @param WP_Term $term The term being edited
 */
function wc_ras_add_term_edit_link($term) {
    // Only proceed if this is a WooCommerce product attribute taxonomy
    if (strpos($term->taxonomy, 'pa_') !== 0) {
        return;
    }

    // Look for matching attribute page
    $linked_page = get_page_by_path($term->slug, OBJECT, 'attribute_page');
    
    if ($linked_page) {
        $edit_url = get_edit_post_link($linked_page->ID);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label><?php _e('Rich Content', 'wc-rich-attribute-suite'); ?></label>
            </th>
            <td>
                <a href="<?php echo esc_url($edit_url); ?>" class="button">
                    <?php _e('Edit Rich Content', 'wc-rich-attribute-suite'); ?>
                </a>
                <p class="description">
                    <?php _e('Edit the rich content page for this attribute term using the block editor.', 'wc-rich-attribute-suite'); ?>
                </p>
            </td>
        </tr>
        <?php
    } else {
        // No page exists yet, offer to create one
        $create_url = admin_url('post-new.php?post_type=attribute_page&attribute_term=' . $term->slug . '&attribute_taxonomy=' . $term->taxonomy);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label><?php _e('Rich Content', 'wc-rich-attribute-suite'); ?></label>
            </th>
            <td>
                <a href="<?php echo esc_url($create_url); ?>" class="button">
                    <?php _e('Create Rich Content Page', 'wc-rich-attribute-suite'); ?>
                </a>
                <p class="description">
                    <?php _e('No rich content page exists for this attribute term. Click to create one.', 'wc-rich-attribute-suite'); ?>
                </p>
            </td>
        </tr>
        <?php
    }
}

// Add the link to all product attribute taxonomy term edit screens
function wc_ras_register_term_edit_hooks() {
    $attribute_taxonomies = wc_get_attribute_taxonomies();
    
    if (!empty($attribute_taxonomies)) {
        foreach ($attribute_taxonomies as $taxonomy) {
            $taxonomy_name = wc_attribute_taxonomy_name($taxonomy->attribute_name);
            add_action("{$taxonomy_name}_edit_form_fields", 'wc_ras_add_term_edit_link', 10, 1);
        }
    }
}
add_action('admin_init', 'wc_ras_register_term_edit_hooks');

/**
 * Add custom columns to the attribute_page post type admin list
 *
 * @param array $columns Existing columns
 * @return array Modified columns
 */
function wc_ras_add_attribute_page_columns($columns) {
    $new_columns = array();
    
    // Insert columns after title but before date
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['attribute_taxonomy'] = __('Attribute Type', 'wc-rich-attribute-suite');
            $new_columns['attribute_term'] = __('Attribute Term', 'wc-rich-attribute-suite');
            $new_columns['region'] = __('Region', 'wc-rich-attribute-suite');
            $new_columns['smak'] = __('Smak', 'wc-rich-attribute-suite');
        }
    }
    
    return $new_columns;
}
add_filter('manage_attribute_page_posts_columns', 'wc_ras_add_attribute_page_columns');

/**
 * Populate custom columns in the attribute_page post type admin list
 *
 * @param string $column Column name
 * @param int    $post_id Post ID
 */
function wc_ras_populate_attribute_page_columns($column, $post_id) {
    switch ($column) {
        case 'attribute_taxonomy':
            $taxonomy = get_post_meta($post_id, '_attribute_taxonomy', true);
            if ($taxonomy) {
                $taxonomy_label = str_replace('pa_', '', $taxonomy);
                echo esc_html(ucfirst($taxonomy_label));
            } else {
                echo '—';
            }
            break;
            
        case 'attribute_term':
            $term_id = get_post_meta($post_id, '_attribute_term_id', true);
            $taxonomy = get_post_meta($post_id, '_attribute_taxonomy', true);
            
            if ($term_id && $taxonomy) {
                $term = get_term($term_id, $taxonomy);
                if (!is_wp_error($term) && $term) {
                    $term_url = get_edit_term_link($term_id, $taxonomy);
                    echo '<a href="' . esc_url($term_url) . '">' . esc_html($term->name) . '</a>';
                } else {
                    echo esc_html(get_the_title($post_id));
                }
            } else {
                // Fallback to post slug if no term info stored
                $post = get_post($post_id);
                echo esc_html($post->post_name);
            }
            break;
            
        case 'region':
        case 'smak':
            // Display meta value
            $value = get_post_meta($post_id, $column, true);
            echo esc_html($value ? $value : '—');
            break;
    }
}
add_action('manage_attribute_page_posts_custom_column', 'wc_ras_populate_attribute_page_columns', 10, 2);

/**
 * Add meta box for attribute page fields
 */
function wc_ras_add_attribute_page_meta_box() {
    add_meta_box(
        'wc_ras_attribute_meta',
        __('Attribute Properties', 'wc-rich-attribute-suite'),
        'wc_ras_render_attribute_page_meta_box',
        'attribute_page',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'wc_ras_add_attribute_page_meta_box');

/**
 * Render meta box for attribute page fields
 *
 * @param WP_Post $post Current post object
 */
function wc_ras_render_attribute_page_meta_box($post) {
    wp_nonce_field('wc_ras_save_attribute_meta', 'wc_ras_attribute_meta_nonce');
    
    $region = get_post_meta($post->ID, 'region', true);
    $smak = get_post_meta($post->ID, 'smak', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="wc_ras_region"><?php _e('Region', 'wc-rich-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="text" id="wc_ras_region" name="wc_ras_region" value="<?php echo esc_attr($region); ?>" class="regular-text" />
                <p class="description"><?php _e('The region for this attribute (e.g., geographical location)', 'wc-rich-attribute-suite'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="wc_ras_smak"><?php _e('Smak', 'wc-rich-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="text" id="wc_ras_smak" name="wc_ras_smak" value="<?php echo esc_attr($smak); ?>" class="regular-text" />
                <p class="description"><?php _e('The taste profile for this attribute', 'wc-rich-attribute-suite'); ?></p>
            </td>
        </tr>
    </table>
    <?php
    
    /**
     * Hook for adding additional fields to the attribute page meta box
     * 
     * @param WP_Post $post Current post object
     */
    do_action('wc_ras_attribute_page_meta_box_fields', $post);
}

/**
 * Save meta box data
 *
 * @param int $post_id Post ID
 */
function wc_ras_save_attribute_meta($post_id) {
    // Check if our nonce is set and verify it
    if (!isset($_POST['wc_ras_attribute_meta_nonce']) || !wp_verify_nonce($_POST['wc_ras_attribute_meta_nonce'], 'wc_ras_save_attribute_meta')) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save region
    if (isset($_POST['wc_ras_region'])) {
        update_post_meta($post_id, 'region', sanitize_text_field($_POST['wc_ras_region']));
    }
    
    // Save smak
    if (isset($_POST['wc_ras_smak'])) {
        update_post_meta($post_id, 'smak', sanitize_text_field($_POST['wc_ras_smak']));
    }
    
    /**
     * Hook for saving additional fields from the attribute page meta box
     * 
     * @param int $post_id Post ID
     */
    do_action('wc_ras_save_attribute_page_meta', $post_id);
}
add_action('save_post_attribute_page', 'wc_ras_save_attribute_meta');
