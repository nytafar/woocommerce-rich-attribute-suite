<?php
/**
 * The Template for displaying product attribute archives
 *
 * This template overrides WooCommerce's default attribute archive template
 * to enable rich content display from attribute_page CPT.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */

defined('ABSPATH') || exit;

get_header('shop');

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
 * @hooked woocommerce_breadcrumb - 20
 */
do_action('woocommerce_before_main_content');

// Get the current term
$term = get_queried_object();

// Get matching attribute page
$attribute_page = wc_ras_get_cached_attribute_page($term->slug);
?>

<header class="woocommerce-products-header">
    <h1 class="woocommerce-products-header__title page-title"><?php echo esc_html($term->name); ?></h1>

    <?php if ($attribute_page) : ?>
        <?php
        // Get metadata
        $region = get_post_meta($attribute_page->ID, 'region', true);
        $smak = get_post_meta($attribute_page->ID, 'smak', true);
        ?>

        <?php if (!empty($region) || !empty($smak)) : ?>
            <div class="attribute-meta-details">
                <?php if (!empty($region)) : ?>
                    <div class="attribute-region">
                        <strong><?php esc_html_e('Region:', 'wc-rich-attribute-suite'); ?></strong> 
                        <?php echo esc_html($region); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($smak)) : ?>
                    <div class="attribute-smak">
                        <strong><?php esc_html_e('Smak:', 'wc-rich-attribute-suite'); ?></strong> 
                        <?php echo esc_html($smak); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($term->description)) : ?>
            <div class="term-description">
                <?php echo wpautop(wptexturize($term->description)); ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <?php if (!empty($term->description)) : ?>
            <div class="term-description">
                <?php echo wpautop(wptexturize($term->description)); ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</header>

<?php if ($attribute_page && !empty($attribute_page->post_content)) : ?>
<article class="attribute-page-content">
    <?php echo apply_filters('the_content', $attribute_page->post_content); ?>
</article>
<?php endif; ?>

<section class="attribute-products-section">
    <h2 class="products-made-with-title"><?php printf(esc_html__('Products made with %s', 'wc-rich-attribute-suite'), esc_html($term->name)); ?></h2>

    <?php
    // If this is a product attribute archive, display the products
    if (is_tax() && wc_ras_is_product_attribute()) {
        if (woocommerce_product_loop()) {
            /**
             * Hook: woocommerce_before_shop_loop.
             *
             * @hooked woocommerce_output_all_notices - 10
             * @hooked woocommerce_result_count - 20
             * @hooked woocommerce_catalog_ordering - 30
             */
            do_action('woocommerce_before_shop_loop');

            woocommerce_product_loop_start();

            if (wc_get_loop_prop('total')) {
                while (have_posts()) {
                    the_post();

                    /**
                     * Hook: woocommerce_shop_loop.
                     */
                    do_action('woocommerce_shop_loop');

                    wc_get_template_part('content', 'product');
                }
            }

            woocommerce_product_loop_end();

            /**
             * Hook: woocommerce_after_shop_loop.
             *
             * @hooked woocommerce_pagination - 10
             */
            do_action('woocommerce_after_shop_loop');
        } else {
            /**
             * Hook: woocommerce_no_products_found.
             *
             * @hooked wc_no_products_found - 10
             */
            do_action('woocommerce_no_products_found');
        }
    }
    ?>
</section>

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('woocommerce_after_main_content');

/**
 * Hook: woocommerce_sidebar.
 *
 * @hooked woocommerce_get_sidebar - 10
 */
do_action('woocommerce_sidebar');

get_footer('shop');
