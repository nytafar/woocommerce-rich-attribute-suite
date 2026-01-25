# Working with Hooks

## Hook Callback Naming Convention

Hook callback methods follow the pattern `handle_{hook_name}` and require the `@internal` annotation.

## Hook Docblocks

When modifying a line that fires a hook lacking documentation:

1. Create a docblock with a description and parameter documentation
2. Use `git log -S "hook_name"` to determine when it was introduced
3. Include a `@since` annotation with that version number

## Hook Documentation Requirements

All hooks must include complete docblocks containing:

- **Description**: When the hook executes
- **Parameters**: `@param` tags for each argument
- **Version**: `@since` annotation (positioned last with a blank line before it)

**Action hook template:**
```php
/**
 * Fires after a product is saved.
 *
 * @param int        $product_id The product ID.
 * @param WC_Product $product    The product object.
 *
 * @since 9.5.0
 */
do_action( 'woocommerce_product_saved', $product_id, $product );
```

**Filter hook template:**
```php
/**
 * Filters the product price before display.
 *
 * @param string     $price   The formatted price.
 * @param WC_Product $product The product object.
 *
 * @since 9.5.0
 */
$price = apply_filters( 'woocommerce_product_price', $price, $product );
```
