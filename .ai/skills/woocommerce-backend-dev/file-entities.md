# File Entities

## Fundamental Rule: No Standalone Functions

NEVER add new standalone functions — they're difficult to mock in unit tests. Always use class methods.

Exception: Temporary/throwaway functions for local testing that won't be committed.

## Adding New Classes

### Default Location: `src/Internal/`

New classes go in `src/Internal/` by default.

### Public Classes: `src/`

Only when the class is a "public" class should the file go in `src/` but not in `Internal`.

## Naming Conventions

### Class Names

- Must be PascalCase
- Must follow PSR-4 standard
- Root namespace for the `src` directory is `Automattic\WooCommerce`

## Namespace and Import Conventions

When referencing a namespaced class:

1. Always add a `use` statement with the fully qualified class name at the beginning of the file
2. Reference the short class name throughout the code
