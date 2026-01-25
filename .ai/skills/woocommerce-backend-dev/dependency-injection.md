# Dependency Injection

## Key Pattern

Dependencies are injected via a `final` `init` method with `@internal` annotation (blank lines before/after).

## Hook Initialization

Classes that establish hooks should be instantiated in the main plugin class within the `init_hooks()` method using `$container->get( ClassName::class )`.

## Container Usage

Retrieve instances via: `wc_get_container()->get( ClassName::class )`

## Singleton Pattern

The container always retrieves the same instance of a given class (singleton pattern). Create new instances only when multiple distinct objects are necessary.
