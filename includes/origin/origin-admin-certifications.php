<?php
/**
 * Origin module — certification icon picker (term admin UI).
 *
 * Adds a media-frame attachment picker to the certification taxonomy's
 * add-term and edit-term screens, bound to the `icon_svg_id` term meta
 * that was registered in origin-taxonomies.php.
 *
 * Stores the attachment ID, not the URL — consumers resolve the URL via
 * wp_get_attachment_url() so alt text and regenerated sources stay in
 * sync with the media library.
 *
 * @package Kaupang\AttributeSuite
 */

defined('ABSPATH') || exit;

/**
 * Enqueue the picker script on the term edit/add pages for the
 * certification taxonomy.
 *
 * @param string $hook Current admin page hook.
 */
function kaupang_attribute_suite_origin_enqueue_cert_picker($hook) {
    if (!in_array($hook, array('edit-tags.php', 'term.php'), true)) {
        return;
    }
    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key(wp_unslash($_GET['taxonomy'])) : '';
    if ($taxonomy !== 'certification') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'kaupang-attribute-suite-admin-cert-picker',
        KAUPANG_ATTRIBUTE_SUITE_URL . 'assets/js/admin-certification-picker.js',
        array(),
        KAUPANG_ATTRIBUTE_SUITE_VERSION,
        true
    );
    wp_localize_script('kaupang-attribute-suite-admin-cert-picker', 'wcRasCertPicker', array(
        'frameTitle'   => __('Velg sertifiserings-ikon', 'kaupang-attribute-suite'),
        'frameButton'  => __('Bruk dette ikonet', 'kaupang-attribute-suite'),
        'chooseLabel'  => __('Velg ikon', 'kaupang-attribute-suite'),
        'replaceLabel' => __('Bytt ikon', 'kaupang-attribute-suite'),
        'removeLabel'  => __('Fjern ikon', 'kaupang-attribute-suite'),
        'mimeTypes'    => array('image/svg+xml', 'image/png', 'image/webp'),
    ));
}
add_action('admin_enqueue_scripts', 'kaupang_attribute_suite_origin_enqueue_cert_picker');

/**
 * Render the picker field on the "Add certification" screen.
 */
function kaupang_attribute_suite_origin_cert_add_fields() {
    ?>
    <div class="form-field wc-ras-cert-icon-field" data-wc-ras-cert-icon>
        <label><?php esc_html_e('Ikon', 'kaupang-attribute-suite'); ?></label>
        <div class="wc-ras-cert-icon-preview" data-wc-ras-cert-preview></div>
        <input type="hidden" name="kaupang_attribute_suite_icon_svg_id" value="" data-wc-ras-cert-input />
        <button type="button" class="button" data-wc-ras-cert-choose>
            <?php esc_html_e('Velg ikon', 'kaupang-attribute-suite'); ?>
        </button>
        <button type="button" class="button-link" data-wc-ras-cert-remove hidden>
            <?php esc_html_e('Fjern ikon', 'kaupang-attribute-suite'); ?>
        </button>
        <p class="description">
            <?php esc_html_e('SVG anbefales. Ikonet vises på modal, CPT-side og arkivkort — ikke på produktkort.', 'kaupang-attribute-suite'); ?>
        </p>
    </div>
    <?php
}
add_action('certification_add_form_fields', 'kaupang_attribute_suite_origin_cert_add_fields');

/**
 * Render the picker field on the "Edit certification" screen.
 *
 * @param WP_Term $term Current term.
 */
function kaupang_attribute_suite_origin_cert_edit_fields($term) {
    $icon_id  = (int) get_term_meta($term->term_id, 'icon_svg_id', true);
    $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';
    ?>
    <tr class="form-field wc-ras-cert-icon-field" data-wc-ras-cert-icon>
        <th scope="row">
            <label><?php esc_html_e('Ikon', 'kaupang-attribute-suite'); ?></label>
        </th>
        <td>
            <div class="wc-ras-cert-icon-preview" data-wc-ras-cert-preview>
                <?php if ($icon_url) : ?>
                    <img src="<?php echo esc_url($icon_url); ?>" alt="" style="max-width:64px;max-height:64px;" />
                <?php endif; ?>
            </div>
            <input type="hidden" name="kaupang_attribute_suite_icon_svg_id"
                   value="<?php echo esc_attr($icon_id); ?>"
                   data-wc-ras-cert-input />
            <button type="button" class="button" data-wc-ras-cert-choose>
                <?php echo $icon_id
                    ? esc_html__('Bytt ikon', 'kaupang-attribute-suite')
                    : esc_html__('Velg ikon', 'kaupang-attribute-suite'); ?>
            </button>
            <button type="button" class="button-link" data-wc-ras-cert-remove<?php echo $icon_id ? '' : ' hidden'; ?>>
                <?php esc_html_e('Fjern ikon', 'kaupang-attribute-suite'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('SVG anbefales. Ikonet vises på modal, CPT-side og arkivkort — ikke på produktkort.', 'kaupang-attribute-suite'); ?>
            </p>
        </td>
    </tr>
    <?php
}
add_action('certification_edit_form_fields', 'kaupang_attribute_suite_origin_cert_edit_fields');

/**
 * Persist the icon attachment ID on term create/update.
 *
 * @param int $term_id Term ID.
 */
function kaupang_attribute_suite_origin_cert_save($term_id) {
    if (!current_user_can('manage_categories')) {
        return;
    }
    if (!isset($_POST['kaupang_attribute_suite_icon_svg_id'])) {
        return;
    }
    $id = absint(wp_unslash($_POST['kaupang_attribute_suite_icon_svg_id']));
    if ($id > 0) {
        update_term_meta($term_id, 'icon_svg_id', $id);
    } else {
        delete_term_meta($term_id, 'icon_svg_id');
    }
}
add_action('created_certification', 'kaupang_attribute_suite_origin_cert_save');
add_action('edited_certification',  'kaupang_attribute_suite_origin_cert_save');
