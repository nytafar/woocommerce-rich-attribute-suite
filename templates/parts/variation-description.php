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
 *   1. <p class="smak"> — betinget på $origin['taste_notes'].
 *   2. <p> — betinget på $description_text (term-/variation-desc fallback).
 *   3. <p class="origin-meta"> — flagg + land | varietet | høyde, betinget
 *      på $origin-data. Under teksten, ikke over; ingen piller.
 *   4. <p class="term-page-link-wrapper"> — CTA, betinget på $cta_url.
 *
 * Expected variables (passed via kaupang_attribute_suite_load_template):
 *   @var array|null $origin           wc_ras_origin struct or null.
 *   @var string     $description_text Free-text fallback (may be empty).
 *   @var string     $cta_url          Canonical URL for CTA ("" disables).
 *   @var string     $cta_label        Translated label for CTA.
 *
 * Theme override path:
 *   {theme}/kaupang-attribute-suite/parts/variation-description.php
 *
 * @package Kaupang\AttributeSuite
 */

defined('ABSPATH') || exit;

/** @var array|null $origin */
$origin = isset($origin) && is_array($origin) ? $origin : null;
$description_text = isset($description_text) ? (string) $description_text : '';
$cta_url   = isset($cta_url)   ? (string) $cta_url   : '';
$cta_label = isset($cta_label) ? (string) $cta_label : __('Lær mer', 'kaupang-attribute-suite');

// ── Meta line ───────────────────────────────────────────────────
$country  = (string) ($origin['country']  ?? '');
$variety  = (string) ($origin['variety']  ?? '');
$flag_url = (string) ($origin['country_flag_url'] ?? '');
$alt_str  = (!empty($origin['altitude']) && function_exists('kaupang_attribute_suite_format_altitude'))
    ? (string) kaupang_attribute_suite_format_altitude($origin['altitude'])
    : '';

$meta = array();
if ($country !== '') {
    $meta[] = ($flag_url !== '' ? '<img class="flag-img" src="' . esc_url($flag_url) . '" alt="" /> ' : '') . esc_html($country);
}
if ($variety !== '') {
    $meta[] = esc_html($variety);
}
if ($alt_str !== '') {
    $meta[] = esc_html($alt_str);
}

// ── Smak ────────────────────────────────────────────────────────
$taste_notes = $origin['taste_notes'] ?? null;

if (!empty($taste_notes)) : ?>
<p class="smak"><?php echo esc_html($taste_notes); ?></p>
<?php endif; ?>

<?php if ($description_text !== '') : ?>
<p><?php echo wp_kses_post($description_text); ?></p>
<?php endif; ?>

<?php if ($meta) : ?>
<p class="origin-meta"><?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above
    echo implode(' <span class="sep" aria-hidden="true">|</span> ', $meta);
?></p>
<?php endif; ?>

<?php if ($cta_url !== '') : ?>
<p class="term-page-link-wrapper">
    <a href="<?php echo esc_url($cta_url); ?>" class="term-page-link" data-open-origin-modal>
        <?php echo esc_html($cta_label); ?>
    </a>
</p>
<?php endif;
