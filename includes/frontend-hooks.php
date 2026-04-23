<?php
/**
 * Frontend Hooks
 *
 * Owns:
 *   - Rewrite coordination for WooCommerce attribute taxonomies (kept so
 *     /product-attribute/opprinnelse/{slug}/ keeps resolving for product
 *     filtering — this is Woo's URL, not our CPT URL).
 *   - Rewrite-flush version gate.
 *   - Cached attribute_page lookup helper used by variation preload and
 *     everywhere we resolve a term slug to its rich-content CPT post.
 *   - Variation preload: injects the full `wc_ras_origin` struct into
 *     `woocommerce_available_variation` so blurb/modal can render without
 *     extra round-trips.
 *
 * Does NOT own (removed in 1.3.0):
 *   - template_include override for taxonomy archives — superseded by the
 *     CPT /opprinnelser/{slug}/ single template.
 *   - woocommerce_taxonomy_archive_description output — ditto.
 *   - variation-display.js enqueue — lives in variation-improvements.php.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Enable public archives for WooCommerce product attribute taxonomies.
 *
 * Ensures get_term_link() returns proper permalinks so Woo-style attribute
 * filtering URLs keep working independently of our CPT archive.
 */
function wc_ras_enable_attribute_archives() {
    $attribute_taxonomies = wc_get_attribute_taxonomies();

    if (empty($attribute_taxonomies)) {
        return;
    }

    foreach ($attribute_taxonomies as $tax) {
        $taxonomy_name = wc_attribute_taxonomy_name($tax->attribute_name);
        add_filter("woocommerce_taxonomy_args_{$taxonomy_name}", 'wc_ras_filter_attribute_taxonomy_args', 10, 1);
    }
}
// Run early, before WooCommerce registers taxonomies
add_action('init', 'wc_ras_enable_attribute_archives', 1);

/**
 * Filter attribute taxonomy args to enable public archives.
 *
 * @param array $args Taxonomy registration args.
 * @return array Modified args.
 */
function wc_ras_filter_attribute_taxonomy_args($args) {
    $args['public'] = true;
    $args['query_var'] = true;

    if (empty($args['rewrite']) || $args['rewrite'] === false) {
        $current_filter = current_filter();
        $taxonomy_name = str_replace('woocommerce_taxonomy_args_', '', $current_filter);
        $attribute_name = str_replace('pa_', '', $taxonomy_name);

        $permalinks = wc_get_permalink_structure();
        $base_slug = !empty($permalinks['attribute_rewrite_slug']) ? $permalinks['attribute_rewrite_slug'] : '';

        $args['rewrite'] = array(
            'slug'         => trailingslashit($base_slug) . urldecode(sanitize_title($attribute_name)),
            'with_front'   => false,
            'hierarchical' => true,
        );
    }

    return $args;
}

/**
 * Flush rewrite rules when the plugin is activated or settings change.
 */
function wc_ras_maybe_flush_rewrite_rules() {
    if (get_option('wc_ras_flush_rewrite_rules')) {
        flush_rewrite_rules();
        delete_option('wc_ras_flush_rewrite_rules');
    }
}
add_action('init', 'wc_ras_maybe_flush_rewrite_rules', 99);

/**
 * Bump rewrite-flush on version change so new CPT/taxonomy rewrite rules
 * take effect without asking the admin to visit Permalinks manually.
 */
function wc_ras_check_rewrite_rules_version() {
    $current_version = get_option('wc_ras_rewrite_version', '0');

    if (version_compare($current_version, '1.3.0', '<')) {
        update_option('wc_ras_flush_rewrite_rules', true);
        update_option('wc_ras_rewrite_version', WC_RAS_VERSION);
    }
}
add_action('init', 'wc_ras_check_rewrite_rules_version', 98);

/**
 * Get cached attribute_page by term slug.
 *
 * Cache group `wc_ras_attribute_page` — kept in sync with the legacy
 * `wc_ras_attribute_pages` group by wc_ras_invalidate_attribute_page_cache.
 *
 * @param string $slug The attribute term slug.
 * @return WP_Post|null The attribute page or null if not found.
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
 * Build the full wc_ras_origin struct for an attribute_page post.
 *
 * Single source of truth for the payload shape consumed by blurb, modal,
 * archive cards, and single-page headers. Keeps null semantics explicit so
 * the render layer can apply "hide whole UI if minimum missing" uniformly.
 *
 * Expected example output:
 *   array(
 *     'name'               => 'Qori Inti',
 *     'slug'               => 'peru-qoriinti',
 *     'excerpt'             => 'Solens Bønn(e)',
 *     'permalink'           => 'https://site/opprinnelser/peru-qoriinti/',
 *     'country'             => 'Peru',
 *     'country_slug'        => 'peru',
 *     'country_flag_url'    => 'https://site/wp-content/plugins/.../flags/peru.svg' | null,
 *     'region'              => 'Cusco, Quillabamba',
 *     'variety'             => 'Chuncho',
 *     'altitude'            => array('min' => 1200, 'max' => 1450, 'unit' => 'm') | null,
 *     'taste_notes'         => 'Mango, krem, macadamia',
 *     'producer_type'       => 'cooperative' | null,
 *     'producer_count'      => 45 | null,
 *     'fermentation_type'   => 'centralized' | null,
 *     'fermentation_days'   => 6 | null,
 *     'fermentation_method' => '3-tier cascade...' | null,
 *     'drying_method'       => 'Sun-dried on raised beds' | null,
 *     'taste_profile'       => array('acidity' => 6, 'sweetness' => null, ...),
 *     'certifications'      => array(array('slug' => 'organic', 'name' => 'Organic', 'icon_url' => '...'), ...),
 *     'featured_image_url'  => 'https://...' | null,
 *     'has_rich_page'       => true,
 *   )
 *
 * @param WP_Post|null $page Attribute page post.
 * @return array|null Origin struct, or null if $page is null.
 */
function wc_ras_build_origin_struct($page) {
    if (!$page instanceof WP_Post) {
        return null;
    }

    $page_id = $page->ID;

    // Country via origin_country taxonomy
    $country_name = null;
    $country_slug = null;
    $country_terms = get_the_terms($page_id, 'origin_country');
    if ($country_terms && !is_wp_error($country_terms)) {
        $first = reset($country_terms);
        $country_name = $first->name;
        $country_slug = $first->slug;
    }

    // Flag URL (null if no SVG present)
    $country_flag_url = $country_slug && function_exists('wc_ras_country_flag_url')
        ? wc_ras_country_flag_url($country_slug)
        : null;
    if (empty($country_flag_url)) {
        $country_flag_url = null;
    }

    // Certifications → array of lightweight records
    $certifications = array();
    $cert_terms = get_the_terms($page_id, 'certification');
    if ($cert_terms && !is_wp_error($cert_terms)) {
        foreach ($cert_terms as $term) {
            $icon_id = (int) get_term_meta($term->term_id, 'icon_svg_id', true);
            $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : null;
            if (!$icon_url) {
                $icon_url = null;
            }
            $certifications[] = array(
                'slug'     => $term->slug,
                'name'     => $term->name,
                'icon_url' => $icon_url,
            );
        }
    }

    // Featured image
    $thumb_id = get_post_thumbnail_id($page_id);
    $featured_image_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'large') : null;
    if (!$featured_image_url) {
        $featured_image_url = null;
    }

    // Altitude — return null when neither min nor max is set
    $altitude = get_post_meta($page_id, 'altitude', true);
    if (!is_array($altitude) || (empty($altitude['min']) && empty($altitude['max']))) {
        $altitude = null;
    }

    // Helper to normalise empty string → null
    $nullable = function ($value) {
        if ($value === '' || $value === false || $value === null) {
            return null;
        }
        return $value;
    };

    $producer_count   = get_post_meta($page_id, 'producer_count', true);
    $fermentation_days = get_post_meta($page_id, 'fermentation_days', true);

    $taste_profile = get_post_meta($page_id, 'taste_profile', true);
    if (!is_array($taste_profile)) {
        $taste_profile = array();
    }

    $struct = array(
        'name'                => $page->post_title,
        'slug'                => $page->post_name,
        'excerpt'             => get_the_excerpt($page),
        'permalink'           => get_permalink($page),

        'country'             => $country_name,
        'country_slug'        => $country_slug,
        'country_flag_url'    => $country_flag_url,
        'region'              => $nullable(get_post_meta($page_id, 'region', true)),
        'variety'             => $nullable(get_post_meta($page_id, 'variety', true)),
        'altitude'            => $altitude,
        'taste_notes'         => $nullable(get_post_meta($page_id, 'smak', true)),

        'producer_type'       => $nullable(get_post_meta($page_id, 'producer_type', true)),
        'producer_count'      => ($producer_count === '' || $producer_count === false) ? null : (int) $producer_count,

        'fermentation_type'   => $nullable(get_post_meta($page_id, 'fermentation_type', true)),
        'fermentation_days'   => ($fermentation_days === '' || $fermentation_days === false) ? null : (int) $fermentation_days,
        'fermentation_method' => $nullable(get_post_meta($page_id, 'fermentation_method', true)),
        'drying_method'       => $nullable(get_post_meta($page_id, 'drying_method', true)),

        'taste_profile'       => $taste_profile,
        'certifications'      => $certifications,
        'featured_image_url'  => $featured_image_url,

        'has_rich_page'       => true,
    );

    /**
     * Filter the origin struct before it goes out to the variation data
     * or template helpers. Use for adding plugin-specific fields.
     *
     * @param array   $struct The built origin struct.
     * @param WP_Post $page   The source attribute_page post.
     */
    return apply_filters('wc_ras_origin_struct', $struct, $page);
}

/**
 * Resolve the attribute_page CPT for a given variation's opprinnelse term.
 *
 * @param int    $variation_id Variation ID.
 * @param string $attribute    Attribute taxonomy (default: pa_opprinnelse).
 * @return WP_Post|null Matching attribute_page post, or null.
 */
function wc_ras_get_attribute_page_for_variation($variation_id, $attribute = 'pa_opprinnelse') {
    $product = wc_get_product($variation_id);
    if (!$product || !$product->is_type('variation')) {
        return null;
    }

    $attributes = $product->get_attributes();
    if (!isset($attributes[$attribute])) {
        return null;
    }

    $term = get_term_by('slug', $attributes[$attribute], $attribute);
    if (!$term || is_wp_error($term)) {
        return null;
    }

    return wc_ras_get_cached_attribute_page($term->slug);
}

/**
 * Add the wc_ras_origin struct to variation data at priority 20.
 *
 * @param array                $data      Variation data.
 * @param WC_Product_Variable  $product   Product object.
 * @param WC_Product_Variation $variation Variation object.
 * @return array
 */
function wc_ras_add_origin_to_variation($data, $product, $variation) {
    $page = wc_ras_get_attribute_page_for_variation($variation->get_id());
    if (!$page) {
        return $data;
    }

    $struct = wc_ras_build_origin_struct($page);
    if ($struct) {
        $data['wc_ras_origin'] = $struct;
    }

    return $data;
}
add_filter('woocommerce_available_variation', 'wc_ras_add_origin_to_variation', 20, 3);
