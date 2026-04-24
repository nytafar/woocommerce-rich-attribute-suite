<?php
/**
 * Origin modal — native <dialog> shell with a single hydratable card.
 *
 * Server-renders once per product-page load with the default variation's
 * origin. `assets/js/origin-modal.js` updates the card in place on every
 * `found_variation` / `show_variation` event — there is no carousel of
 * per-origin cards, only this one card that mirrors the current
 * pa_opprinnelse selection.
 *
 * Hydration contract: each mutable element carries a `data-field` attribute
 * matching a key on the wc_ras_origin struct. Elements that hold no value
 * for the default variation are rendered with the `hidden` attribute so the
 * initial and hydrated states look identical.
 *
 * Theme override path:
 *   {theme}/woocommerce-rich-attribute-suite/parts/origin-modal.php
 *
 * Expected variable:
 *   @var array|null $origin wc_ras_origin struct or null.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/** @var array|null $origin */
$origin = isset($origin) && is_array($origin) ? $origin : null;

$name           = (string) ($origin['name']                 ?? '');
$excerpt        = (string) ($origin['excerpt']              ?? '');
$variety        = (string) ($origin['variety']              ?? '');
$flag_url       = (string) ($origin['country_flag_url']     ?? '');
$image_url      = (string) ($origin['featured_image_url']   ?? '');
$permalink      = (string) ($origin['permalink']            ?? '');
$taste_notes    = (string) ($origin['taste_notes']          ?? '');
$region_label   = (string) ($origin['region_label']         ?? '');
$alt_str        = (string) ($origin['altitude_label']       ?? '');
$producer_type_label  = (string) ($origin['producer_type_label']  ?? '');
$producer_count_str   = (string) ($origin['producer_count_label'] ?? '');
$ferm_value     = (string) ($origin['fermentation_value']   ?? '');
$ferm_method    = (string) ($origin['fermentation_method']  ?? '');
$drying_method  = (string) ($origin['drying_method']        ?? '');

$radar_svg = ($origin && !empty($origin['taste_profile']) && function_exists('wc_ras_render_taste_radar_svg'))
    ? wc_ras_render_taste_radar_svg($origin['taste_profile'])
    : '';

$certs = (is_array($origin['certifications'] ?? null)) ? $origin['certifications'] : array();

// Section-visibility gates (JS recomputes on hydration).
$has_producers      = ($producer_type_label !== '' || $producer_count_str !== '' || $variety !== '');
$has_postharvest    = ($ferm_value !== '' || $ferm_method !== '' || $drying_method !== '');
$has_flavour        = ($radar_svg !== '' || $taste_notes !== '');
$has_certifications = !empty($certs);

$hidden_attr = function ($cond) {
    return $cond ? '' : ' hidden';
};
?>
<dialog class="wc-ras-origin-modal" data-wc-ras-origin-modal aria-labelledby="wc-ras-origin-modal-title">
    <div class="wc-ras-origin-modal__chrome">
        <button type="button"
                class="wc-ras-origin-modal__nav wc-ras-origin-modal__nav--prev"
                data-origin-modal-nav="prev"
                aria-label="<?php esc_attr_e('Forrige opprinnelse', 'wc-rich-attribute-suite'); ?>">
            <span aria-hidden="true">‹</span>
        </button>

        <button type="button"
                class="wc-ras-origin-modal__close"
                data-origin-modal-close
                aria-label="<?php esc_attr_e('Lukk', 'wc-rich-attribute-suite'); ?>">
            <span aria-hidden="true">×</span>
        </button>

        <button type="button"
                class="wc-ras-origin-modal__nav wc-ras-origin-modal__nav--next"
                data-origin-modal-nav="next"
                aria-label="<?php esc_attr_e('Neste opprinnelse', 'wc-rich-attribute-suite'); ?>">
            <span aria-hidden="true">›</span>
        </button>
    </div>

    <article class="wc-ras-origin-modal__card" data-origin-modal-card>

        <header class="wc-ras-origin-modal__hero">
            <img class="wc-ras-origin-modal__image"
                 data-field="featured-image"
                 src="<?php echo esc_url($image_url); ?>"
                 alt="<?php echo esc_attr($name); ?>"
                 <?php echo $hidden_attr($image_url !== ''); ?> />

            <div class="wc-ras-origin-modal__hero-text">
                <h2 id="wc-ras-origin-modal-title"
                    class="wc-ras-origin-modal__title"
                    data-field="name"><?php echo esc_html($name); ?></h2>

                <p class="wc-ras-origin-modal__tagline"
                   data-field="excerpt"
                   <?php echo $hidden_attr($excerpt !== ''); ?>><?php echo esc_html($excerpt); ?></p>

                <div class="wc-ras-origin-modal__pills">
                    <span class="pill flag"
                          data-field-group="flag"
                          <?php echo $hidden_attr($region_label !== ''); ?>>
                        <img class="flag-img"
                             data-field="flag-image"
                             src="<?php echo esc_url($flag_url); ?>"
                             alt=""
                             <?php echo $hidden_attr($flag_url !== ''); ?> />
                        <span data-field="flag-label"><?php echo esc_html($region_label); ?></span>
                    </span>

                    <span class="pill variety"
                          data-field-group="variety"
                          <?php echo $hidden_attr($variety !== ''); ?>>
                        <span data-field="variety"><?php echo esc_html($variety); ?></span>
                    </span>

                    <span class="pill altitude"
                          data-field-group="altitude"
                          <?php echo $hidden_attr($alt_str !== ''); ?>>
                        <?php echo wc_ras_origin_icon('altitude'); ?>
                        <span data-field="altitude"><?php echo esc_html($alt_str); ?></span>
                    </span>
                </div>
            </div>
        </header>

        <section class="wc-ras-origin-modal__section producers"
                 data-section="producers"
                 <?php echo $hidden_attr($has_producers); ?>>
            <h3><?php esc_html_e('Produsenter og dyrking', 'wc-rich-attribute-suite'); ?></h3>

            <p class="producer-line"
               data-field-group="producer-type"
               <?php echo $hidden_attr($producer_type_label !== ''); ?>>
                <?php echo wc_ras_origin_icon('producer'); ?>
                <span data-field="producer-type"><?php echo esc_html($producer_type_label); ?></span>
            </p>

            <p class="producer-count"
               data-field="producer-count"
               <?php echo $hidden_attr($producer_count_str !== ''); ?>><?php echo esc_html($producer_count_str); ?></p>

            <p class="variety-line"
               data-field-group="variety-line"
               <?php echo $hidden_attr($variety !== ''); ?>>
                <strong><?php esc_html_e('Varietet:', 'wc-rich-attribute-suite'); ?></strong>
                <span data-field="variety-line"><?php echo esc_html($variety); ?></span>
            </p>
        </section>

        <section class="wc-ras-origin-modal__section postharvest"
                 data-section="postharvest"
                 <?php echo $hidden_attr($has_postharvest); ?>>
            <h3><?php esc_html_e('Etter innhøsting', 'wc-rich-attribute-suite'); ?></h3>

            <div class="postharvest-item"
                 data-field-group="fermentation"
                 <?php echo $hidden_attr($ferm_value !== '' || $ferm_method !== ''); ?>>
                <div class="icon"><?php echo wc_ras_origin_icon('fermentation'); ?></div>
                <div class="body">
                    <div class="label"><?php esc_html_e('Fermentering', 'wc-rich-attribute-suite'); ?></div>
                    <div class="value"
                         data-field="fermentation-value"
                         <?php echo $hidden_attr($ferm_value !== ''); ?>><?php echo esc_html($ferm_value); ?></div>
                    <div class="freetext"
                         data-field="fermentation-method"
                         <?php echo $hidden_attr($ferm_method !== ''); ?>><?php echo esc_html($ferm_method); ?></div>
                </div>
            </div>

            <div class="postharvest-item"
                 data-field-group="drying"
                 <?php echo $hidden_attr($drying_method !== ''); ?>>
                <div class="icon"><?php echo wc_ras_origin_icon('drying'); ?></div>
                <div class="body">
                    <div class="label"><?php esc_html_e('Tørking', 'wc-rich-attribute-suite'); ?></div>
                    <div class="freetext"
                         data-field="drying-method"
                         <?php echo $hidden_attr($drying_method !== ''); ?>><?php echo esc_html($drying_method); ?></div>
                </div>
            </div>
        </section>

        <section class="wc-ras-origin-modal__section flavour"
                 data-section="flavour"
                 <?php echo $hidden_attr($has_flavour); ?>>
            <div class="radar"
                 data-field="radar"
                 <?php echo $hidden_attr($radar_svg !== ''); ?>><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns safe inline SVG
                    echo $radar_svg;
                ?></div>
            <div class="notes"
                 data-field-group="notes"
                 <?php echo $hidden_attr($taste_notes !== ''); ?>>
                <h3><?php esc_html_e('Smaksnotater', 'wc-rich-attribute-suite'); ?></h3>
                <p data-field="taste-notes"><?php echo esc_html($taste_notes); ?></p>
            </div>
        </section>

        <section class="wc-ras-origin-modal__section certifications"
                 data-section="certifications"
                 <?php echo $hidden_attr($has_certifications); ?>>
            <h3><?php esc_html_e('Sertifiseringer', 'wc-rich-attribute-suite'); ?></h3>
            <ul data-field="certifications">
                <?php foreach ($certs as $cert) : ?>
                    <li data-cert-slug="<?php echo esc_attr($cert['slug'] ?? ''); ?>">
                        <?php if (!empty($cert['icon_url'])) : ?>
                            <img src="<?php echo esc_url($cert['icon_url']); ?>" alt="" />
                        <?php endif; ?>
                        <span><?php echo esc_html($cert['name'] ?? ''); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <p class="wc-ras-origin-modal__permalink"
           <?php echo $hidden_attr($permalink !== ''); ?>>
            <a data-field="permalink"
               href="<?php echo esc_url($permalink); ?>"
               class="term-page-link">
                <?php esc_html_e('Se hele siden →', 'wc-rich-attribute-suite'); ?>
            </a>
        </p>
    </article>
</dialog>
