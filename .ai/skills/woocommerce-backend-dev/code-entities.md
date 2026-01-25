# Code Entities

## Naming

All methods, variables, and hooks should use snake_case formatting rather than camelCase or PascalCase.

## Visibility Hierarchy

Methods default to `private`, use `protected` when child classes will inherit them, and reserve `public` for external API calls.

## Static Methods

Pure functions — those depending only on their parameters — must be declared `static`. Methods accessing databases, system time, or object state should remain instance methods.

## Documentation Standards

- All hooks and methods require concise docblocks (ideally one line)
- Public and protected methods need `@since` annotations indicating the next release version
- The `@since` tag must appear last, preceded by a blank comment line
- Private methods and internal callbacks skip the `@since` requirement
- When using `@internal` annotations, place them after descriptions but before parameter lists
