# Changelog

All notable changes to the WooCommerce Rich Attribute Suite plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

## [Unreleased]

### Planned Features
- Settings page for configuring default behavior
- Additional meta fields for different attribute types
- Template selection for different attribute taxonomies
- Enhanced styling options for attribute metadata display
- Import/export functionality for attribute content
- Analytics integration for attribute page views
