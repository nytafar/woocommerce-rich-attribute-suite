<?php
/**
 * Variation description — inner HTML injected into the existing
 * `.wc-ras-inline-description.woocommerce-variation-description` container
 * by inline-variation-description.js on variation change.
 *
 * DO NOT wrap in the container div here — the container is owned by the
 * inline-variation-description infrastructure. This template renders only
 * the inner blocks.
 *
 * Rendering order:
 *   1. Pills row (flag / variety / altitude) — betinget på $origin-data.
 *   2. <p class="smak"> — betinget på $origin['taste_notes'].
 *   3. <p> — betinget på $description_text (term-/variation-desc fallback).
 *   4. <p class="term-page-link-wrapper"> — CTA, betinget på $cta_url.
 *
 * Expected variables (passed via wc_ras_load_template):
 *   @var array|null $origin           wc_ras_origin struct or null.
 *   @var string     $description_text Free-text fallback (may be empty).
 *   @var string     $cta_url          Canonical URL for CTA ("" disables).
 *   @var string     $cta_label        Translated label for CTA.
 *
 * Theme override path:
 *   {theme}/woocommerce-rich-attribute-suite/parts/variation-description.php
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

/** @var array|null $origin */
$origin = isset($origin) && is_array($origin) ? $origin : null;
$description_text = isset($description_text) ? (string) $description_text : '';
$cta_url   = isset($cta_url)   ? (string) $cta_url   : '';
$cta_label = isset($cta_label) ? (string) $cta_label : __('Lær mer', 'wc-rich-attribute-suite');

// ── Pills ───────────────────────────────────────────────────────
$country  = $origin['country']  ?? null;
$region   = $origin['region']   ?? null;
$variety  = $origin['variety']  ?? null;
$flag_url = $origin['country_flag_url'] ?? null;
$alt_str  = (!empty($origin['altitude']) && function_exists('wc_ras_format_altitude'))
    ? wc_ras_format_altitude($origin['altitude'])
    : '';

$region_label = implode(', ', array_filter(array($country, $region)));
$has_flag_pill     = $region_label !== '';
$has_variety_pill  = !empty($variety);
$has_altitude_pill = $alt_str !== '';
$has_any_pill = $has_flag_pill || $has_variety_pill || $has_altitude_pill;

// ── Smak ────────────────────────────────────────────────────────
$taste_notes = $origin['taste_notes'] ?? null;

if ($has_any_pill) : ?>
<div class="origin-pills">
    <?php if ($has_flag_pill) : ?>
        <span class="pill flag">
            <?php if ($flag_url) : ?>
                <img class="flag-img" src="<?php echo esc_url($flag_url); ?>" alt="<?php echo esc_attr($country ?: ''); ?>" />
            <?php endif; ?>
        </span>
    <?php endif; ?>

    <?php if ($has_variety_pill) : ?>
        <span class="pill variety"><?php echo esc_html($variety); ?></span>
    <?php endif; ?>

    <?php if ($has_altitude_pill) : ?>
        <span class="pill altitude"><?php echo esc_html($alt_str); ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($taste_notes)) : ?>
<p class="smak"><?php echo esc_html($taste_notes); ?></p>
<?php endif; ?>

<?php if ($description_text !== '') : ?>
<p><?php echo wp_kses_post($description_text); ?></p>
<?php endif; ?>

<?php if ($cta_url !== '') : ?>
<p class="term-page-link-wrapper">
    <a href="<?php echo esc_url($cta_url); ?>" class="term-page-link" data-open-origin-modal>
        <?php echo esc_html($cta_label); ?>
    </a>
</p>
<?php endif;
