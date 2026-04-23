<?php
/**
 * Archive template for the attribute_page CPT.
 *
 * Rendered at /opprinnelser/ unless the theme overrides via standard WP
 * template hierarchy (archive-attribute_page.php in the active theme).
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

get_header();
?>
<main class="wc-ras-origin-archive">
    <header class="header">
        <h1><?php post_type_archive_title(); ?></h1>
        <?php
        $archive_desc = get_the_archive_description();
        if ($archive_desc) :
            ?>
            <div class="description"><?php echo wp_kses_post($archive_desc); ?></div>
        <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
        <div class="grid">
            <?php while (have_posts()) : the_post(); ?>
                <?php
                $card_origin = wc_ras_build_origin_struct(get_post());
                if ($card_origin) {
                    include WC_RAS_PLUGIN_DIR . 'templates/parts/origin-card.php';
                }
                ?>
            <?php endwhile; ?>
        </div>

        <?php
        the_posts_pagination(array(
            'prev_text' => __('Forrige', 'wc-rich-attribute-suite'),
            'next_text' => __('Neste', 'wc-rich-attribute-suite'),
        ));
        ?>
    <?php else : ?>
        <p class="empty">
            <?php esc_html_e('Ingen opprinnelser publisert enda.', 'wc-rich-attribute-suite'); ?>
        </p>
    <?php endif; ?>
</main>
<?php
get_footer();
