<?php
/**
 * Admin screens: settings, the case field editor, list columns and admin assets.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything that only runs in wp-admin.
 */
class CaseLens_Admin {

	const SETTINGS_GROUP = 'caselens_settings_group';
	const FIELDS_ACTION  = 'caselens_save_fields';
	const FIELDS_NONCE   = 'caselens_fields_nonce';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_' . self::FIELDS_ACTION, array( $this, 'save_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		add_filter( 'manage_' . CASELENS_CPT . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . CASELENS_CPT . '_posts_custom_column', array( $this, 'column' ), 10, 2 );

		add_action( 'save_post_' . CASELENS_CPT, array( 'CaseLens_Query', 'flush_counts' ) );
		add_action( 'deleted_post', array( 'CaseLens_Query', 'flush_counts' ) );
		add_action( 'set_object_terms', array( 'CaseLens_Query', 'flush_counts' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( CASELENS_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Add the two sub-menus.
	 *
	 * @return void
	 */
	public function menu() {
		$parent = 'edit.php?post_type=' . CASELENS_CPT;

		add_submenu_page(
			$parent,
			__( 'Case Fields', 'caselens-before-and-after-gallery' ),
			__( 'Case Fields', 'caselens-before-and-after-gallery' ),
			'manage_options',
			'caselens-fields',
			array( $this, 'fields_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Gallery Settings', 'caselens-before-and-after-gallery' ),
			__( 'Settings', 'caselens-before-and-after-gallery' ),
			'manage_options',
			'caselens-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * A Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'edit.php?post_type=' . CASELENS_CPT . '&page=caselens-settings' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'caselens-before-and-after-gallery' ) . '</a>' );

		return $links;
	}

	/**
	 * Register the settings option with its sanitiser.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			CaseLens_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CaseLens_Settings', 'sanitize' ),
				'default'           => CaseLens_Settings::defaults(),
			)
		);
	}

	/**
	 * Admin styles and scripts, only on our own screens.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_case_editor = $screen && CASELENS_CPT === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		$is_our_page    = isset( $_GET['page'] ) && in_array( sanitize_key( wp_unslash( $_GET['page'] ) ), array( 'caselens-fields', 'caselens-settings' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $is_case_editor && ! $is_our_page ) {
			return;
		}

		wp_enqueue_style( 'caselens-admin', CASELENS_URL . 'assets/css/admin.css', array(), CASELENS_VERSION );

		if ( $is_case_editor ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'caselens-admin',
				CASELENS_URL . 'assets/js/admin.js',
				array( 'jquery', 'jquery-ui-sortable' ),
				CASELENS_VERSION,
				true
			);
			wp_localize_script(
				'caselens-admin',
				'caseLensAdmin',
				array(
					'chooseImage' => __( 'Choose image', 'caselens-before-and-after-gallery' ),
					'useImage'    => __( 'Use this image', 'caselens-before-and-after-gallery' ),
					'frameTitle'  => __( 'Select a photo', 'caselens-before-and-after-gallery' ),
				)
			);
		}

		if ( $is_our_page ) {
			wp_enqueue_script(
				'caselens-fields',
				CASELENS_URL . 'assets/js/fields.js',
				array(),
				CASELENS_VERSION,
				true
			);
		}
	}

	/**
	 * Extra columns on the case list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['caselens_photo'] = __( 'Photos', 'caselens-before-and-after-gallery' );
			}
			$out[ $key ] = $label;
		}

		$out['caselens_number'] = __( 'Number', 'caselens-before-and-after-gallery' );

		return $out;
	}

	/**
	 * Render one custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Case ID.
	 * @return void
	 */
	public function column( $column, $post_id ) {
		if ( 'caselens_number' === $column ) {
			$number = CaseLens_Case::number( $post_id );
			echo '' !== $number ? esc_html( $number ) : '<span aria-hidden="true">—</span>';
			return;
		}

		if ( 'caselens_photo' !== $column ) {
			return;
		}

		$images = CaseLens_Case::card_images( $post_id, 'thumbnail' );
		if ( empty( $images ) ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		echo '<span class="caselens-col-photos">';
		foreach ( $images as $image ) {
			printf(
				'<img src="%1$s" alt="%2$s" width="38" height="38" loading="lazy" />',
				esc_url( $image['url'] ),
				esc_attr( $image['alt'] )
			);
		}
		echo '</span>';
	}

	/**
	 * The settings screen.
	 *
	 * @return void
	 */
	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'caselens-before-and-after-gallery' ) );
		}

		$settings = CaseLens_Settings::all();
		$option   = CaseLens_Settings::OPTION;
		?>
		<div class="wrap caselens-wrap">
			<h1><?php esc_html_e( 'Gallery Settings', 'caselens-before-and-after-gallery' ); ?></h1>

			<?php
			// This screen lives under Gallery Cases, so the "Settings saved" notice is not printed for us.
			if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				add_settings_error( 'caselens_messages', 'caselens_saved', __( 'Settings saved.', 'caselens-before-and-after-gallery' ), 'updated' );
			}
			settings_errors( 'caselens_messages' );
			?>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Where the gallery lives', 'caselens-before-and-after-gallery' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="caselens-page"><?php esc_html_e( 'Gallery page', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $option . '[page_id]' ),
									'id'                => 'caselens-page',
									'selected'          => (int) $settings['page_id'],
									'show_option_none'  => esc_html__( '— Select —', 'caselens-before-and-after-gallery' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description">
								<?php esc_html_e( 'The page that holds the [caselens_gallery] shortcode. Procedure links point here.', 'caselens-before-and-after-gallery' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-base"><?php esc_html_e( 'URL base', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
							<input type="text" id="caselens-base" name="<?php echo esc_attr( $option ); ?>[base]" value="<?php echo esc_attr( $settings['base'] ); ?>" class="regular-text" />
							<code>/procedure/case/</code>
							<p class="description">
								<?php esc_html_e( 'Usually the slug of the gallery page. Cases live under this base.', 'caselens-before-and-after-gallery' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Gallery grid', 'caselens-before-and-after-gallery' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="caselens-heading"><?php esc_html_e( 'Heading', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="text" id="caselens-heading" name="<?php echo esc_attr( $option ); ?>[heading]" value="<?php echo esc_attr( $settings['heading'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-disclaimer"><?php esc_html_e( 'Disclaimer', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<input type="text" id="caselens-disclaimer" name="<?php echo esc_attr( $option ); ?>[disclaimer]" value="<?php echo esc_attr( $settings['disclaimer'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Shown above the grid and on each case. Leave empty to hide it.', 'caselens-before-and-after-gallery' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-per-page"><?php esc_html_e( 'Cases per page', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="number" min="3" max="60" id="caselens-per-page" name="<?php echo esc_attr( $option ); ?>[per_page]" value="<?php echo esc_attr( $settings['per_page'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-accent"><?php esc_html_e( 'Accent colour', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="color" id="caselens-accent" name="<?php echo esc_attr( $option ); ?>[accent]" value="<?php echo esc_attr( $settings['accent'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'caselens-before-and-after-gallery' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Gallery options', 'caselens-before-and-after-gallery' ); ?></legend>
								<?php
								$toggles = array(
									'enable_filters' => __( 'Show the filter button', 'caselens-before-and-after-gallery' ),
									'enable_number'  => __( 'Show the case number search', 'caselens-before-and-after-gallery' ),
									'hide_empty'     => __( 'Hide procedures that have no cases', 'caselens-before-and-after-gallery' ),
									'empty_is_404'   => __( 'Return 404 for a procedure URL with no cases', 'caselens-before-and-after-gallery' ),
									'show_prev_next' => __( 'Show previous and next links on a case', 'caselens-before-and-after-gallery' ),
									'lightbox'       => __( 'Open case photos full size when clicked', 'caselens-before-and-after-gallery' ),
								);
								foreach ( $toggles as $key => $label ) :
									?>
									<label class="caselens-check">
										<input type="checkbox" name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $settings[ $key ] ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-order"><?php esc_html_e( 'Procedure order', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<textarea id="caselens-order" name="<?php echo esc_attr( $option ); ?>[procedure_order]" rows="5" class="large-text code"><?php echo esc_textarea( $settings['procedure_order'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One procedure slug per line. Listed procedures come first, in this order; the rest follow A to Z.', 'caselens-before-and-after-gallery' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Wording and links', 'caselens-before-and-after-gallery' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="caselens-noun"><?php esc_html_e( 'Word for one case', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<input type="text" id="caselens-noun" name="<?php echo esc_attr( $option ); ?>[case_noun]" value="<?php echo esc_attr( $settings['case_noun'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Used for headings such as "Tummy Tuck Patient #904" when a case has no title.', 'caselens-before-and-after-gallery' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-number-label"><?php esc_html_e( 'Search box label', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="text" id="caselens-number-label" name="<?php echo esc_attr( $option ); ?>[number_label]" value="<?php echo esc_attr( $settings['number_label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-back"><?php esc_html_e( 'Back link text', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="text" id="caselens-back" name="<?php echo esc_attr( $option ); ?>[back_label]" value="<?php echo esc_attr( $settings['back_label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-cta-label"><?php esc_html_e( 'Button text', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td><input type="text" id="caselens-cta-label" name="<?php echo esc_attr( $option ); ?>[cta_label]" value="<?php echo esc_attr( $settings['cta_label'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="caselens-cta-url"><?php esc_html_e( 'Button link', 'caselens-before-and-after-gallery' ); ?></label></th>
						<td>
							<input type="url" id="caselens-cta-url" name="<?php echo esc_attr( $option ); ?>[cta_url]" value="<?php echo esc_attr( $settings['cta_url'] ); ?>" class="regular-text" placeholder="https://" />
							<p class="description"><?php esc_html_e( 'Shown under the details on a case. Leave empty to hide the button.', 'caselens-before-and-after-gallery' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Uninstalling', 'caselens-before-and-after-gallery' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'On delete', 'caselens-before-and-after-gallery' ); ?></th>
						<td>
							<label class="caselens-check">
								<input type="checkbox" name="<?php echo esc_attr( $option ); ?>[delete_data]" value="1" <?php checked( ! empty( $settings['delete_data'] ) ); ?> />
								<?php esc_html_e( 'Also delete every case, procedure and setting when this plugin is deleted', 'caselens-before-and-after-gallery' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Off by default, so deleting the plugin leaves your cases in place.', 'caselens-before-and-after-gallery' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcode', 'caselens-before-and-after-gallery' ); ?></h2>
			<p><?php esc_html_e( 'Place this on any page to show the gallery:', 'caselens-before-and-after-gallery' ); ?> <code>[caselens_gallery]</code></p>
			<p class="description">
				<?php esc_html_e( 'Attributes: procedure, per_page, columns, sidebar, filters, search. For example:', 'caselens-before-and-after-gallery' ); ?>
				<code>[caselens_gallery procedure="breast" columns="2" sidebar="no"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * The case field editor.
	 *
	 * @return void
	 */
	public function fields_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these fields.', 'caselens-before-and-after-gallery' ) );
		}

		$fields = CaseLens_Fields::all();
		$types  = CaseLens_Fields::types();
		?>
		<div class="wrap caselens-wrap">
			<h1><?php esc_html_e( 'Case Fields', 'caselens-before-and-after-gallery' ); ?></h1>
			<p>
				<?php esc_html_e( 'The details each case can hold. They appear on the case editor, in the details table beside the photos, and as front-end filters when you tick Filter.', 'caselens-before-and-after-gallery' ); ?>
			</p>

			<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Fields saved.', 'caselens-before-and-after-gallery' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::FIELDS_ACTION ); ?>" />
				<?php wp_nonce_field( self::FIELDS_ACTION, self::FIELDS_NONCE ); ?>

				<div id="caselens-field-rows">
					<?php foreach ( $fields as $index => $field ) : ?>
						<?php $this->render_field_row( $index, $field, $types ); ?>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" id="caselens-add-field"><?php esc_html_e( 'Add field', 'caselens-before-and-after-gallery' ); ?></button>
				</p>

				<script type="text/html" id="tmpl-caselens-field">
					<?php
					$this->render_field_row(
						'__index__',
						array(
							'key'     => '',
							'label'   => '',
							'group'   => '',
							'type'    => 'text',
							'choices' => array(),
							'suffix'  => '',
							'filter'  => 1,
							'show'    => 1,
						),
						$types
					);
					?>
				</script>

				<?php submit_button( __( 'Save fields', 'caselens-before-and-after-gallery' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * One row of the field editor.
	 *
	 * @param int|string $index Row index, or the template placeholder.
	 * @param array      $field Field definition.
	 * @param array      $types Available field types.
	 * @return void
	 */
	private function render_field_row( $index, $field, $types ) {
		$name = 'caselens_field[' . $index . ']';
		?>
		<div class="caselens-field-row">
			<div class="caselens-field-grid">
				<p class="caselens-field-cell">
					<label><?php esc_html_e( 'Label', 'caselens-before-and-after-gallery' ); ?><br />
						<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( $field['label'] ); ?>" />
					</label>
				</p>
				<p class="caselens-field-cell">
					<label><?php esc_html_e( 'Key', 'caselens-before-and-after-gallery' ); ?><br />
						<input type="text" class="code" name="<?php echo esc_attr( $name ); ?>[key]" value="<?php echo esc_attr( $field['key'] ); ?>" placeholder="<?php esc_attr_e( 'auto', 'caselens-before-and-after-gallery' ); ?>" />
					</label>
				</p>
				<p class="caselens-field-cell">
					<label><?php esc_html_e( 'Group', 'caselens-before-and-after-gallery' ); ?><br />
						<input type="text" name="<?php echo esc_attr( $name ); ?>[group]" value="<?php echo esc_attr( $field['group'] ); ?>" />
					</label>
				</p>
				<p class="caselens-field-cell">
					<label><?php esc_html_e( 'Type', 'caselens-before-and-after-gallery' ); ?><br />
						<select name="<?php echo esc_attr( $name ); ?>[type]" class="caselens-field-type">
							<?php foreach ( $types as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p class="caselens-field-cell">
					<label><?php esc_html_e( 'Suffix', 'caselens-before-and-after-gallery' ); ?><br />
						<input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[suffix]" value="<?php echo esc_attr( $field['suffix'] ); ?>" />
					</label>
				</p>
				<p class="caselens-field-cell caselens-field-toggles">
					<label class="caselens-check">
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show]" value="1" <?php checked( ! empty( $field['show'] ) ); ?> />
						<?php esc_html_e( 'Show on case', 'caselens-before-and-after-gallery' ); ?>
					</label>
					<label class="caselens-check">
						<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[filter]" value="1" <?php checked( ! empty( $field['filter'] ) ); ?> />
						<?php esc_html_e( 'Filter', 'caselens-before-and-after-gallery' ); ?>
					</label>
					<button type="button" class="button-link delete caselens-remove-field"><?php esc_html_e( 'Delete', 'caselens-before-and-after-gallery' ); ?></button>
				</p>
			</div>

			<p class="caselens-field-choices">
				<label><?php esc_html_e( 'Choices, one per line, as value : Label', 'caselens-before-and-after-gallery' ); ?><br />
					<textarea name="<?php echo esc_attr( $name ); ?>[choices]" rows="3" class="large-text code"><?php echo esc_textarea( CaseLens_Fields::choices_to_text( $field['choices'] ) ); ?></textarea>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the field editor form.
	 *
	 * @return void
	 */
	public function save_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these fields.', 'caselens-before-and-after-gallery' ) );
		}
		check_admin_referer( self::FIELDS_ACTION, self::FIELDS_NONCE );

		$raw = isset( $_POST['caselens_field'] ) ? wp_unslash( $_POST['caselens_field'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in normalize().
		$raw = is_array( $raw ) ? $raw : array();

		// Drop rows the user left completely empty.
		$raw = array_filter(
			$raw,
			static function ( $row ) {
				return is_array( $row ) && ( ! empty( $row['label'] ) || ! empty( $row['key'] ) );
			}
		);

		CaseLens_Fields::update( array_values( $raw ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => CASELENS_CPT,
					'page'      => 'caselens-fields',
					'updated'   => 1,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
