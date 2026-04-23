<?php
/**
 * Origin card (used on archive grid and anywhere else we list origins).
 *
 * Expected variable:
 *   $card_origin (array) — origin struct from wc_ras_build_origin_struct().
 *                          Caller is responsible for building it.
 *
 * Entire card is link-covered: the overlay <a> makes the whole card
 * clickable while keeping semantic elements (heading, etc.) in the source
 * for screen readers.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/** @var array|null $card_origin */
if (empty($card_origin) || !is_array($card_origin)) {
    return;
}

$country       = $card_origin['country']  ?? null;
$region        = $card_origin['region']   ?? null;
$variety       = $card_origin['variety']  ?? null;
$flag_url      = $card_origin['country_flag_url'] ?? null;
$alt_str       = $card_origin['altitude'] ? wc_ras_format_altitude($card_origin['altitude']) : '';
$tagline       = $card_origin['excerpt']  ?? '';
$producer_type = $card_origin['producer_type'] ?? null;
$permalink     = $card_origin['permalink'] ?? '#';
$name          = $card_origin['name']     ?? '';
$img           = $card_origin['featured_image_url'] ?? null;

$region_label = implode(', ', array_filter(array($country, $region)));
$product_count = function_exists('wc_ras_origin_product_count') && !empty($card_origin['slug'])
    ? wc_ras_origin_product_count($card_origin['slug'])
    : 0;
?>
<article class="wc-ras-origin-card">
    <?php if ($img) : ?>
        <div class="media">
            <img src="<?php echo esc_url($img); ?>" alt="" loading="lazy" />
        </div>
    <?php endif; ?>

    <div class="body">
        <h2><?php echo esc_html($name); ?></h2>

        <?php if ($region_label || $variety || $alt_str !== '') : ?>
            <div class="meta">
                <?php if ($region_label) : ?>
                    <span class="pill flag">
                        <?php if ($flag_url) : ?>
                            <img src="<?php echo esc_url($flag_url); ?>" alt="" />
                        <?php endif; ?>
                        <span><?php echo esc_html($region_label); ?></span>
                    </span>
                <?php endif; ?>

                <?php if ($variety) : ?>
                    <span class="pill variety"><?php echo esc_html($variety); ?></span>
                <?php endif; ?>

                <?php if ($alt_str !== '') : ?>
                    <span class="pill altitude">
                        <?php echo wc_ras_origin_icon('altitude'); ?>
                        <span><?php echo esc_html($alt_str); ?></span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tagline) : ?>
            <p class="tagline"><?php echo esc_html($tagline); ?></p>
        <?php endif; ?>

        <?php if ($producer_type) : ?>
            <div class="producer">
                <?php echo wc_ras_origin_icon('producer'); ?>
                <?php
                $types = function_exists('wc_ras_producer_types') ? wc_ras_producer_types() : array();
                $producer_label = isset($types[$producer_type]) ? $types[$producer_type] : $producer_type;
                ?>
                <span><?php echo esc_html($producer_label); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($product_count > 0) : ?>
            <p class="count">
                <?php
                printf(
                    /* translators: %d: number of chocolates/products using this origin */
                    esc_html(_n('Brukes i %d sjokolade', 'Brukes i %d sjokolader', $product_count, 'wc-rich-attribute-suite')),
                    (int) $product_count
                );
                ?>
            </p>
        <?php endif; ?>
    </div>

    <a class="link" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($name); ?>">
        <?php echo esc_html($name); ?>
    </a>
</article>
