# General Coding Conventions

## Core Principles

- Write self-explanatory code. Use comments sparingly — only for non-obvious insights.
- Comments should address unusual decisions, workarounds, or performance considerations rather than restating what the code obviously does.
- Follow WordPress Coding Standards, including Yoda conditions (`'true' === $value`), proper spacing around operators, and snake_case naming conventions.

## Operator Usage

- Null coalescing `??` is preferred for safe array access over traditional `isset()` checks.
- Simple conditional assignments should use the ternary form, though traditional if-else structures remain appropriate for complex logic.

## Function Calls

When using `call_user_func_array()`, pass arguments as numerically indexed arrays rather than associative arrays.

## Maintenance Standards

Linting fixes should target only code modified within the current branch, preserving existing codebases unless explicitly requested otherwise.
