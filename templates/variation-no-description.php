<?php
/**
 * Single variation display (without description)
 *
 * This template is used when inline variation description is enabled.
 * It removes the description div from the default WooCommerce template
 * since the description is rendered inline within the variations table.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce_Rich_Attribute_Suite
 * @version 1.1.0
 * @since 1.1.0
 * 
 * Based on WooCommerce template version 9.3.0
 */

defined( 'ABSPATH' ) || exit;

?>
<script type="text/template" id="tmpl-variation-template">
	<div class="woocommerce-variation-price">{{{ data.variation.price_html }}}</div>
	<div class="woocommerce-variation-availability">{{{ data.variation.availability_html }}}</div>
</script>
<script type="text/template" id="tmpl-unavailable-variation-template">
	<p role="alert"><?php esc_html_e( 'Sorry, this product is unavailable. Please choose a different combination.', 'woocommerce' ); ?></p>
</script>
