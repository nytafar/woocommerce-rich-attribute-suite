<?php
/**
 * Origin module — custom taxonomies.
 *
 * Registers origin_country and certification taxonomies on the
 * attribute_page CPT. Both provide canonical name governance and drive
 * downstream features (flag mapping, compliance badges).
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Register origin_country and certification taxonomies.
 *
 * Hooked on init at priority 11 — after the CPT is registered at priority 10
 * (cpt-attribute-page.php), and before attribute-term sync hooks at priority 20.
 */
function wc_ras_register_origin_taxonomies() {
    register_taxonomy('origin_country', array('attribute_page'), array(
        'labels' => array(
            'name'              => _x('Countries', 'Taxonomy general name', 'wc-rich-attribute-suite'),
            'singular_name'     => _x('Country', 'Taxonomy singular name', 'wc-rich-attribute-suite'),
            'menu_name'         => __('Countries', 'wc-rich-attribute-suite'),
            'all_items'         => __('All countries', 'wc-rich-attribute-suite'),
            'edit_item'         => __('Edit country', 'wc-rich-attribute-suite'),
            'view_item'         => __('View country', 'wc-rich-attribute-suite'),
            'update_item'       => __('Update country', 'wc-rich-attribute-suite'),
            'add_new_item'      => __('Add new country', 'wc-rich-attribute-suite'),
            'new_item_name'     => __('New country name', 'wc-rich-attribute-suite'),
            'search_items'      => __('Search countries', 'wc-rich-attribute-suite'),
            'not_found'         => __('No countries found.', 'wc-rich-attribute-suite'),
        ),
        'public'             => true,
        'publicly_queryable' => true,
        'hierarchical'       => false,
        'show_ui'            => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array(
            'slug'       => 'opprinnelser/land',
            'with_front' => false,
        ),
    ));

    register_taxonomy('certification', array('attribute_page'), array(
        'labels' => array(
            'name'              => _x('Certifications', 'Taxonomy general name', 'wc-rich-attribute-suite'),
            'singular_name'     => _x('Certification', 'Taxonomy singular name', 'wc-rich-attribute-suite'),
            'menu_name'         => __('Certifications', 'wc-rich-attribute-suite'),
            'all_items'         => __('All certifications', 'wc-rich-attribute-suite'),
            'edit_item'         => __('Edit certification', 'wc-rich-attribute-suite'),
            'view_item'         => __('View certification', 'wc-rich-attribute-suite'),
            'update_item'       => __('Update certification', 'wc-rich-attribute-suite'),
            'add_new_item'      => __('Add new certification', 'wc-rich-attribute-suite'),
            'new_item_name'     => __('New certification name', 'wc-rich-attribute-suite'),
            'search_items'      => __('Search certifications', 'wc-rich-attribute-suite'),
            'not_found'         => __('No certifications found.', 'wc-rich-attribute-suite'),
        ),
        'public'             => true,
        'publicly_queryable' => true,
        'hierarchical'       => false,
        'show_ui'            => true,
        'show_admin_column'  => true,
        'show_in_rest'       => true,
        'query_var'          => true,
        'rewrite'            => array(
            'slug'       => 'opprinnelser/sertifiseringer',
            'with_front' => false,
        ),
    ));

    register_term_meta('certification', 'icon_svg_id', array(
        'type'              => 'integer',
        'description'       => __('Attachment ID of the certification icon SVG.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
    ));

    register_term_meta('certification', 'full_name', array(
        'type'              => 'string',
        'description'       => __('Full/official certification name.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    register_term_meta('certification', 'external_url', array(
        'type'              => 'string',
        'description'       => __('External URL (certification body or programme page).', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'esc_url_raw',
    ));
}
add_action('init', 'wc_ras_register_origin_taxonomies', 11);

/**
 * Invalidate the cached attribute_page on term changes so wc_ras_origin
 * preload reflects updated country/certification assignments immediately.
 *
 * @param int    $object_id Post ID whose terms changed.
 * @param array  $terms     Term IDs (unused).
 * @param array  $tt_ids    Term taxonomy IDs (unused).
 * @param string $taxonomy  Taxonomy name.
 */
function wc_ras_invalidate_origin_terms_cache($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy !== 'origin_country' && $taxonomy !== 'certification') {
        return;
    }
    $post = get_post($object_id);
    if (!$post || $post->post_type !== 'attribute_page') {
        return;
    }
    if (function_exists('wc_ras_invalidate_attribute_page_cache')) {
        wc_ras_invalidate_attribute_page_cache($object_id);
    }
}
add_action('set_object_terms', 'wc_ras_invalidate_origin_terms_cache', 10, 4);
