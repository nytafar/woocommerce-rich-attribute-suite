<?php
/**
 * Custom Post Type: Attribute Page
 *
 * Registers the attribute_page CPT and related meta fields.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the attribute_page custom post type registration and management.
 *
 * @since 1.0.0
 */
class WC_RAS_Attribute_Page_CPT {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.3.0
	 */
	public function init() {
		add_action( 'init', array( $this, 'handle_register_cpt' ) );
		add_action( 'init', array( $this, 'handle_register_meta' ) );
		add_action( 'init', array( $this, 'handle_register_term_hooks' ), 20 );
		add_action( 'admin_menu', array( $this, 'handle_customize_admin_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'handle_prefill_from_url' ) );
		add_action( 'save_post_attribute_page', array( $this, 'handle_save_term_link' ), 5 );
	}

	/**
	 * Register the attribute_page custom post type.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_register_cpt() {
		$labels = array(
			'name'                  => _x( 'Attribute Pages', 'Post type general name', 'wc-rich-attribute-suite' ),
			'singular_name'         => _x( 'Attribute Page', 'Post type singular name', 'wc-rich-attribute-suite' ),
			'menu_name'             => _x( 'Attribute Suite', 'Admin Menu text', 'wc-rich-attribute-suite' ),
			'name_admin_bar'        => _x( 'Attribute Page', 'Add New on Toolbar', 'wc-rich-attribute-suite' ),
			'add_new'               => __( 'Add New', 'wc-rich-attribute-suite' ),
			'add_new_item'          => __( 'Add New Attribute Page', 'wc-rich-attribute-suite' ),
			'new_item'              => __( 'New Attribute Page', 'wc-rich-attribute-suite' ),
			'edit_item'             => __( 'Edit Attribute Page', 'wc-rich-attribute-suite' ),
			'view_item'             => __( 'View Attribute Page', 'wc-rich-attribute-suite' ),
			'all_items'             => __( 'All Pages', 'wc-rich-attribute-suite' ),
			'search_items'          => __( 'Search Attribute Pages', 'wc-rich-attribute-suite' ),
			'parent_item_colon'     => __( 'Parent Attribute Pages:', 'wc-rich-attribute-suite' ),
			'not_found'             => __( 'No attribute pages found.', 'wc-rich-attribute-suite' ),
			'not_found_in_trash'    => __( 'No attribute pages found in Trash.', 'wc-rich-attribute-suite' ),
			'featured_image'        => _x( 'Attribute Image', 'Overrides the "Featured Image" phrase', 'wc-rich-attribute-suite' ),
			'set_featured_image'    => _x( 'Set attribute image', 'Overrides the "Set featured image" phrase', 'wc-rich-attribute-suite' ),
			'remove_featured_image' => _x( 'Remove attribute image', 'Overrides the "Remove featured image" phrase', 'wc-rich-attribute-suite' ),
			'use_featured_image'    => _x( 'Use as attribute image', 'Overrides the "Use as featured image" phrase', 'wc-rich-attribute-suite' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-tag',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'revisions' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'attribute_page', $args );
	}

	/**
	 * Add custom admin menu structure for Attribute Suite.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_customize_admin_menu() {
		global $submenu;

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) || ! isset( $submenu['edit.php?post_type=attribute_page'] ) ) {
			return;
		}

		// Remove the "Add New" submenu item.
		foreach ( $submenu['edit.php?post_type=attribute_page'] as $key => $item ) {
			if ( 'post-new.php?post_type=attribute_page' === $item[2] ) {
				unset( $submenu['edit.php?post_type=attribute_page'][ $key ] );
				break;
			}
		}

		// Insert attribute term configuration links before "All Pages".
		$new_submenu = array();
		$inserted    = false;

		foreach ( $submenu['edit.php?post_type=attribute_page'] as $key => $item ) {
			if ( ! $inserted && 'edit.php?post_type=attribute_page' === $item[2] ) {
				foreach ( $attribute_taxonomies as $tax ) {
					$taxonomy_name   = wc_attribute_taxonomy_name( $tax->attribute_name );
					$attribute_label = $tax->attribute_label ? $tax->attribute_label : $tax->attribute_name;

					$new_submenu[] = array(
						$attribute_label,
						'manage_product_terms',
						'edit-tags.php?taxonomy=' . $taxonomy_name . '&post_type=product',
					);
				}
				$inserted = true;
			}

			$new_submenu[] = $item;
		}

		$submenu['edit.php?post_type=attribute_page'] = $new_submenu;
	}

	/**
	 * Register meta fields for attribute_page CPT.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_register_meta() {
		register_post_meta(
			'attribute_page',
			'region',
			array(
				'type'              => 'string',
				'description'       => __( 'Region information for the attribute', 'wc-rich-attribute-suite' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			'attribute_page',
			'smak',
			array(
				'type'              => 'string',
				'description'       => __( 'Taste profile for the attribute', 'wc-rich-attribute-suite' ),
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		/**
		 * Hook for registering additional meta fields.
		 *
		 * @since 1.0.0
		 *
		 * @param string $post_type The post type (attribute_page).
		 */
		do_action( 'wc_ras_register_attribute_page_meta_fields', 'attribute_page' );
	}

	/**
	 * Auto-create attribute_page CPT for each relevant attribute term.
	 *
	 * @internal
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 *
	 * @since 1.0.0
	 */
	public function handle_sync_on_term_create( $term_id, $taxonomy ) {
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$existing = get_page_by_path( $term->slug, OBJECT, 'attribute_page' );
		if ( $existing ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $term->name,
				'post_name'    => $term->slug,
				'post_type'    => 'attribute_page',
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_attribute_taxonomy', $taxonomy );
			update_post_meta( $post_id, '_attribute_term_id', $term_id );
		}
	}

	/**
	 * Register hooks for attribute term creation.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_register_term_hooks() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $taxonomy ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $taxonomy->attribute_name );
			add_action( "created_{$taxonomy_name}", array( $this, 'handle_sync_on_term_create' ), 10, 2 );
		}
	}

	/**
	 * Pre-fill attribute page data when creating from term edit screen.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_prefill_from_url() {
		global $pagenow;

		if ( 'post-new.php' !== $pagenow || ! isset( $_GET['post_type'] ) || 'attribute_page' !== $_GET['post_type'] ) {
			return;
		}

		if ( ! isset( $_GET['attribute_term'] ) || ! isset( $_GET['attribute_taxonomy'] ) ) {
			return;
		}

		$term_slug = sanitize_text_field( wp_unslash( $_GET['attribute_term'] ) );
		$taxonomy  = sanitize_text_field( wp_unslash( $_GET['attribute_taxonomy'] ) );

		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		set_transient(
			'wc_ras_pending_attribute_page_' . get_current_user_id(),
			array(
				'term_id'   => $term->term_id,
				'term_slug' => $term_slug,
				'term_name' => $term->name,
				'taxonomy'  => $taxonomy,
			),
			HOUR_IN_SECONDS
		);

		add_action(
			'admin_footer',
			function () use ( $term ) {
				?>
				<script>
				jQuery(document).ready(function($) {
					if ($('#title').length && !$('#title').val()) {
						$('#title').val(<?php echo wp_json_encode( $term->name ); ?>);
					}
					if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
						var currentTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
						if (!currentTitle) {
							wp.data.dispatch('core/editor').editPost({ title: <?php echo wp_json_encode( $term->name ); ?> });
						}
					}
				});
				</script>
				<?php
			}
		);
	}

	/**
	 * Save attribute term meta when attribute page is created manually.
	 *
	 * @internal
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_term_link( $post_id ) {
		if ( 'attribute_page' !== get_post_type( $post_id ) ) {
			return;
		}

		$pending_data = get_transient( 'wc_ras_pending_attribute_page_' . get_current_user_id() );
		if ( ! $pending_data ) {
			return;
		}

		if ( ! get_post_meta( $post_id, '_attribute_term_id', true ) ) {
			update_post_meta( $post_id, '_attribute_term_id', $pending_data['term_id'] );
			update_post_meta( $post_id, '_attribute_taxonomy', $pending_data['taxonomy'] );

			$post = get_post( $post_id );
			if ( $post && empty( $post->post_name ) ) {
				wp_update_post(
					array(
						'ID'        => $post_id,
						'post_name' => $pending_data['term_slug'],
					)
				);
			}
		}

		delete_transient( 'wc_ras_pending_attribute_page_' . get_current_user_id() );
	}

	/**
	 * Get an attribute page by term slug.
	 *
	 * @param string $term_slug The term slug.
	 *
	 * @return WP_Post|null The attribute page post or null if not found.
	 *
	 * @since 1.0.0
	 */
	public static function get_attribute_page( $term_slug ) {
		$cache_key = 'attribute_page_' . md5( $term_slug );
		$page_id   = wp_cache_get( $cache_key, 'wc_ras_attribute_pages' );

		if ( false === $page_id ) {
			$page    = get_page_by_path( $term_slug, OBJECT, 'attribute_page' );
			$page_id = $page ? $page->ID : 0;
			wp_cache_set( $cache_key, $page_id, 'wc_ras_attribute_pages' );
		}

		return $page_id ? get_post( $page_id ) : null;
	}
}
