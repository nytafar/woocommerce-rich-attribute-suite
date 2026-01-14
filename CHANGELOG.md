# Changelog

All notable changes to the WooCommerce Rich Attribute Suite plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2025-01-14

### Added
- **Public Attribute Archives**: Automatically enables public archives for all WooCommerce product attribute taxonomies
  - Ensures `get_term_link()` returns proper permalink URLs instead of query-string URLs
  - Sets up rewrite rules for attribute term archives
  - Includes automatic rewrite flush on plugin activation
- **Term List Enhancements**: New columns in attribute term list tables (Products → Attributes → Configure terms)
  - Description column showing truncated term descriptions
  - Rich Content column with Edit/Create buttons for attribute pages
- **Quick Edit Description**: Edit term descriptions directly from the term list using Quick Edit
  - Adds description textarea to the quick edit form
  - Pre-populates with existing description
- **Improved Attribute Page Creation**: When creating an attribute page from the term edit screen
  - Title is pre-filled with the term name
  - Post slug is automatically set to match the term slug
  - Term linkage metadata is properly saved

### Technical
- New function `wc_ras_enable_attribute_archives()` to filter taxonomy registration
- New function `wc_ras_prefill_attribute_page_from_url()` for handling URL parameters
- New function `wc_ras_save_attribute_page_term_link()` for saving term linkage on manual creation
- New JavaScript `assets/js/admin-quick-edit.js` for quick edit description support
- Deferred rewrite flush using transient option

## [1.1.0] - 2025-01-14

### Added
- **Inline Variation Description**: New feature that renders variation descriptions directly within the variations table, eliminating Cumulative Layout Shift (CLS) and DOM manipulation issues
  - Must be explicitly enabled by theme using `add_filter('wc_ras_enable_inline_variation_description', '__return_true')`
  - Auto-detects which attribute has term descriptions, or can be configured via `wc_ras_inline_variation_description_config` filter
  - Overrides WooCommerce's variation template to remove default description div
  - Uses JavaScript to update inline content on variation change without DOM manipulation
- New filter hooks:
  - `wc_ras_enable_inline_variation_description` - Enable/disable inline description (default: false)
  - `wc_ras_inline_variation_description_config` - Configure target attribute and auto-detection
  - `wc_ras_inline_description_animation_duration` - Customize show/hide animation duration

### Technical
- New class `WC_RAS_Inline_Variation_Description` in `includes/inline-variation-description.php`
- New template `templates/variation-no-description.php` based on WooCommerce 9.3.0
- New JavaScript `assets/js/inline-variation-description.js` for handling variation updates

## [1.0.0] - 2025-05-12

### Added
- Initial release of WooCommerce Rich Attribute Suite
- Custom Post Type (CPT) for attribute pages with full block editor support
- Automatic content linkage between attribute terms and CPT by slug
- Meta fields for region and taste profile (smak)
- Admin UI integration with links between term edit screen and content pages
- Custom columns in admin list view showing attribute metadata
- Frontend template override for attribute archives
- JavaScript for displaying attribute metadata on product variation changes
- Performance optimization with object caching for all attribute page lookups
- Full WooCommerce compatibility with attribute archives
- Support for all product attribute taxonomies
- Variation-level access to attribute metadata

### Technical
- Implemented helper function `wc_ras_is_product_attribute()` for detecting attribute taxonomy pages
- Added object cache integration for attribute page lookups
- Created extensible architecture with developer hooks for custom meta fields
- Implemented meta boxes for attribute properties in the admin interface
- Added template overrides with fallback to theme templates

## [1.0.1]

### Added
- Variation description fallback feature that uses attribute term descriptions when variation descriptions are empty
- Support for Mix and Match products to display attribute term descriptions
- Multiple filter hooks to enable/disable specific variation improvement features:
  - `wc_ras_enable_variation_improvements` - Master toggle for all variation improvements
  - `wc_ras_enable_variation_description_fallback` - Toggle for description fallback feature
  - `wc_ras_enable_mnm_description_support` - Toggle for Mix and Match support
  - `wc_ras_show_variation_description_links` - Toggle for "Learn more" links in descriptions
  - `wc_ras_combine_all_term_descriptions` - Toggle for combining multiple term descriptions

## [1.0.2] - 2025-05-13

### Added
- New hook `wc_ras_enable_variation_meta_display` to control whether variation meta fields are displayed in product summary (disabled by default)

### Fixed
- Fixed issue where variation meta display was not properly controlled by hooks
- Improved prioritization for variation descriptions (variation description → term description → attribute page content)
- Fixed newly created attributes to use term description instead of attribute page content
- Removed unnecessary wrapper div around attribute page content in taxonomy template

### Planned Features
- Settings page for configuring default behavior
- Additional meta fields for different attribute types
- Template selection for different attribute taxonomies
- Enhanced styling options for attribute metadata display
- Import/export functionality for attribute content
- Analytics integration for attribute page views
