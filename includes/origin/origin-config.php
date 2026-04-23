<?php
/**
 * Origin module — central configuration.
 *
 * All definitions that drive the origin meta fields, admin UI, and rendering
 * layer live here. Filters exposed so downstream code can add/remove axes,
 * producer types, fermentation types, and country flag mappings without
 * touching storage or the database.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Taste profile axes.
 *
 * Source of truth for the 8-axis taste radar. Keys are stable JSON keys
 * (English, lower_snake_case). Values are translatable labels shown in admin
 * inputs and on rendered radars. Adding/removing an axis requires NO database
 * migration: render code reads only the axes defined here at read time.
 *
 * @return array<string,string> Map of axis_key => translated label.
 */
function wc_ras_taste_axes() {
    $axes = array(
        'acidity'    => __('Syre', 'wc-rich-attribute-suite'),
        'sweetness'  => __('Sødme', 'wc-rich-attribute-suite'),
        'bitterness' => __('Bitter', 'wc-rich-attribute-suite'),
        'body'       => __('Kropp', 'wc-rich-attribute-suite'),
        'fruit'      => __('Frukt', 'wc-rich-attribute-suite'),
        'floral'     => __('Blomst', 'wc-rich-attribute-suite'),
        'earth'      => __('Jord', 'wc-rich-attribute-suite'),
        'spice'      => __('Krydder', 'wc-rich-attribute-suite'),
    );

    return apply_filters('wc_ras_taste_axes', $axes);
}

/**
 * Producer type enum.
 *
 * @return array<string,string> Map of enum_value => translated label.
 */
function wc_ras_producer_types() {
    $types = array(
        'single_estate'     => __('Single estate', 'wc-rich-attribute-suite'),
        'family_farm'       => __('Family farm', 'wc-rich-attribute-suite'),
        'cooperative'       => __('Cooperative', 'wc-rich-attribute-suite'),
        'social_enterprise' => __('Social enterprise', 'wc-rich-attribute-suite'),
        'smallholders'      => __('Smallholders', 'wc-rich-attribute-suite'),
    );

    return apply_filters('wc_ras_producer_types', $types);
}

/**
 * Fermentation type enum.
 *
 * @return array<string,string> Map of enum_value => translated label.
 */
function wc_ras_fermentation_types() {
    $types = array(
        'centralized'   => __('Sentralisert', 'wc-rich-attribute-suite'),
        'decentralized' => __('Desentralisert', 'wc-rich-attribute-suite'),
        'mixed'         => __('Blandet', 'wc-rich-attribute-suite'),
    );

    return apply_filters('wc_ras_fermentation_types', $types);
}

/**
 * Country flag mapping: origin_country term slug => flag SVG/image URL.
 *
 * Default is empty. Site owners register mappings via the filter if they
 * want to override the file-based resolution in
 * wc_ras_country_flag_url() (origin-render.php). Slug convention:
 * ISO-ish lowercase names (peru, tanzania, nicaragua, …).
 *
 * @return array<string,string>
 */
function wc_ras_country_flag_map() {
    return apply_filters('wc_ras_country_flag_map', array());
}
