<?php
/**
 * Origin module — starter block pattern for CPT single pages.
 *
 * Gives editors a 3-column scaffold to flesh out the narrative sections
 * below the strukturert header (produsentene / stedet / håndverket).
 * Editors can freely edit, add, or remove blocks — the pattern is a
 * starting point, not a rigid structure.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Register block patterns and category on init.
 */
function wc_ras_origin_register_block_patterns() {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('wc-ras', array(
            'label' => __('Rich Attribute Suite', 'wc-rich-attribute-suite'),
        ));
    }

    register_block_pattern('wc-ras/origin-starter', array(
        'title'       => __('Opprinnelse — 3-kolonne-start', 'wc-rich-attribute-suite'),
        'description' => __('Startmal for opprinnelsesside: produsenter, sted, håndverk. Rediger fritt.', 'wc-rich-attribute-suite'),
        'categories'  => array('wc-ras'),
        'keywords'    => array('opprinnelse', 'origin', 'columns'),
        'content'     => wc_ras_origin_starter_pattern_markup(),
    ));
}
add_action('init', 'wc_ras_origin_register_block_patterns', 20);

/**
 * Return the block markup for the origin-starter pattern.
 *
 * Stored as a function so we can keep the markup readable and still use
 * translated strings at registration time.
 *
 * @return string
 */
function wc_ras_origin_starter_pattern_markup() {
    $h_producers = esc_html__('Produsentene', 'wc-rich-attribute-suite');
    $h_place     = esc_html__('Stedet', 'wc-rich-attribute-suite');
    $h_craft     = esc_html__('Håndverket', 'wc-rich-attribute-suite');

    $p_producers = esc_html__('Hvem dyrker kakaoen? Kooperativet, familien, den sosiale virksomheten. Skriv om menneskene bak bønnene.', 'wc-rich-attribute-suite');
    $p_place     = esc_html__('Hvor ligger opprinnelsen? Klima, jordsmonn, landskap, høyde. Det stedet smaken kommer fra.', 'wc-rich-attribute-suite');
    $p_craft     = esc_html__('Hvordan behandles bønnene? Fermentering, tørking, lokal tradisjon. Det som gjør akkurat denne opprinnelsen distinkt.', 'wc-rich-attribute-suite');

    return '<!-- wp:columns -->
<div class="wp-block-columns">
    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading {"level":2} --><h2>' . $h_producers . '</h2><!-- /wp:heading -->
        <!-- wp:paragraph --><p>' . $p_producers . '</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading {"level":2} --><h2>' . $h_place . '</h2><!-- /wp:heading -->
        <!-- wp:paragraph --><p>' . $p_place . '</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
        <!-- wp:heading {"level":2} --><h2>' . $h_craft . '</h2><!-- /wp:heading -->
        <!-- wp:paragraph --><p>' . $p_craft . '</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
</div>
<!-- /wp:columns -->';
}
