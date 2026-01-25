<?php
/**
 * Admin Hooks
 *
 * Handles admin UI integration for attribute pages.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all admin functionality: term edit links, CPT columns,
 * meta boxes, term columns, and quick edit support.
 *
 * @since 1.0.0
 */
class WC_RAS_Admin {

	/**
	 * Initialize hooks.
	 *
	 * @since 1.3.0
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'handle_register_term_edit_hooks' ) );
		add_action( 'admin_init', array( $this, 'handle_register_term_column_hooks' ) );
		add_filter( 'manage_attribute_page_posts_columns', array( $this, 'handle_add_columns' ) );
		add_action( 'manage_attribute_page_posts_custom_column', array( $this, 'handle_populate_columns' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'handle_add_meta_box' ) );
		add_action( 'save_post_attribute_page', array( $this, 'handle_save_meta' ) );
		add_action( 'admin_footer', array( $this, 'handle_add_quick_edit_js' ) );
		add_action( 'admin_head', array( $this, 'handle_add_description_save_nonce' ) );
		add_action( 'wp_ajax_wc_ras_save_term_description', array( $this, 'handle_ajax_save_term_description' ) );
	}

	/**
	 * Add a link from attribute term edit screen to its content page.
	 *
	 * @internal
	 *
	 * @param WP_Term $term The term being edited.
	 *
	 * @since 1.0.0
	 */
	public function handle_add_term_edit_link( $term ) {
		if ( 0 !== strpos( $term->taxonomy, 'pa_' ) ) {
			return;
		}

		$linked_page = get_page_by_path( $term->slug, OBJECT, 'attribute_page' );

		if ( $linked_page ) {
			$edit_url = get_edit_post_link( $linked_page->ID );
			?>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label><?php esc_html_e( 'Rich Content', 'wc-rich-attribute-suite' ); ?></label>
				</th>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button">
						<?php esc_html_e( 'Edit Rich Content', 'wc-rich-attribute-suite' ); ?>
					</a>
					<p class="description">
						<?php esc_html_e( 'Edit the rich content page for this attribute term using the block editor.', 'wc-rich-attribute-suite' ); ?>
					</p>
				</td>
			</tr>
			<?php
		} else {
			$create_url = admin_url( 'post-new.php?post_type=attribute_page&attribute_term=' . $term->slug . '&attribute_taxonomy=' . $term->taxonomy );
			?>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label><?php esc_html_e( 'Rich Content', 'wc-rich-attribute-suite' ); ?></label>
				</th>
				<td>
					<a href="<?php echo esc_url( $create_url ); ?>" class="button">
						<?php esc_html_e( 'Create Rich Content Page', 'wc-rich-attribute-suite' ); ?>
					</a>
					<p class="description">
						<?php esc_html_e( 'No rich content page exists for this attribute term. Click to create one.', 'wc-rich-attribute-suite' ); ?>
					</p>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Register term edit hooks for all product attribute taxonomies.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_register_term_edit_hooks() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $taxonomy ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $taxonomy->attribute_name );
			add_action( "{$taxonomy_name}_edit_form_fields", array( $this, 'handle_add_term_edit_link' ), 10, 1 );
		}
	}

	/**
	 * Add custom columns to the attribute_page post type admin list.
	 *
	 * @internal
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 *
	 * @since 1.0.0
	 */
	public function handle_add_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['attribute_taxonomy'] = __( 'Attribute Type', 'wc-rich-attribute-suite' );
				$new_columns['attribute_term']     = __( 'Attribute Term', 'wc-rich-attribute-suite' );
				$new_columns['region']             = __( 'Region', 'wc-rich-attribute-suite' );
				$new_columns['smak']               = __( 'Smak', 'wc-rich-attribute-suite' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate custom columns in the attribute_page post type admin list.
	 *
	 * @internal
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 *
	 * @since 1.0.0
	 */
	public function handle_populate_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'attribute_taxonomy':
				$taxonomy = get_post_meta( $post_id, '_attribute_taxonomy', true );
				if ( $taxonomy ) {
					$taxonomy_label = str_replace( 'pa_', '', $taxonomy );
					echo esc_html( ucfirst( $taxonomy_label ) );
				} else {
					echo '—';
				}
				break;

			case 'attribute_term':
				$term_id  = get_post_meta( $post_id, '_attribute_term_id', true );
				$taxonomy = get_post_meta( $post_id, '_attribute_taxonomy', true );

				if ( $term_id && $taxonomy ) {
					$term = get_term( $term_id, $taxonomy );
					if ( ! is_wp_error( $term ) && $term ) {
						$term_url = get_edit_term_link( $term_id, $taxonomy );
						echo '<a href="' . esc_url( $term_url ) . '">' . esc_html( $term->name ) . '</a>';
					} else {
						echo esc_html( get_the_title( $post_id ) );
					}
				} else {
					$post = get_post( $post_id );
					echo esc_html( $post->post_name );
				}
				break;

			case 'region':
			case 'smak':
				$value = get_post_meta( $post_id, $column, true );
				echo esc_html( $value ? $value : '—' );
				break;
		}
	}

	/**
	 * Add meta box for attribute page fields.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_add_meta_box() {
		add_meta_box(
			'wc_ras_attribute_meta',
			__( 'Attribute Properties', 'wc-rich-attribute-suite' ),
			array( $this, 'handle_render_meta_box' ),
			'attribute_page',
			'normal',
			'default'
		);
	}

	/**
	 * Render meta box for attribute page fields.
	 *
	 * @internal
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @since 1.0.0
	 */
	public function handle_render_meta_box( $post ) {
		wp_nonce_field( 'wc_ras_save_attribute_meta', 'wc_ras_attribute_meta_nonce' );

		$region = get_post_meta( $post->ID, 'region', true );
		$smak   = get_post_meta( $post->ID, 'smak', true );

		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="wc_ras_region"><?php esc_html_e( 'Region', 'wc-rich-attribute-suite' ); ?></label>
				</th>
				<td>
					<input type="text" id="wc_ras_region" name="wc_ras_region" value="<?php echo esc_attr( $region ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The region for this attribute (e.g., geographical location)', 'wc-rich-attribute-suite' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="wc_ras_smak"><?php esc_html_e( 'Smak', 'wc-rich-attribute-suite' ); ?></label>
				</th>
				<td>
					<input type="text" id="wc_ras_smak" name="wc_ras_smak" value="<?php echo esc_attr( $smak ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The taste profile for this attribute', 'wc-rich-attribute-suite' ); ?></p>
				</td>
			</tr>
		</table>
		<?php

		/**
		 * Hook for adding additional fields to the attribute page meta box.
		 *
		 * @since 1.0.0
		 *
		 * @param WP_Post $post Current post object.
		 */
		do_action( 'wc_ras_attribute_page_meta_box_fields', $post );
	}

	/**
	 * Save meta box data.
	 *
	 * @internal
	 *
	 * @param int $post_id Post ID.
	 *
	 * @since 1.0.0
	 */
	public function handle_save_meta( $post_id ) {
		// Check for autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if our nonce is set and verify it.
		if ( ! isset( $_POST['wc_ras_attribute_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_ras_attribute_meta_nonce'] ) ), 'wc_ras_save_attribute_meta' ) ) {
			return;
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save region.
		if ( isset( $_POST['wc_ras_region'] ) ) {
			update_post_meta( $post_id, 'region', sanitize_text_field( wp_unslash( $_POST['wc_ras_region'] ) ) );
		}

		// Save smak.
		if ( isset( $_POST['wc_ras_smak'] ) ) {
			update_post_meta( $post_id, 'smak', sanitize_text_field( wp_unslash( $_POST['wc_ras_smak'] ) ) );
		}

		/**
		 * Hook for saving additional fields from the attribute page meta box.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id Post ID.
		 */
		do_action( 'wc_ras_save_attribute_page_meta', $post_id );
	}

	/**
	 * Add custom columns to product attribute term list tables.
	 *
	 * @internal
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 *
	 * @since 1.0.0
	 */
	public function handle_add_term_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'description' === $key ) {
				$new_columns['rich_content'] = __( 'Rich Content', 'wc-rich-attribute-suite' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate custom columns in product attribute term list tables.
	 *
	 * @internal
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 *
	 * @return string Modified column content.
	 *
	 * @since 1.0.0
	 */
	public function handle_populate_term_columns( $content, $column_name, $term_id ) {
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			return '—';
		}

		if ( 'rich_content' === $column_name ) {
			$linked_page = get_page_by_path( $term->slug, OBJECT, 'attribute_page' );

			if ( $linked_page ) {
				$edit_url = get_edit_post_link( $linked_page->ID );
				return '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' .
					   esc_html__( 'Edit', 'wc-rich-attribute-suite' ) . '</a>';
			} else {
				$create_url = admin_url( 'post-new.php?post_type=attribute_page&attribute_term=' . $term->slug . '&attribute_taxonomy=' . $term->taxonomy );
				return '<a href="' . esc_url( $create_url ) . '" class="button button-small button-secondary" title="' .
					   esc_attr__( 'No rich content page exists. Click to create one.', 'wc-rich-attribute-suite' ) . '">' .
					   esc_html__( 'Create', 'wc-rich-attribute-suite' ) . '</a>';
			}
		}

		return $content;
	}

	/**
	 * Register term column hooks for all product attribute taxonomies.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_register_term_column_hooks() {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if ( empty( $attribute_taxonomies ) ) {
			return;
		}

		foreach ( $attribute_taxonomies as $taxonomy ) {
			$taxonomy_name = wc_attribute_taxonomy_name( $taxonomy->attribute_name );
			add_filter( "manage_edit-{$taxonomy_name}_columns", array( $this, 'handle_add_term_columns' ) );
			add_filter( "manage_{$taxonomy_name}_custom_column", array( $this, 'handle_populate_term_columns' ), 10, 3 );
		}
	}

	/**
	 * Add description textarea to quick edit form via JavaScript.
	 *
	 * Queries all terms for the current taxonomy and outputs a JavaScript object
	 * mapping term IDs to their full descriptions. This allows the quick edit
	 * form to populate the description field with the complete text rather than
	 * relying on potentially truncated DOM content.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_add_quick_edit_js() {
		global $pagenow;

		if ( 'edit-tags.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading taxonomy from URL for display purposes only.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return;
		}

		// Get all terms with their full descriptions.
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		$term_descriptions = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_descriptions[ $term->term_id ] = $term->description;
			}
		}

		?>
		<script type="text/javascript">
		jQuery( document ).ready( function( $ ) {
			var taxonomy = <?php echo wp_json_encode( $taxonomy ); ?>;
			var termDescriptions = <?php echo wp_json_encode( $term_descriptions ); ?>;

			if ( typeof inlineEditTax !== 'undefined' ) {
				var originalEdit = inlineEditTax.edit;
				var originalSave = inlineEditTax.save;

				inlineEditTax.edit = function( id ) {
					originalEdit.apply( this, arguments );

					if ( typeof id === 'object' ) {
						id = this.getId( id );
					}

					var $editRow = $( '#edit-' + id );

					// Add description textarea if it doesn't exist.
					if ( $editRow.find( 'textarea[name="wc_ras_description"]' ).length === 0 ) {
						var descriptionHtml = '<fieldset class="wc-ras-description-field">' +
							'<div class="inline-edit-col">' +
							'<label>' +
							'<span class="title"><?php echo esc_js( __( 'Description', 'wc-rich-attribute-suite' ) ); ?></span>' +
							'<span class="input-text-wrap">' +
							'<textarea name="wc_ras_description" rows="3" class="ptitle" style="width:100%;"></textarea>' +
							'</span>' +
							'</label>' +
							'</div>' +
							'</fieldset>';

						var $slugField = $editRow.find( 'input[name="slug"]' ).closest( 'label' );
						if ( $slugField.length ) {
							$slugField.closest( 'fieldset' ).after( descriptionHtml );
						}
					}

					// Get the full description from our pre-loaded data.
					var description = termDescriptions[ id ] || '';
					$editRow.find( 'textarea[name="wc_ras_description"]' ).val( description );
					$editRow.data( 'wc-ras-term-id', id );
				};

				inlineEditTax.save = function( id ) {
					if ( typeof id === 'object' ) {
						id = this.getId( id );
					}

					var $editRow = $( '#edit-' + id );
					var description = $editRow.find( 'textarea[name="wc_ras_description"]' ).val();
					var termId = $editRow.data( 'wc-ras-term-id' ) || id;

					if ( typeof wcRasDescNonce !== 'undefined' ) {
						$.post( ajaxurl, {
							action: 'wc_ras_save_term_description',
							nonce: wcRasDescNonce,
							term_id: termId,
							taxonomy: taxonomy,
							description: description
						} );
					}

					return originalSave.apply( this, arguments );
				};
			}
		} );
		</script>
		<?php
	}

	/**
	 * AJAX handler to save term description.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_ajax_save_term_description() {
		check_ajax_referer( 'wc_ras_save_description', 'nonce' );

		$term_id     = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
		$taxonomy    = isset( $_POST['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_POST['taxonomy'] ) ) : '';
		$description = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

		if ( ! $term_id || ! $taxonomy ) {
			wp_send_json_error( 'Missing required parameters' );
		}

		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			wp_send_json_error( 'Invalid taxonomy' );
		}

		if ( ! current_user_can( 'manage_product_terms' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$result = wp_update_term(
			$term_id,
			$taxonomy,
			array(
				'description' => $description,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'description' => $description,
			)
		);
	}

	/**
	 * Add nonce for description save AJAX.
	 *
	 * Outputs the nonce as a JavaScript variable in the admin head so it's
	 * available when the quick edit save function runs.
	 *
	 * @internal
	 *
	 * @since 1.0.0
	 */
	public function handle_add_description_save_nonce() {
		global $pagenow;

		if ( 'edit-tags.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading taxonomy from URL for display purposes only.
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
		if ( 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return;
		}

		?>
		<script type="text/javascript">
			var wcRasDescNonce = <?php echo wp_json_encode( wp_create_nonce( 'wc_ras_save_description' ) ); ?>;
		</script>
		<?php
	}
}
