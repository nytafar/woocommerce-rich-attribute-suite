<?php
/**
 * Single template for the attribute_page CPT.
 *
 * Rendered at /opprinnelser/{slug}/ unless the theme overrides via standard
 * WP template hierarchy (single-attribute_page.php in the active theme).
 *
 * Structure:
 *   <article>
 *     <header> — complex grid:
 *       1. Hero: featured image + navn + tagline + flag-pille + altitude-pille
 *       2. Producers & Cultivation: producer_type, producer_count, variety
 *       3. Post-harvest: fermentering + tørking (hybrid layout)
 *       4. Flavour: radar-SVG + frihåndsnotater (smak)
 *       5. Certifications: badges
 *     </header>
 *     6. the_content() — Gutenberg blocks (direct children of <article>)
 *     7. Related products (deferred — hook for theme/site integration)
 *   </article>
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();

    $origin = wc_ras_build_origin_struct(get_post());
    if (!$origin) {
        continue;
    }

    $region_label = implode(', ', array_filter(array($origin['country'], $origin['region'])));
    $alt_str      = $origin['altitude'] ? wc_ras_format_altitude($origin['altitude']) : '';
    $producer_types = function_exists('wc_ras_producer_types') ? wc_ras_producer_types() : array();
    ?>
    <article <?php post_class('wc-ras-origin-single'); ?>>
        <header class="header">
            <?php if ($origin['featured_image_url']) : ?>
                <div class="hero-image">
                    <img src="<?php echo esc_url($origin['featured_image_url']); ?>"
                         alt="<?php echo esc_attr($origin['name']); ?>" />
                </div>
            <?php endif; ?>

            <div class="hero">
                <h1><?php echo esc_html($origin['name']); ?></h1>

                <?php if (!empty($origin['excerpt'])) : ?>
                    <p class="tagline"><?php echo esc_html($origin['excerpt']); ?></p>
                <?php endif; ?>

                <?php if ($region_label || $alt_str !== '') : ?>
                    <div class="meta">
                        <?php if ($region_label) : ?>
                            <span class="pill flag">
                                <?php if ($origin['country_flag_url']) : ?>
                                    <img src="<?php echo esc_url($origin['country_flag_url']); ?>" alt="" />
                                <?php endif; ?>
                                <span><?php echo esc_html($region_label); ?></span>
                            </span>
                        <?php endif; ?>

                        <?php if ($alt_str !== '') : ?>
                            <span class="pill altitude">
                                <?php echo wc_ras_origin_icon('altitude'); ?>
                                <span><?php echo esc_html($alt_str); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            // ─── Producers & Cultivation ───
            $producer_type_label = $origin['producer_type'] && isset($producer_types[$origin['producer_type']])
                ? $producer_types[$origin['producer_type']]
                : null;

            $show_producer_line = (bool) $producer_type_label;
            $show_count_line    = $origin['producer_count'] !== null;
            $show_variety       = (bool) $origin['variety'];

            if ($show_producer_line || $show_count_line || $show_variety) :
                ?>
                <div class="section producers">
                    <h2><?php esc_html_e('Produsenter og dyrking', 'wc-rich-attribute-suite'); ?></h2>

                    <?php if ($show_producer_line) : ?>
                        <p class="producer-line">
                            <?php echo wc_ras_origin_icon('producer'); ?>
                            <span><?php echo esc_html($producer_type_label); ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if ($show_count_line) : ?>
                        <p class="producer-count">
                            <?php
                            printf(
                                /* translators: %d: number of producers */
                                esc_html(_n('%d produsent', '%d produsenter', (int) $origin['producer_count'], 'wc-rich-attribute-suite')),
                                (int) $origin['producer_count']
                            );
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($show_variety) : ?>
                        <p class="variety">
                            <strong><?php esc_html_e('Varietet:', 'wc-rich-attribute-suite'); ?></strong>
                            <?php echo esc_html($origin['variety']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            // ─── Post-harvest ───
            $fermentation_value = wc_ras_format_fermentation_value(
                $origin['fermentation_type'],
                $origin['fermentation_days']
            );
            $ferm_item = wc_ras_render_postharvest_item(
                'fermentation',
                __('Fermentering', 'wc-rich-attribute-suite'),
                $fermentation_value,
                (string) ($origin['fermentation_method'] ?? '')
            );
            $dry_item = wc_ras_render_postharvest_item(
                'drying',
                __('Tørking', 'wc-rich-attribute-suite'),
                '',
                (string) ($origin['drying_method'] ?? '')
            );

            if ($ferm_item || $dry_item) :
                ?>
                <div class="section postharvest">
                    <h2><?php esc_html_e('Etter innhøsting', 'wc-rich-attribute-suite'); ?></h2>
                    <?php
                    echo $ferm_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper
                    echo $dry_item;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped inside helper
                    ?>
                </div>
            <?php endif; ?>

            <?php
            // ─── Flavour ───
            $radar_svg = wc_ras_render_taste_radar_svg($origin['taste_profile']);
            $has_notes = !empty($origin['taste_notes']);

            if ($radar_svg || $has_notes) :
                ?>
                <div class="section flavour">
                    <?php if ($radar_svg) : ?>
                        <div class="radar"><?php echo $radar_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                    <?php if ($has_notes) : ?>
                        <div class="notes">
                            <h2><?php esc_html_e('Smaksnotater', 'wc-rich-attribute-suite'); ?></h2>
                            <p><?php echo esc_html($origin['taste_notes']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php
            // ─── Certifications ───
            if (!empty($origin['certifications'])) :
                ?>
                <div class="section certifications">
                    <h2><?php esc_html_e('Sertifiseringer', 'wc-rich-attribute-suite'); ?></h2>
                    <ul>
                        <?php foreach ($origin['certifications'] as $cert) : ?>
                            <li>
                                <?php if (!empty($cert['icon_url'])) : ?>
                                    <img src="<?php echo esc_url($cert['icon_url']); ?>" alt="" />
                                <?php endif; ?>
                                <span><?php echo esc_html($cert['name']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </header>

        <?php
        // ─── Gutenberg content ───
        // Direct children of <article> so block alignment (alignwide/alignfull)
        // and theme content styles apply without an extra wrapper.
        the_content();

        // ─── Related products hook ───
        // Intentionally no default rendering — hook leveraged by theme/site
        // integration. Kept for fase 3/4 when we ship the related-products
        // query that filters by pa_opprinnelse.
        do_action('wc_ras_origin_single_after_content', $origin);
        ?>
    </article>
    <?php
endwhile;

get_footer();
