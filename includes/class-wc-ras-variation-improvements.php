<?php
/**
 * Variation Improvements
 *
 * Enhances WooCommerce variations with additional functionality.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class for managing variation improvements.
 *
 * @since 1.0.0
 */
class WC_RAS_Variation_Improvements {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( apply_filters( 'wc_ras_enable_variation_improvements', true ) ) {
			$this->init();
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		if ( apply_filters( 'wc_ras_enable_variation_description_fallback', true ) ) {
			add_filter( 'woocommerce_available_variation', array( $this, 'variation_description_fallback' ), 10, 3 );
		}

		if ( apply_filters( 'wc_ras_enable_mnm_description_support', true ) && class_exists( 'WC_Mix_and_Match' ) ) {
			add_action( 'wc_mnm_child_item_details', array( $this, 'mnm_variation_description_support' ), 105, 2 );
		}

		if ( apply_filters( 'wc_ras_enable_variation_meta_display', false ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_variation_display_script' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
	}

	/**
	 * Register scripts without enqueueing them.
	 *
	 * @since 1.0.0
	 */
	public function register_scripts() {
		wp_register_script(
			'wc-ras-variation-display',
			WC_RAS_PLUGIN_URL . 'assets/js/variation-display.js',
			array( 'jquery', 'wc-add-to-cart-variation' ),
			WC_RAS_VERSION,
			true
		);
	}

	/**
	 * Enqueue variation display script for separate meta display.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_variation_display_script() {
		if ( ! is_product() ) {
			return;
		}

		wp_enqueue_script( 'wc-ras-variation-display' );
	}

	/**
	 * Fallback to Attribute Term Description When Variation Description Is Absent.
	 *
	 * When a variation does not have a product-level description defined, this function
	 * will provide a fallback to display the selected attribute term's description.
	 *
	 * @param array                $variation_data The variation data.
	 * @param WC_Product_Variable  $product        The parent product.
	 * @param WC_Product_Variation $variation      The variation product.
	 *
	 * @return array Modified variation data.
	 *
	 * @since 1.0.0
	 */
	public function variation_description_fallback( $variation_data, $product, $variation ) {
		if ( ! empty( $variation_data['variation_description'] ) ) {
			return $variation_data;
		}

		$attributes = $variation->get_attributes();
		if ( empty( $attributes ) ) {
			return $variation_data;
		}

		$term_data   = $this->get_term_descriptions_from_attributes( $attributes );
		$description = $this->format_description_html( $term_data['descriptions'], $term_data['links'] );

		if ( empty( $description ) ) {
			return $variation_data;
		}

		$variation_data['variation_description'] = $description;

		// Add meta to variation data for potential separate display.
		if ( ! empty( $term_data['descriptions'] ) ) {
			if ( empty( $variation_data['attribute_region'] ) && ! empty( $term_data['region'] ) ) {
				$variation_data['attribute_region'] = $term_data['region'];
			}
			if ( empty( $variation_data['attribute_smak'] ) && ! empty( $term_data['smak'] ) ) {
				$variation_data['attribute_smak'] = $term_data['smak'];
			}
		}

		return $variation_data;
	}

	/**
	 * Add support for Mix and Match products to use attribute term descriptions.
	 *
	 * @param WC_MNM_Child_Item $child_item The child item.
	 * @param WC_Product        $product    The parent product.
	 *
	 * @since 1.0.0
	 */
	public function mnm_variation_description_support( $child_item, $product ) {
		if ( ! function_exists( 'wc_string_to_bool' ) || ! wc_string_to_bool( get_option( 'wc_mnm_display_short_description', 'no' ) ) ) {
			return;
		}

		$child_product = $child_item->get_product();
		if ( ! $child_product || ! $child_product->is_type( 'variation' ) ) {
			return;
		}

		$variation_description = $child_product->get_description();

		if ( ! empty( $variation_description ) ) {
			echo '<div class="mnm-child-item-short-description">';
			echo wp_kses_post( $variation_description );
			echo '</div>';
			return;
		}

		$attributes = $child_product->get_attributes();
		if ( empty( $attributes ) ) {
			return;
		}

		$term_data   = $this->get_term_descriptions_from_attributes( $attributes );
		$description = $this->format_description_html( $term_data['descriptions'], $term_data['links'] );

		if ( empty( $description ) ) {
			return;
		}

		echo '<div class="mnm-child-item-short-description">';
		echo wp_kses_post( $description );
		echo '</div>';
	}

	/**
	 * Extract term descriptions and links from variation attributes.
	 *
	 * @since 1.3.0
	 *
	 * @param array $attributes Array of attribute name => value pairs.
	 *
	 * @return array {
	 *     @type array  $descriptions Array of term descriptions.
	 *     @type array  $links        Array of term page link HTML.
	 *     @type string $region       Region meta value (from last found attribute page).
	 *     @type string $smak         Smak meta value (from last found attribute page).
	 * }
	 */
	private function get_term_descriptions_from_attributes( $attributes ) {
		$result = array(
			'descriptions' => array(),
			'links'        => array(),
			'region'       => '',
			'smak'         => '',
		);

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			if ( empty( $attribute_value ) ) {
				continue;
			}

			$taxonomy = str_replace( 'attribute_', '', $attribute_name );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$term = get_term_by( 'slug', $attribute_value, $taxonomy );

			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}

			$term_description = trim( $term->description );

			if ( ! empty( $term_description ) ) {
				$result['descriptions'][] = $term_description;
				$result['links'][]        = $this->build_term_link( $term );
			} else {
				$attribute_page = WC_RAS_Attribute_Page_CPT::get_attribute_page( $term->slug );

				if ( $attribute_page ) {
					$content = ! empty( $attribute_page->post_excerpt ) ? $attribute_page->post_excerpt : $attribute_page->post_content;

					if ( ! empty( $content ) ) {
						$result['descriptions'][] = wp_trim_words( $content, 30, '...' );
						$result['links'][]        = '<a href="' . esc_url( get_term_link( $term ) ) . '" class="term-page-link">' .
													esc_html__( 'Learn more', 'wc-rich-attribute-suite' ) . '</a>';
					}

					$result['region'] = get_post_meta( $attribute_page->ID, 'region', true );
					$result['smak']   = get_post_meta( $attribute_page->ID, 'smak', true );
				}
			}
		}

		return $result;
	}

	/**
	 * Build a link HTML for a term.
	 *
	 * @since 1.3.0
	 *
	 * @param WP_Term $term The term object.
	 *
	 * @return string Link HTML.
	 */
	private function build_term_link( $term ) {
		$page_id    = get_term_meta( $term->term_id, 'linked_page_id', true );
		$custom_url = get_term_meta( $term->term_id, 'custom_page_url', true );

		if ( ! empty( $page_id ) || ! empty( $custom_url ) ) {
			$link_url  = ! empty( $page_id ) ? get_permalink( $page_id ) : $custom_url;
			$link_text = ! empty( $page_id ) ? get_the_title( $page_id ) : __( 'Learn more', 'wc-rich-attribute-suite' );

			return '<a href="' . esc_url( $link_url ) . '" class="term-page-link">' . esc_html( $link_text ) . '</a>';
		}

		return '<a href="' . esc_url( get_term_link( $term ) ) . '" class="term-page-link">' .
			   esc_html__( 'Learn more', 'wc-rich-attribute-suite' ) . '</a>';
	}

	/**
	 * Format term descriptions and links into HTML.
	 *
	 * @since 1.3.0
	 *
	 * @param array $descriptions Array of term descriptions.
	 * @param array $links        Array of term page link HTML.
	 *
	 * @return string Formatted HTML description.
	 */
	private function format_description_html( $descriptions, $links ) {
		if ( empty( $descriptions ) ) {
			return '';
		}

		$show_links = apply_filters( 'wc_ras_show_variation_description_links', true );
		$combine    = apply_filters( 'wc_ras_combine_all_term_descriptions', false );

		if ( $combine && count( $descriptions ) > 1 ) {
			$html = '<p>' . implode( '</p><p>', $descriptions ) . '</p>';

			if ( $show_links && ! empty( $links ) ) {
				$html .= '<p class="term-page-link-wrapper">' . implode( ' | ', $links ) . '</p>';
			}

			return $html;
		}

		$html = '<p>' . $descriptions[0] . '</p>';

		if ( $show_links && ! empty( $links[0] ) ) {
			$html .= '<p class="term-page-link-wrapper">' . $links[0] . '</p>';
		}

		return $html;
	}
}

// Initialize the class.
new WC_RAS_Variation_Improvements();
