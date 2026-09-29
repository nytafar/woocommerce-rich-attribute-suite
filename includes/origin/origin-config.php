<?php
/**
 * Origin module — central configuration.
 *
 * All definitions that drive the origin meta fields, admin UI, and rendering
 * layer live here. Filters exposed so downstream code can add/remove axes,
 * producer types, fermentation types, and country flag mappings without
 * touching storage or the database.
 *
 * @package Kaupang\AttributeSuite
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
function kaupang_attribute_suite_taste_axes() {
    $axes = array(
        'acidity'    => __('Syre', 'kaupang-attribute-suite'),
        'sweetness'  => __('Sødme', 'kaupang-attribute-suite'),
        'bitterness' => __('Bitter', 'kaupang-attribute-suite'),
        'body'       => __('Kropp', 'kaupang-attribute-suite'),
        'fruit'      => __('Frukt', 'kaupang-attribute-suite'),
        'floral'     => __('Blomst', 'kaupang-attribute-suite'),
        'earth'      => __('Jord', 'kaupang-attribute-suite'),
        'spice'      => __('Krydder', 'kaupang-attribute-suite'),
    );

    return apply_filters('kaupang/attribute-suite/taste_axes', $axes);
}

/**
 * Producer type enum.
 *
 * @return array<string,string> Map of enum_value => translated label.
 */
function kaupang_attribute_suite_producer_types() {
    $types = array(
        'single_estate'     => __('Single estate', 'kaupang-attribute-suite'),
        'family_farm'       => __('Family farm', 'kaupang-attribute-suite'),
        'cooperative'       => __('Cooperative', 'kaupang-attribute-suite'),
        'social_enterprise' => __('Social enterprise', 'kaupang-attribute-suite'),
        'smallholders'      => __('Smallholders', 'kaupang-attribute-suite'),
    );

    return apply_filters('kaupang/attribute-suite/producer_types', $types);
}

/**
 * Fermentation type enum.
 *
 * @return array<string,string> Map of enum_value => translated label.
 */
function kaupang_attribute_suite_fermentation_types() {
    $types = array(
        'centralized'   => __('Sentralisert', 'kaupang-attribute-suite'),
        'decentralized' => __('Desentralisert', 'kaupang-attribute-suite'),
        'mixed'         => __('Blandet', 'kaupang-attribute-suite'),
    );

    return apply_filters('kaupang/attribute-suite/fermentation_types', $types);
}

/**
 * Country flag mapping: origin_country term slug => flag SVG/image URL.
 *
 * Default is empty. Site owners register mappings via the filter if they
 * want to override the file-based resolution in
 * kaupang_attribute_suite_country_flag_url() (origin-render.php). Slug convention:
 * ISO-ish lowercase names (peru, tanzania, nicaragua, …).
 *
 * @return array<string,string>
 */
function kaupang_attribute_suite_country_flag_map() {
    return apply_filters('kaupang/attribute-suite/country_flag_map', array());
}
