# Type Annotations for Static Analysis

## Key Purposes

Use PHPStan-specific annotations beyond standard PHPDoc tags when a method returns a type based on its input (generic/template types) or when standard documentation cannot express type relationships.

## Main Techniques

- **Template Declarations:** `@template` keyword establishes generic type parameters
- **Dual Annotations:** Use both `@param` for general documentation and `@phpstan-param` for richer type information
- **Common Applications:** Factory methods, service locators, and typed collections

## Error Management

When PHPStan flags correct code incorrectly, use inline ignore comments like `@phpstan-ignore return.type` with explanatory notes. Prefer proper type annotation fixes over suppression.
