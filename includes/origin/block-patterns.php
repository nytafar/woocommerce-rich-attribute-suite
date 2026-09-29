<?php
/**
 * Origin module — starter block pattern for CPT single pages.
 *
 * Gives editors a 3-column scaffold to flesh out the narrative sections
 * below the strukturert header (produsentene / stedet / håndverket).
 * Editors can freely edit, add, or remove blocks — the pattern is a
 * starting point, not a rigid structure.
 *
 * @package Kaupang\AttributeSuite
 */

defined('ABSPATH') || exit;

/**
 * Register block patterns and category on init.
 */
function kaupang_attribute_suite_origin_register_block_patterns() {
    if (!function_exists('register_block_pattern')) {
        return;
    }

    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('kaupang-attribute-suite', array(
            'label' => __('Kaupang Attribute Suite', 'kaupang-attribute-suite'),
        ));
    }

    register_block_pattern('kaupang-attribute-suite/origin-starter', array(
        'title'       => __('Opprinnelse — 3-kolonne-start', 'kaupang-attribute-suite'),
        'description' => __('Startmal for opprinnelsesside: produsenter, sted, håndverk. Rediger fritt.', 'kaupang-attribute-suite'),
        'categories'  => array('kaupang-attribute-suite'),
        'keywords'    => array('opprinnelse', 'origin', 'columns'),
        'content'     => kaupang_attribute_suite_origin_starter_pattern_markup(),
    ));
}
add_action('init', 'kaupang_attribute_suite_origin_register_block_patterns', 20);

/**
 * Return the block markup for the origin-starter pattern.
 *
 * Stored as a function so we can keep the markup readable and still use
 * translated strings at registration time.
 *
 * @return string
 */
function kaupang_attribute_suite_origin_starter_pattern_markup() {
    $h_producers = esc_html__('Produsentene', 'kaupang-attribute-suite');
    $h_place     = esc_html__('Stedet', 'kaupang-attribute-suite');
    $h_craft     = esc_html__('Håndverket', 'kaupang-attribute-suite');

    $p_producers = esc_html__('Hvem dyrker kakaoen? Kooperativet, familien, den sosiale virksomheten. Skriv om menneskene bak bønnene.', 'kaupang-attribute-suite');
    $p_place     = esc_html__('Hvor ligger opprinnelsen? Klima, jordsmonn, landskap, høyde. Det stedet smaken kommer fra.', 'kaupang-attribute-suite');
    $p_craft     = esc_html__('Hvordan behandles bønnene? Fermentering, tørking, lokal tradisjon. Det som gjør akkurat denne opprinnelsen distinkt.', 'kaupang-attribute-suite');

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
