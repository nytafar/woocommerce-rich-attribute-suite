# WooCommerce Backend Development Skill

This skill guides developers in creating and modifying WooCommerce backend PHP code according to project standards.

## Primary Use Cases

Invoke this skill before:
- Writing PHP unit tests
- Creating new PHP classes
- Modifying existing backend code
- Adding hooks or filters

## Key Guidelines

Follow these reference documents:

1. **File organization** — conventions for structuring classes and files (file-entities.md)
2. **Naming conventions** — standards for methods, variables, and parameters (code-entities.md)
3. **Coding style** — WordPress Coding Standards compliance (coding-conventions.md)
4. **Type annotations** — PHPStan-compatible PHPDoc usage (type-annotations.md)
5. **Hooks implementation** — callback conventions and documentation (hooks.md)
6. **Dependency injection** — DI container patterns (dependency-injection.md)
7. **Data integrity** — CRUD operation safeguards (data-integrity.md)
8. **Unit testing** — test writing conventions (unit-tests.md)

## Core Principles

- Always follow WordPress Coding Standards
- Use class methods instead of standalone functions
- Place internal classes in `src/Internal/` with PSR-4 autoloading under the `Automattic\WooCommerce` namespace
