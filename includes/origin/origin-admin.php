<?php
/**
 * Origin module — grouped admin meta boxes.
 *
 * Renders six thematic meta boxes on the attribute_page edit screen and
 * handles save with enum whitelisting and range clamping. Existing RAS
 * meta box (region, smak) remains untouched — this module adds to it.
 *
 * @package Kaupang\AttributeSuite
 */

defined('ABSPATH') || exit;

/**
 * Register origin meta boxes.
 */
function kaupang_attribute_suite_origin_register_meta_boxes() {
    add_meta_box(
        'kaupang_attribute_suite_origin_cultivation',
        __('Opprinnelse: Cultivation', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_cultivation_box',
        'attribute_page',
        'normal',
        'default'
    );

    add_meta_box(
        'kaupang_attribute_suite_origin_people',
        __('Opprinnelse: People & Producers', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_people_box',
        'attribute_page',
        'normal',
        'default'
    );

    add_meta_box(
        'kaupang_attribute_suite_origin_postharvest',
        __('Opprinnelse: Post-harvest', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_postharvest_box',
        'attribute_page',
        'normal',
        'default'
    );

    add_meta_box(
        'kaupang_attribute_suite_origin_flavour',
        __('Opprinnelse: Flavour', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_flavour_box',
        'attribute_page',
        'normal',
        'default'
    );

    add_meta_box(
        'kaupang_attribute_suite_origin_future',
        __('Opprinnelse: Fremtidig', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_future_box',
        'attribute_page',
        'side',
        'default'
    );

    add_meta_box(
        'kaupang_attribute_suite_origin_refs',
        __('Opprinnelse: Referanser', 'kaupang-attribute-suite'),
        'kaupang_attribute_suite_origin_render_refs_box',
        'attribute_page',
        'side',
        'default'
    );
}
add_action('add_meta_boxes_attribute_page', 'kaupang_attribute_suite_origin_register_meta_boxes');

/**
 * Render a shared nonce field. Called once from the first box rendered.
 *
 * @param WP_Post $post Current post.
 */
function kaupang_attribute_suite_origin_render_nonce($post) {
    static $done = false;
    if ($done) {
        return;
    }
    wp_nonce_field('kaupang_attribute_suite_origin_meta', 'kaupang_attribute_suite_origin_meta_nonce');
    $done = true;
}

/**
 * Cultivation box.
 */
function kaupang_attribute_suite_origin_render_cultivation_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $variety = get_post_meta($post->ID, 'variety', true);
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_variety"><?php esc_html_e('Variety', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="text" id="kaupang_attribute_suite_variety" name="kaupang_attribute_suite_origin[variety]"
                       value="<?php echo esc_attr($variety); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Cacao variety, e.g. Chuncho, Criollo, Trinitario.', 'kaupang-attribute-suite'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * People & Producers box.
 */
function kaupang_attribute_suite_origin_render_people_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $producer_type  = get_post_meta($post->ID, 'producer_type', true);
    $producer_count = get_post_meta($post->ID, 'producer_count', true);
    $types          = kaupang_attribute_suite_producer_types();
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_producer_type"><?php esc_html_e('Producer type', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <select id="kaupang_attribute_suite_producer_type" name="kaupang_attribute_suite_origin[producer_type]">
                    <option value=""><?php esc_html_e('— Select —', 'kaupang-attribute-suite'); ?></option>
                    <?php foreach ($types as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($producer_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_producer_count"><?php esc_html_e('Producer count', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="number" id="kaupang_attribute_suite_producer_count" name="kaupang_attribute_suite_origin[producer_count]"
                       value="<?php echo esc_attr($producer_count); ?>" min="1" step="1" class="small-text" />
                <p class="description"><?php esc_html_e('Leave blank if not applicable — the line is hidden when missing.', 'kaupang-attribute-suite'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Post-harvest box.
 */
function kaupang_attribute_suite_origin_render_postharvest_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $ferm_type   = get_post_meta($post->ID, 'fermentation_type', true);
    $ferm_days   = get_post_meta($post->ID, 'fermentation_days', true);
    $ferm_method = get_post_meta($post->ID, 'fermentation_method', true);
    $drying      = get_post_meta($post->ID, 'drying_method', true);
    $ferm_types  = kaupang_attribute_suite_fermentation_types();
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_fermentation_type"><?php esc_html_e('Fermentation type', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <select id="kaupang_attribute_suite_fermentation_type" name="kaupang_attribute_suite_origin[fermentation_type]">
                    <option value=""><?php esc_html_e('— Select —', 'kaupang-attribute-suite'); ?></option>
                    <?php foreach ($ferm_types as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($ferm_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_fermentation_days"><?php esc_html_e('Fermentation days', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="number" id="kaupang_attribute_suite_fermentation_days" name="kaupang_attribute_suite_origin[fermentation_days]"
                       value="<?php echo esc_attr($ferm_days); ?>" min="1" step="1" class="small-text" />
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_fermentation_method"><?php esc_html_e('Fermentation method', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="text" id="kaupang_attribute_suite_fermentation_method" name="kaupang_attribute_suite_origin[fermentation_method]"
                       value="<?php echo esc_attr($ferm_method); ?>" class="large-text" maxlength="200" />
                <p class="description"><?php esc_html_e('Short free-text description (e.g., "3-tier cascade wooden boxes").', 'kaupang-attribute-suite'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="kaupang_attribute_suite_drying_method"><?php esc_html_e('Drying method', 'kaupang-attribute-suite'); ?></label>
            </th>
            <td>
                <input type="text" id="kaupang_attribute_suite_drying_method" name="kaupang_attribute_suite_origin[drying_method]"
                       value="<?php echo esc_attr($drying); ?>" class="large-text" maxlength="200" />
                <p class="description"><?php esc_html_e('Short free-text description (e.g., "Sun-dried on raised beds").', 'kaupang-attribute-suite'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Flavour box — 8 axes from kaupang_attribute_suite_taste_axes().
 */
function kaupang_attribute_suite_origin_render_flavour_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $profile = get_post_meta($post->ID, 'taste_profile', true);
    if (!is_array($profile)) {
        $profile = array();
    }
    $axes = kaupang_attribute_suite_taste_axes();
    ?>
    <table class="form-table">
        <tbody>
        <?php foreach ($axes as $key => $label) :
            $value = array_key_exists($key, $profile) ? $profile[$key] : '';
            if ($value === null) {
                $value = '';
            }
            ?>
            <tr>
                <th scope="row">
                    <label for="kaupang_attribute_suite_taste_<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($label); ?>
                    </label>
                </th>
                <td>
                    <input type="number"
                           id="kaupang_attribute_suite_taste_<?php echo esc_attr($key); ?>"
                           name="kaupang_attribute_suite_origin[taste_profile][<?php echo esc_attr($key); ?>]"
                           value="<?php echo esc_attr($value); ?>"
                           min="0" max="10" step="1" class="small-text" />
                    <span class="description"><?php esc_html_e('0–10, blank = ikke vurdert', 'kaupang-attribute-suite'); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="description">
        <?php esc_html_e('Frihåndsnotater redigeres i feltet "Smak" i hovedboksen ovenfor.', 'kaupang-attribute-suite'); ?>
    </p>
    <?php
}

/**
 * Future/private box — altitude. Stored, not frontend-rendered yet.
 */
function kaupang_attribute_suite_origin_render_future_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $altitude = get_post_meta($post->ID, 'altitude', true);
    if (!is_array($altitude)) {
        $altitude = array();
    }
    $min  = isset($altitude['min']) ? $altitude['min'] : '';
    $max  = isset($altitude['max']) ? $altitude['max'] : '';
    $unit = isset($altitude['unit']) ? $altitude['unit'] : 'm';
    ?>
    <p>
        <label for="kaupang_attribute_suite_altitude_min"><?php esc_html_e('Altitude min', 'kaupang-attribute-suite'); ?></label><br />
        <input type="number" id="kaupang_attribute_suite_altitude_min" name="kaupang_attribute_suite_origin[altitude][min]"
               value="<?php echo esc_attr($min); ?>" min="0" step="1" class="small-text" />
    </p>
    <p>
        <label for="kaupang_attribute_suite_altitude_max"><?php esc_html_e('Altitude max', 'kaupang-attribute-suite'); ?></label><br />
        <input type="number" id="kaupang_attribute_suite_altitude_max" name="kaupang_attribute_suite_origin[altitude][max]"
               value="<?php echo esc_attr($max); ?>" min="0" step="1" class="small-text" />
    </p>
    <p>
        <label for="kaupang_attribute_suite_altitude_unit"><?php esc_html_e('Unit', 'kaupang-attribute-suite'); ?></label><br />
        <select id="kaupang_attribute_suite_altitude_unit" name="kaupang_attribute_suite_origin[altitude][unit]">
            <option value="m" <?php selected($unit, 'm'); ?>>m</option>
            <option value="ft" <?php selected($unit, 'ft'); ?>>ft</option>
        </select>
    </p>
    <p class="description"><?php esc_html_e('Stored for future use — not yet rendered on the frontend.', 'kaupang-attribute-suite'); ?></p>
    <?php
}

/**
 * References box.
 */
function kaupang_attribute_suite_origin_render_refs_box($post) {
    kaupang_attribute_suite_origin_render_nonce($post);
    $id_archivo = get_post_meta($post->ID, 'id_archivo', true);
    ?>
    <p>
        <label for="kaupang_attribute_suite_id_archivo"><?php esc_html_e('Archive ID', 'kaupang-attribute-suite'); ?></label><br />
        <input type="text" id="kaupang_attribute_suite_id_archivo" name="kaupang_attribute_suite_origin[id_archivo]"
               value="<?php echo esc_attr($id_archivo); ?>" class="regular-text" />
    </p>
    <p class="description"><?php esc_html_e('External archive identifier (e.g., Silva GT-047).', 'kaupang-attribute-suite'); ?></p>
    <?php
}

/**
 * Save origin meta from the grouped boxes.
 *
 * @param int $post_id Post ID.
 */
function kaupang_attribute_suite_origin_save_meta($post_id) {
    if (!isset($_POST['kaupang_attribute_suite_origin_meta_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['kaupang_attribute_suite_origin_meta_nonce'], 'kaupang_attribute_suite_origin_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (get_post_type($post_id) !== 'attribute_page') {
        return;
    }

    $input = isset($_POST['kaupang_attribute_suite_origin']) && is_array($_POST['kaupang_attribute_suite_origin'])
        ? wp_unslash($_POST['kaupang_attribute_suite_origin'])
        : array();

    // variety — string
    if (array_key_exists('variety', $input)) {
        $v = sanitize_text_field($input['variety']);
        if ($v === '') {
            delete_post_meta($post_id, 'variety');
        } else {
            update_post_meta($post_id, 'variety', $v);
        }
    }

    // producer_type — enum
    if (array_key_exists('producer_type', $input)) {
        $v = kaupang_attribute_suite_sanitize_producer_type($input['producer_type']);
        if ($v === '') {
            delete_post_meta($post_id, 'producer_type');
        } else {
            update_post_meta($post_id, 'producer_type', $v);
        }
    }

    // producer_count — nullable int
    if (array_key_exists('producer_count', $input)) {
        $v = kaupang_attribute_suite_sanitize_nullable_int($input['producer_count']);
        if ($v === '' || $v === 0) {
            delete_post_meta($post_id, 'producer_count');
        } else {
            update_post_meta($post_id, 'producer_count', $v);
        }
    }

    // fermentation_type — enum
    if (array_key_exists('fermentation_type', $input)) {
        $v = kaupang_attribute_suite_sanitize_fermentation_type($input['fermentation_type']);
        if ($v === '') {
            delete_post_meta($post_id, 'fermentation_type');
        } else {
            update_post_meta($post_id, 'fermentation_type', $v);
        }
    }

    // fermentation_days — nullable int
    if (array_key_exists('fermentation_days', $input)) {
        $v = kaupang_attribute_suite_sanitize_nullable_int($input['fermentation_days']);
        if ($v === '' || $v === 0) {
            delete_post_meta($post_id, 'fermentation_days');
        } else {
            update_post_meta($post_id, 'fermentation_days', $v);
        }
    }

    // fermentation_method — free text
    if (array_key_exists('fermentation_method', $input)) {
        $v = sanitize_text_field($input['fermentation_method']);
        if ($v === '') {
            delete_post_meta($post_id, 'fermentation_method');
        } else {
            update_post_meta($post_id, 'fermentation_method', $v);
        }
    }

    // drying_method — free text
    if (array_key_exists('drying_method', $input)) {
        $v = sanitize_text_field($input['drying_method']);
        if ($v === '') {
            delete_post_meta($post_id, 'drying_method');
        } else {
            update_post_meta($post_id, 'drying_method', $v);
        }
    }

    // taste_profile — object, axes from filter, clamped 0–10
    if (array_key_exists('taste_profile', $input)) {
        $v = kaupang_attribute_suite_sanitize_taste_profile($input['taste_profile']);
        // Remove axes where user left the field blank so null doesn't pollute storage
        $clean = array();
        foreach ($v as $axis => $score) {
            if ($score !== null) {
                $clean[$axis] = $score;
            }
        }
        if (empty($clean)) {
            delete_post_meta($post_id, 'taste_profile');
        } else {
            update_post_meta($post_id, 'taste_profile', $clean);
        }
    }

    // altitude — object
    if (array_key_exists('altitude', $input)) {
        $v = kaupang_attribute_suite_sanitize_altitude($input['altitude']);
        if (empty($v)) {
            delete_post_meta($post_id, 'altitude');
        } else {
            update_post_meta($post_id, 'altitude', $v);
        }
    }

    // id_archivo — string
    if (array_key_exists('id_archivo', $input)) {
        $v = sanitize_text_field($input['id_archivo']);
        if ($v === '') {
            delete_post_meta($post_id, 'id_archivo');
        } else {
            update_post_meta($post_id, 'id_archivo', $v);
        }
    }
}
add_action('save_post_attribute_page', 'kaupang_attribute_suite_origin_save_meta');
