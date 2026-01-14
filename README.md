# WooCommerce Rich Attribute Suite

![WooCommerce Rich Attribute Suite](https://img.shields.io/badge/WooCommerce-Rich%20Attribute%20Suite-7f54b3.svg)
![Version 1.1.0](https://img.shields.io/badge/Version-1.1.0-brightgreen.svg)
![WooCommerce 6.0+](https://img.shields.io/badge/WooCommerce-6.0+-a46497.svg)
![PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-8892BF.svg)

Enhance WooCommerce product attribute taxonomy pages with rich, translatable, and fully editable content using native WordPress tools — without any external dependencies or plugins.

## 🎯 Purpose

WooCommerce Rich Attribute Suite transforms standard attribute taxonomy pages into rich content experiences. It creates a seamless bridge between WooCommerce's attribute system and WordPress's powerful content editing capabilities.

## ✨ Features

- **Block Editor Support**: Full Gutenberg editing for each attribute term
- **Automatic Content Linkage**: CPT attribute_page automatically matches attribute terms by slug
- **Meta Fields per Term**: Add native fields like region, smak, etc., stored in the CPT
- **Archive Override**: Attribute archives (e.g., `/opprinnelse/colombia-betulia/`) display the matching CPT
- **Admin UX Integration**: Link from term edit screen to its content page, list meta in CPT admin list
- **Translatable**: Fully compatible with WPML/Polylang, matches content by slug per language
- **Variation-level Access**: Fetch region/smak on a product variation using linked attribute page
- **Performance-optimized**: All lookups cacheable using wp_cache_get() for high-scale environments
- **Variation Description Fallback**: Automatically use attribute term descriptions when variation descriptions are empty
- **Mix and Match Support**: Extends description fallback to WooCommerce Mix and Match products
- **Inline Variation Description**: Render descriptions directly in the variations table, eliminating CLS and DOM manipulation issues

## 🔧 Technical Design

| Area | Implementation |
|------|----------------|
| Term → Content Mapping | Slug-matched CPT (attribute_page) |
| Content Storage | post_content + native post_meta |
| Performance | Object cache integration for all lookups |
| Editing UI | Block editor + meta box or custom fields |
| URL structure | Uses native attribute term archive URLs |
| Extendability | Developers can register new meta fields or templates via hooks |

## 🛠️ Installation

1. Upload the `woocommerce-rich-attribute-suite` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Products → Attributes and ensure you have at least one attribute with "Enable archives" checked
4. Edit an attribute term to access its rich content page

## 📋 Requirements

- WordPress 6.x or newer (block editor enabled)
- WooCommerce 6.0 or newer
- PHP 7.4+ (recommended 8.x)
- Object cache (optional, but performance boost)

## 🔌 Usage

### For Store Administrators

1. **Edit Attribute Content**: Go to Products → Attributes → [Your Attribute] → [Term] → Edit, then click "Edit Rich Content"
2. **Add Meta Information**: Use the "Attribute Properties" meta box to add region, taste profiles, etc.
3. **Design Rich Content**: Use the full power of the WordPress block editor to create compelling attribute pages

### For Developers

#### Adding Custom Meta Fields

```php
// Register a new meta field for attribute pages
add_action('wc_ras_register_attribute_page_meta_fields', function($post_type) {
    register_post_meta($post_type, 'altitude', [
        'type' => 'string',
        'description' => 'Altitude information',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
    ]);
});

// Add the field to the meta box
add_action('wc_ras_attribute_page_meta_box_fields', function($post) {
    $altitude = get_post_meta($post->ID, 'altitude', true);
    ?>
    <tr>
        <th><label for="wc_ras_altitude">Altitude</label></th>
        <td>
            <input type="text" id="wc_ras_altitude" name="wc_ras_altitude" 
                   value="<?php echo esc_attr($altitude); ?>" class="regular-text">
        </td>
    </tr>
    <?php
});

// Save the field
add_action('wc_ras_save_attribute_page_meta', function($post_id) {
    if (isset($_POST['wc_ras_altitude'])) {
        update_post_meta($post_id, 'altitude', sanitize_text_field($_POST['wc_ras_altitude']));
    }
});
```

#### Enabling Inline Variation Description

The inline variation description feature renders descriptions directly within the variations table, eliminating Cumulative Layout Shift (CLS) and DOM manipulation issues. This feature must be explicitly enabled by the theme.

```php
// Enable inline variation description (add to theme's functions.php)
add_filter('wc_ras_enable_inline_variation_description', '__return_true');

// Optional: Configure which attribute to show description after
add_filter('wc_ras_inline_variation_description_config', function($config) {
    return array(
        // Specify attribute taxonomy, or leave empty for auto-detection
        'target_attribute' => 'pa_opprinnelse',
        // Auto-detect first attribute with term descriptions
        'auto_detect' => true,
    );
});

// Optional: Customize animation duration (in milliseconds)
add_filter('wc_ras_inline_description_animation_duration', function() {
    return 150; // Faster animation
});
```

**How it works:**
1. The plugin injects a hidden row into the variations table after the target attribute
2. WooCommerce's default variation template is overridden to remove the description div
3. When a variation is selected, JavaScript updates the inline row content
4. No DOM manipulation or element moving required - eliminates CLS completely

**Important:** When enabling this feature, remove any existing theme JavaScript that moves or clones the variation description element. The plugin handles everything automatically.

## 🧩 Hooks and Filters

### Attribute Page Hooks

| Hook | Description |
|------|-------------|
| `wc_ras_register_attribute_page_meta_fields` | Register additional meta fields for attribute pages |
| `wc_ras_attribute_page_meta_box_fields` | Add fields to the attribute page meta box |
| `wc_ras_save_attribute_page_meta` | Process additional meta fields when saving |

### Variation Improvement Hooks

| Hook | Description |
|------|-------------|
| `wc_ras_enable_variation_improvements` | Master toggle for all variation improvements (default: true) |
| `wc_ras_enable_variation_description_fallback` | Enable/disable description fallback feature (default: true) |
| `wc_ras_enable_mnm_description_support` | Enable/disable Mix and Match support (default: true) |
| `wc_ras_show_variation_description_links` | Show/hide "Learn more" links in descriptions (default: true) |
| `wc_ras_combine_all_term_descriptions` | Combine multiple term descriptions instead of using just the first one (default: false) |
| `wc_ras_enable_variation_meta_display` | Enable/disable display of variation meta (region, smak) in the product summary (default: false) |
| `wc_ras_enable_inline_variation_description` | Enable inline description rendering in variations table (default: false, must be enabled by theme) |
| `wc_ras_inline_variation_description_config` | Configure inline description behavior (target_attribute, auto_detect) |
| `wc_ras_inline_description_animation_duration` | Animation duration in ms for showing/hiding inline description (default: 200) |

## 🔄 Compatibility

- **Multilingual Plugins**: Compatible with WPML and Polylang
- **Cache Plugins**: Works with object cache implementations (Redis, Memcached)
- **Theme Compatibility**: Works with any WooCommerce-compatible theme

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed history of changes.

## 👨‍💻 Author

**Lasse Jellum** - [jellum.net](https://jellum.net)

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.
