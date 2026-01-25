# Unit Testing Conventions

## File Structure & Naming

- Tests must use `declare(strict_types = 1)` at the start
- Follow namespace patterns matching source locations
- Files in `src/` get appended with `Test`, while `includes/` classes use `-test` suffix with hyphens

## System Under Test Variable

All test classes should declare: `private $sut;` with documentation stating "The System Under Test."

## Documentation Standards

Test methods require `@testdox` annotations with proper punctuation.

## Code Style

Rely on test names and assertion messages to convey intent rather than over-commenting.

## Specialized Testing Patterns

- **Data providers** enable testing multiple scenarios efficiently
- **Fake loggers** should implement `WC_Logger_Interface` and be injected via the `woocommerce_logging_class` filter
- Always clean up filters post-test with `remove_all_filters()`

## Key Assertion Guidance

Include contextual messages with assertions to help diagnose failures, such as: `'Draft orders should be deletable'`
