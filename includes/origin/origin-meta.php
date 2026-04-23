<?php
/**
 * Origin module — post meta registration.
 *
 * Registers the origin-specific meta fields on the attribute_page CPT via
 * the existing wc_ras_register_attribute_page_meta_fields action hook fired
 * from cpt-attribute-page.php.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/**
 * Register origin post meta fields.
 *
 * @param string $post_type Post type slug (passed by the hook).
 */
function wc_ras_origin_register_meta($post_type) {
    if ($post_type !== 'attribute_page') {
        return;
    }

    // Cultivation
    register_post_meta('attribute_page', 'variety', array(
        'type'              => 'string',
        'description'       => __('Cacao variety (e.g., Chuncho, Criollo).', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // People & Producers
    register_post_meta('attribute_page', 'producer_type', array(
        'type'              => 'string',
        'description'       => __('Producer organization type.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'wc_ras_sanitize_producer_type',
    ));

    register_post_meta('attribute_page', 'producer_count', array(
        'type'              => 'integer',
        'description'       => __('Number of producers (nullable).', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'wc_ras_sanitize_nullable_int',
    ));

    // Post-harvest
    register_post_meta('attribute_page', 'fermentation_type', array(
        'type'              => 'string',
        'description'       => __('Fermentation organization type.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'wc_ras_sanitize_fermentation_type',
    ));

    register_post_meta('attribute_page', 'fermentation_days', array(
        'type'              => 'integer',
        'description'       => __('Fermentation duration in days (nullable).', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'wc_ras_sanitize_nullable_int',
    ));

    register_post_meta('attribute_page', 'fermentation_method', array(
        'type'              => 'string',
        'description'       => __('Free-text fermentation method description.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    register_post_meta('attribute_page', 'drying_method', array(
        'type'              => 'string',
        'description'       => __('Free-text drying method description.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));

    // Flavour — taste_profile stored as object keyed by axis slug
    register_post_meta('attribute_page', 'taste_profile', array(
        'type'              => 'object',
        'description'       => __('8-axis taste profile, values 0–10 per axis.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => array(
            'schema' => array(
                'type'                 => 'object',
                'additionalProperties' => array(
                    'type'    => array('integer', 'null'),
                    'minimum' => 0,
                    'maximum' => 10,
                ),
            ),
        ),
        'sanitize_callback' => 'wc_ras_sanitize_taste_profile',
    ));

    // Altitude — stored for future direct-trade partners, not frontend-rendered
    register_post_meta('attribute_page', 'altitude', array(
        'type'              => 'object',
        'description'       => __('Altitude range {min, max, unit}.', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => array(
            'schema' => array(
                'type'       => 'object',
                'properties' => array(
                    'min'  => array('type' => array('integer', 'null')),
                    'max'  => array('type' => array('integer', 'null')),
                    'unit' => array('type' => 'string'),
                ),
            ),
        ),
        'sanitize_callback' => 'wc_ras_sanitize_altitude',
    ));

    // References
    register_post_meta('attribute_page', 'id_archivo', array(
        'type'              => 'string',
        'description'       => __('External archive identifier (e.g., Silva GT-047).', 'wc-rich-attribute-suite'),
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
    ));
}
add_action('wc_ras_register_attribute_page_meta_fields', 'wc_ras_origin_register_meta');

/**
 * Sanitize producer_type against the configured enum.
 *
 * @param mixed $value Submitted value.
 * @return string Valid enum value, or empty string.
 */
function wc_ras_sanitize_producer_type($value) {
    $value = is_string($value) ? $value : '';
    $allowed = array_keys(wc_ras_producer_types());
    return in_array($value, $allowed, true) ? $value : '';
}

/**
 * Sanitize fermentation_type against the configured enum.
 *
 * @param mixed $value Submitted value.
 * @return string Valid enum value, or empty string.
 */
function wc_ras_sanitize_fermentation_type($value) {
    $value = is_string($value) ? $value : '';
    $allowed = array_keys(wc_ras_fermentation_types());
    return in_array($value, $allowed, true) ? $value : '';
}

/**
 * Sanitize an integer that may be null.
 *
 * Empty string / 0 / non-numeric becomes empty string so update_post_meta
 * can skip storing a value. Admin save layer deletes the meta when empty.
 *
 * @param mixed $value Submitted value.
 * @return int|string Sanitized int, or '' to signal "no value".
 */
function wc_ras_sanitize_nullable_int($value) {
    if ($value === '' || $value === null) {
        return '';
    }
    if (!is_numeric($value)) {
        return '';
    }
    return absint($value);
}

/**
 * Sanitize the taste_profile object.
 *
 * Keeps only keys that appear in wc_ras_taste_axes(). Values are clamped to
 * 0–10 or preserved as null. Any unknown axis or invalid value is dropped.
 *
 * @param mixed $value Submitted object/array.
 * @return array Sanitized map of axis_key => int|null.
 */
function wc_ras_sanitize_taste_profile($value) {
    if (!is_array($value)) {
        return array();
    }

    $allowed = array_keys(wc_ras_taste_axes());
    $out = array();

    foreach ($allowed as $axis) {
        if (!array_key_exists($axis, $value)) {
            continue;
        }
        $v = $value[$axis];
        if ($v === '' || $v === null) {
            $out[$axis] = null;
            continue;
        }
        if (!is_numeric($v)) {
            continue;
        }
        $n = (int) $v;
        if ($n < 0) {
            $n = 0;
        }
        if ($n > 10) {
            $n = 10;
        }
        $out[$axis] = $n;
    }

    return $out;
}

/**
 * Sanitize the altitude object.
 *
 * @param mixed $value Submitted object/array.
 * @return array Sanitized {min, max, unit}.
 */
function wc_ras_sanitize_altitude($value) {
    if (!is_array($value)) {
        return array();
    }

    $out = array();

    if (isset($value['min']) && $value['min'] !== '' && is_numeric($value['min'])) {
        $out['min'] = absint($value['min']);
    }
    if (isset($value['max']) && $value['max'] !== '' && is_numeric($value['max'])) {
        $out['max'] = absint($value['max']);
    }
    $unit = isset($value['unit']) ? sanitize_text_field($value['unit']) : 'm';
    $out['unit'] = in_array($unit, array('m', 'ft'), true) ? $unit : 'm';

    // If both min and max missing, return empty to clear the meta entirely
    if (!isset($out['min']) && !isset($out['max'])) {
        return array();
    }

    return $out;
}
