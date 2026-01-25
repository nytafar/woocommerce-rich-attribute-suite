<?php
/**
 * Frontend Hooks
 *
 * Handles frontend integration for attribute pages.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all frontend functionality: attribute archives, template overrides,
 * variation meta, and script enqueueing.
 *
 * @since 1.0.0
 */
class WC_RAS_Frontend {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.3.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'handle_enable_attribute_archives' ), 1 );
		add_action( 'init', array( $this, 'handle_check_rewrite_rules_version' ), 98 );
		add_action( 'init', array( $this, 'handle_maybe_flush_rewrite_rules' ), 99 );
		add_filter( 'template_include', array( $this, 'handle_override_template' ), 99 );
		add_filter( 'woocommerce_taxonomy_archive_description', array( $this, 'handle_add_content_to_archive' ), 20 );
		add_filter( 'woocommerce_available_variation', array( $this, 'handle_add_attribute_meta_to_variation' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'handle_enqueue_variation_scripts' ) );
		add_action( 'plugins_loaded', array( $this, 'handle_create_assets_directory' ) );
	}

	/**
	 * Enable public archives for WooCommerce product attribute taxonomies.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_enable_attribute_archives() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $tax->attribute_name );
			add_filter( "woocommerce_taxonomy_args_{$taxonomy_name}", array( $this, 'handle_filter_taxonomy_args' ), 10, 1 );
		}
	}

	/**
	 * Filter attribute taxonomy args to enable public archives.
	 *
	 * @internal
	 *
	 * @param array $args Taxonomy registration args.
	 *
	 * @return array Modified args.
	 *
	 * @since 1.0.0
	 */
	public function handle_filter_taxonomy_args( $args ) {
		$args['public']    = true;
		$args['query_var'] = true;

		if ( empty( $args['rewrite'] ) || false === $args['rewrite'] ) {
			$current_filter = current_filter();
			$taxonomy_name  = str_replace( 'woocommerce_taxonomy_args_', '', $current_filter );
			$attribute_name = str_replace( 'pa_', '', $taxonomy_name );

			$permalinks = wc_get_permalink_structure();
			$base_slug  = ! empty( $permalinks['attribute_rewrite_slug'] ) ? $permalinks['attribute_rewrite_slug'] : '';

			$args['rewrite'] = array(
				'slug'         => trailingslashit( $base_slug ) . urldecode( sanitize_title( $attribute_name ) ),
				'with_front'   => false,
				'hierarchical' => true,
			);
		}

		return $args;
	}

	/**
	 * Flush rewrite rules when plugin is activated or settings change.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_maybe_flush_rewrite_rules() {
		if ( get_option( 'wc_ras_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'wc_ras_flush_rewrite_rules' );
		}
	}

	/**
	 * Check if rewrite rules need to be flushed for attribute archives.
	 *
	 * @internal
	 *
	 * @since 1.2.0
	 */
	public function handle_check_rewrite_rules_version() {
		$current_version = get_option( 'wc_ras_rewrite_version', '0' );

		if ( version_compare( $current_version, '1.2.0', '<' ) ) {
			update_option( 'wc_ras_flush_rewrite_rules', true );
			update_option( 'wc_ras_rewrite_version', WC_RAS_VERSION );
		}
	}

	/**
	 * Check if current page is a product attribute archive.
	 *
	 * @return bool True if current page is a product attribute taxonomy.
	 *
	 * @since 1.0.0
	 */
	public static function is_product_attribute() {
		if ( ! is_tax() ) {
			return false;
		}

		$queried = get_queried_object();
		return $queried && isset( $queried->taxonomy ) && 0 === strpos( $queried->taxonomy, 'pa_' );
	}

	/**
	 * Override WooCommerce attribute archive template.
	 *
	 * @internal
	 *
	 * @param string $template Original template path.
	 *
	 * @return string Modified template path.
	 *
	 * @since 1.0.0
	 */
	public function handle_override_template( $template ) {
		if ( ! is_tax() || ! self::is_product_attribute() ) {
			return $template;
		}

		$term           = get_queried_object();
		$attribute_page = WC_RAS_Attribute_Page_CPT::get_attribute_page( $term->slug );

		if ( ! $attribute_page ) {
			return $template;
		}

		$theme_template = locate_template(
			array(
				'woocommerce/taxonomy-' . $term->taxonomy . '.php',
				'taxonomy-' . $term->taxonomy . '.php',
			)
		);

		if ( $theme_template ) {
			return $theme_template;
		}

		$plugin_template = WC_RAS_PLUGIN_DIR . 'templates/taxonomy-product-attribute.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		return $template;
	}

	/**
	 * Add attribute page content to archive description.
	 *
	 * @internal
	 *
	 * @param string $description Original archive description.
	 *
	 * @return string Modified archive description with rich content.
	 *
	 * @since 1.0.0
	 */
	public function handle_add_content_to_archive( $description ) {
		if ( ! is_tax() || ! self::is_product_attribute() ) {
			return $description;
		}

		$term           = get_queried_object();
		$attribute_page = WC_RAS_Attribute_Page_CPT::get_attribute_page( $term->slug );

		if ( ! $attribute_page ) {
			return $description;
		}

		$rich_content = apply_filters( 'the_content', $attribute_page->post_content );
		$region       = get_post_meta( $attribute_page->ID, 'region', true );
		$smak         = get_post_meta( $attribute_page->ID, 'smak', true );

		$content = '';

		if ( ! empty( $term->description ) ) {
			$content .= '<div class="term-description">' . wpautop( wptexturize( $term->description ) ) . '</div>';
		}

		if ( ! empty( $region ) || ! empty( $smak ) ) {
			$content .= '<div class="attribute-meta">';

			if ( ! empty( $region ) ) {
				$content .= '<div class="attribute-region"><strong>' . esc_html__( 'Region:', 'wc-rich-attribute-suite' ) . '</strong> ' . esc_html( $region ) . '</div>';
			}

			if ( ! empty( $smak ) ) {
				$content .= '<div class="attribute-smak"><strong>' . esc_html__( 'Smak:', 'wc-rich-attribute-suite' ) . '</strong> ' . esc_html( $smak ) . '</div>';
			}

			$content .= '</div>';
		}

		$content .= '<div class="attribute-rich-content">' . $rich_content . '</div>';

		return $content;
	}

	/**
	 * Get attribute meta for a specific variation.
	 *
	 * @param int    $variation_id Variation ID.
	 * @param string $attribute    Attribute taxonomy name (e.g. 'pa_color'). If empty,
	 *                             uses the first attribute with an attribute page.
	 *
	 * @return array Attribute metadata.
	 *
	 * @since 1.0.0
	 */
	public function get_attribute_meta_for_variation( $variation_id, $attribute = '' ) {
		/**
		 * Filter the default attribute to use for variation meta lookup.
		 *
		 * @since 1.3.0
		 *
		 * @param string $attribute    The attribute taxonomy name.
		 * @param int    $variation_id The variation ID.
		 */
		$attribute = apply_filters( 'wc_ras_variation_meta_attribute', $attribute, $variation_id );
		$product = wc_get_product( $variation_id );

		if ( ! $product || ! $product->is_type( 'variation' ) ) {
			return array();
		}

		$attributes = $product->get_attributes();

		// If no specific attribute provided, find the first one with an attribute page.
		if ( empty( $attribute ) ) {
			foreach ( $attributes as $attr_name => $attr_value ) {
				if ( empty( $attr_value ) ) {
					continue;
				}
				$taxonomy = str_replace( 'attribute_', '', $attr_name );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$term = get_term_by( 'slug', $attr_value, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$page = WC_RAS_Attribute_Page_CPT::get_attribute_page( $term->slug );
					if ( $page ) {
						$attribute = $taxonomy;
						break;
					}
				}
			}
		}

		if ( empty( $attribute ) || ! isset( $attributes[ $attribute ] ) ) {
			return array();
		}

		$attribute_value = $attributes[ $attribute ];
		$term            = get_term_by( 'slug', $attribute_value, $attribute );

		if ( ! $term ) {
			return array();
		}

		$attribute_page = WC_RAS_Attribute_Page_CPT::get_attribute_page( $term->slug );

		if ( ! $attribute_page ) {
			return array();
		}

		return array(
			'region' => get_post_meta( $attribute_page->ID, 'region', true ),
			'smak'   => get_post_meta( $attribute_page->ID, 'smak', true ),
		);
	}

	/**
	 * Add attribute meta to variation data.
	 *
	 * @internal
	 *
	 * @param array                $data      Variation data.
	 * @param WC_Product_Variable  $product   Product object.
	 * @param WC_Product_Variation $variation Variation object.
	 *
	 * @return array Modified variation data.
	 *
	 * @since 1.0.0
	 */
	public function handle_add_attribute_meta_to_variation( $data, $product, $variation ) {
		$attribute_meta = $this->get_attribute_meta_for_variation( $variation->get_id() );

		if ( ! empty( $attribute_meta ) ) {
			foreach ( $attribute_meta as $key => $value ) {
				if ( ! empty( $value ) ) {
					$data[ 'attribute_' . $key ] = $value;
				}
			}
		}

		return $data;
	}

	/**
	 * Enqueue variation meta display scripts on product pages.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_enqueue_variation_scripts() {
		if ( ! is_product() || ! apply_filters( 'wc_ras_enable_variation_meta_display', false ) ) {
			return;
		}

		wp_enqueue_script(
			'wc-ras-variation-display',
			WC_RAS_PLUGIN_URL . 'assets/js/variation-display.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			WC_RAS_VERSION,
			true
		);
	}

	/**
	 * Create assets directory if it doesn't exist.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_create_assets_directory() {
		$js_dir = WC_RAS_PLUGIN_DIR . 'assets/js';
		if ( ! file_exists( $js_dir ) ) {
			wp_mkdir_p( $js_dir );
		}
	}
}
