<?php
/**
 * Template: Attribute Term Index
 *
 * Thin page shell. All wrapper HTML lives in
 * wc_ras_render_attribute_term_index() so markup has one home.
 * Theme override: drop `wc-ras/attribute-term-index.php` or
 * `attribute-term-index.php` in the theme to take over entirely.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

get_header();
?>

<main id="primary">
    <?php echo wc_ras_render_attribute_term_index(wc_ras_resolve_index_taxonomy()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>

<?php
get_footer();
