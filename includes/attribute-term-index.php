<?php
/**
 * Attribute Term Index
 *
 * Provides a grid overview of all terms for a given WooCommerce product
 * attribute taxonomy. Each term is rendered as a rich card (image, name,
 * description, meta fields) driven by its matching attribute_page CPT.
 *
 * Consumption options:
 * - Page template: assign "Attribute Term Index" to a WP page and set the
 *   taxonomy via the `wc_ras_attribute_term_index_taxonomy` filter or the
 *   `_wc_ras_index_taxonomy` post meta (defaults to pa_opprinnelse).
 * - Shortcode: [wc_ras_attribute_index taxonomy="pa_opprinnelse"]
 * - Direct:    wc_ras_render_attribute_term_index( 'pa_opprinnelse' );
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Resolve which taxonomy the current index should render.
 *
 * Priority: shortcode/args → post meta → filter → default.
 *
 * @param string $requested Raw taxonomy hint (from shortcode/template).
 * @return string Taxonomy slug (e.g. pa_opprinnelse).
 */
function wc_ras_resolve_index_taxonomy($requested = '') {
    $taxonomy = '';

    if (!empty($requested)) {
        $taxonomy = $requested;
    } elseif (is_singular() && ($meta = get_post_meta(get_the_ID(), '_wc_ras_index_taxonomy', true))) {
        $taxonomy = $meta;
    }

    /**
     * Filter the taxonomy used by the attribute term index.
     *
     * @param string $taxonomy Taxonomy slug.
     */
    $taxonomy = apply_filters('wc_ras_attribute_term_index_taxonomy', $taxonomy ?: 'pa_opprinnelse');

    return sanitize_key($taxonomy);
}

/**
 * Get attribute page CPT matched to a term (wrapper around cached helper).
 *
 * @param WP_Term $term Term object.
 * @return WP_Post|null
 */
function wc_ras_get_term_attribute_page($term) {
    if (function_exists('wc_ras_get_cached_attribute_page')) {
        return wc_ras_get_cached_attribute_page($term->slug);
    }
    return get_page_by_path($term->slug, OBJECT, 'attribute_page');
}

/**
 * Collect renderable data for a single attribute term.
 *
 * @param WP_Term $term Term object.
 * @return array {
 *     @type string  $name        Term display name.
 *     @type string  $permalink   Term archive URL.
 *     @type string  $description Term description (unfiltered).
 *     @type string  $image_html  Featured image HTML or empty string.
 *     @type array   $meta        Associative array of meta key => value.
 *     @type int     $page_id     Attribute page post ID (0 if none).
 * }
 */
function wc_ras_get_attribute_term_card_data($term) {
    $page = wc_ras_get_term_attribute_page($term);
    $page_id = $page ? $page->ID : 0;

    $image_html = '';
    if ($page_id && has_post_thumbnail($page_id)) {
        $image_html = get_the_post_thumbnail(
            $page_id,
            'medium_large',
            array(
                'loading' => 'lazy',
                'alt'     => esc_attr($term->name),
            )
        );
    }

    $region = '';
    $smak   = '';
    if ($page_id) {
        $region = (string) get_post_meta($page_id, 'region', true);
        $smak   = (string) get_post_meta($page_id, 'smak', true);
    }

    return array(
        'name'        => $term->name,
        'permalink'   => get_term_link($term),
        'description' => $term->description,
        'image_html'  => $image_html,
        'region'      => $region,
        'smak'        => $smak,
        'page_id'     => $page_id,
    );
}

/**
 * Render the attribute term index grid.
 *
 * @param string $taxonomy Taxonomy slug (e.g. pa_opprinnelse).
 * @return string HTML markup.
 */
function wc_ras_render_attribute_term_index($taxonomy = '') {
    $taxonomy = wc_ras_resolve_index_taxonomy($taxonomy);

    if (!taxonomy_exists($taxonomy)) {
        return '';
    }

    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => apply_filters('wc_ras_attribute_term_index_hide_empty', true, $taxonomy),
        'orderby'    => apply_filters('wc_ras_attribute_term_index_orderby', 'name', $taxonomy),
    ));

    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    ob_start();
    ?>
    <ul class="term-cards alignwide">
        <?php foreach ($terms as $term) :
            $card      = wc_ras_get_attribute_term_card_data($term);
            $has_image = !empty($card['image_html']);
            ?>
            <li>
                <a href="<?php echo esc_url($card['permalink']); ?>">
                    <?php if ($has_image) : ?>
                        <figure>
                            <?php echo $card['image_html']; // Safe: from get_the_post_thumbnail. ?>
                            <?php if (!empty($card['region'])) : ?>
                                <figcaption><?php echo esc_html($card['region']); ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php elseif (!empty($card['region'])) : ?>
                        <p class="region"><?php echo esc_html($card['region']); ?></p>
                    <?php endif; ?>

                    <h2><?php echo esc_html($card['name']); ?></h2>

                    <?php if (!empty($card['smak'])) : ?>
                        <p class="smak"><?php echo esc_html($card['smak']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($card['description'])) : ?>
                        <?php echo wp_kses_post(wpautop(wptexturize($card['description']))); ?>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: [wc_ras_attribute_index taxonomy="pa_opprinnelse"]
 */
function wc_ras_attribute_index_shortcode($atts) {
    $atts = shortcode_atts(array(
        'taxonomy' => '',
    ), $atts, 'wc_ras_attribute_index');

    // Mark scaffold enqueue needed (in case shortcode is used outside the page template).
    add_filter('wc_ras_attribute_index_force_enqueue', '__return_true');

    return wc_ras_render_attribute_term_index($atts['taxonomy']);
}
add_shortcode('wc_ras_attribute_index', 'wc_ras_attribute_index_shortcode');

/**
 * Register the plugin-provided page template so it's selectable on any WP page.
 *
 * @param array $templates Existing templates.
 * @return array
 */
function wc_ras_register_index_page_template($templates) {
    $templates['wc-ras-attribute-term-index'] = __('Attribute Term Index', 'wc-rich-attribute-suite');
    return $templates;
}
add_filter('theme_page_templates', 'wc_ras_register_index_page_template');

/**
 * Serve the plugin template when the index page template is selected.
 *
 * @param string $template Template path.
 * @return string
 */
function wc_ras_filter_index_template($template) {
    if (!is_singular('page')) {
        return $template;
    }

    $assigned = get_page_template_slug(get_queried_object_id());
    if ($assigned !== 'wc-ras-attribute-term-index') {
        return $template;
    }

    // Allow theme override: wc-ras/attribute-term-index.php or attribute-term-index.php
    $theme_template = locate_template(array(
        'wc-ras/attribute-term-index.php',
        'attribute-term-index.php',
    ));
    if ($theme_template) {
        return $theme_template;
    }

    $plugin_template = WC_RAS_PLUGIN_DIR . 'templates/attribute-term-index.php';
    return file_exists($plugin_template) ? $plugin_template : $template;
}
add_filter('template_include', 'wc_ras_filter_index_template', 95);

/**
 * Check whether the current request renders an attribute term index.
 *
 * @return bool
 */
function wc_ras_is_attribute_term_index() {
    if (!is_singular('page')) {
        return (bool) apply_filters('wc_ras_attribute_index_force_enqueue', false);
    }
    $assigned = get_page_template_slug(get_queried_object_id());
    if ($assigned === 'wc-ras-attribute-term-index') {
        return true;
    }
    return (bool) apply_filters('wc_ras_attribute_index_force_enqueue', false);
}

/**
 * Enqueue the scaffold stylesheet only when the index is rendered.
 */
function wc_ras_enqueue_attribute_index_assets() {
    if (!wc_ras_is_attribute_term_index()) {
        return;
    }

    wp_enqueue_style(
        'wc-ras-attribute-term-index',
        WC_RAS_PLUGIN_URL . 'assets/css/attribute-term-index.css',
        array(),
        WC_RAS_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wc_ras_enqueue_attribute_index_assets');

/**
 * Expose a body class so theme CSS can target the index page reliably.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function wc_ras_body_class_attribute_index($classes) {
    if (wc_ras_is_attribute_term_index()) {
        $classes[] = 'wc-ras-has-attribute-index';
    }
    return $classes;
}
add_filter('body_class', 'wc_ras_body_class_attribute_index');
