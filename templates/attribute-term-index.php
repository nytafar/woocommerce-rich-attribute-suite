<?php
/**
 * Template: Attribute Term Index
 *
 * Renders a WP page as a grid overview of all terms for a given
 * product attribute taxonomy (defaults to pa_opprinnelse).
 *
 * Theme override: drop `wc-ras/attribute-term-index.php` or
 * `attribute-term-index.php` in the theme to take over entirely.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

get_header();

$page_id  = get_queried_object_id();
$taxonomy = wc_ras_resolve_index_taxonomy();
$intro    = $page_id ? get_post_field('post_content', $page_id) : '';
?>

<main id="primary">
    <section class="attribute-archive">
        <h1><?php the_title(); ?></h1>

        <?php if (!empty($intro)) : ?>
            <div class="page-intro">
                <?php echo apply_filters('the_content', $intro); ?>
            </div>
        <?php endif; ?>

        <?php echo wc_ras_render_attribute_term_index($taxonomy); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </section>
</main>

<?php
get_footer();
