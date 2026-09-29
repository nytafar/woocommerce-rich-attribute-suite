<?php
/**
 * Template loader — theme-overridable template parts.
 *
 * Shared helper for rendering plugin template files with theme override.
 * Themes place overrides at `{theme}/kaupang-attribute-suite/{slug}.php`.
 *
 * @package Kaupang\AttributeSuite
 */

defined('ABSPATH') || exit;

/**
 * Render a plugin template to a string.
 *
 * @param string $slug Template slug without `.php`. E.g. `parts/variation-description`.
 * @param array  $args Associative array of variables made available to the template.
 * @return string Rendered HTML, or empty string if the template is missing.
 */
function kaupang_attribute_suite_load_template($slug, array $args = array()) {
    $slug = ltrim((string) $slug, '/');
    if ($slug === '') {
        return '';
    }
    $rel = $slug . '.php';

    // Theme overrides take priority.
    $theme_hit = locate_template(array(
        'kaupang-attribute-suite/' . $rel,
    ));

    $path = $theme_hit ? $theme_hit : KAUPANG_ATTRIBUTE_SUITE_DIR . 'templates/' . $rel;

    if (!file_exists($path)) {
        return '';
    }

    if (!empty($args)) {
        extract($args, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
    }

    ob_start();
    include $path;
    return (string) ob_get_clean();
}
