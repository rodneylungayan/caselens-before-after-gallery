<?php
/**
 * The two boxes on the case editor: before/after photos and case details.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case editor meta boxes.
 */
class CaseLens_Meta_Box {

	const NONCE_ACTION = 'caselens_save_case';
	const NONCE_NAME   = 'caselens_case_nonce';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'add_meta_boxes_' . CASELENS_CPT, array( $this, 'register' ) );
		add_action( 'save_post_' . CASELENS_CPT, array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Add the boxes.
	 *
	 * @return void
	 */
	public function register() {
		add_meta_box(
			'caselens-photos',
			__( 'Before and After Photos', 'caselens-before-after-gallery' ),
			array( $this, 'render_photos' ),
			CASELENS_CPT,
			'normal',
			'high'
		);

		if ( CaseLens_Fields::all() ) {
			add_meta_box(
				'caselens-details',
				__( 'Case Details', 'caselens-before-after-gallery' ),
				array( $this, 'render_details' ),
				CASELENS_CPT,
				'normal',
				'default'
			);
		}
	}

	/**
	 * The repeatable before/after rows.
	 *
	 * @param WP_Post $post Case being edited.
	 * @return void
	 */
	public function render_photos( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$pairs = CaseLens_Case::pairs( $post->ID );
		?>
		<p class="description">
			<?php esc_html_e( 'Each row is one before and after pair. The first row is used for the card in the gallery grid.', 'caselens-before-after-gallery' ); ?>
		</p>

		<div id="caselens-pairs" class="caselens-pairs">
			<?php foreach ( $pairs as $index => $pair ) : ?>
				<?php $this->render_pair_row( $index, $pair ); ?>
			<?php endforeach; ?>
		</div>

		<p>
			<button type="button" class="button" id="caselens-add-pair">
				<?php esc_html_e( 'Add photo pair', 'caselens-before-after-gallery' ); ?>
			</button>
		</p>

		<script type="text/html" id="tmpl-caselens-pair">
			<?php $this->render_pair_row( '__index__', array( 'before' => 0, 'after' => 0, 'caption' => '' ) ); ?>
		</script>
		<?php
	}

	/**
	 * One before/after row.
	 *
	 * @param int|string $index Row index, or the template placeholder.
	 * @param array      $pair  Pair values.
	 * @return void
	 */
	private function render_pair_row( $index, $pair ) {
		$slots = array(
			'before' => __( 'Before', 'caselens-before-after-gallery' ),
			'after'  => __( 'After', 'caselens-before-after-gallery' ),
		);
		?>
		<div class="caselens-pair">
			<span class="caselens-pair-handle dashicons dashicons-menu" aria-hidden="true"></span>

			<?php foreach ( $slots as $slot => $label ) : ?>
				<?php
				$id  = isset( $pair[ $slot ] ) ? (int) $pair[ $slot ] : 0;
				$url = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
				?>
				<div class="caselens-slot" data-slot="<?php echo esc_attr( $slot ); ?>">
					<span class="caselens-slot-label"><?php echo esc_html( $label ); ?></span>
					<button type="button" class="caselens-pick<?php echo $url ? ' has-image' : ''; ?>">
						<?php if ( $url ) : ?>
							<img src="<?php echo esc_url( $url ); ?>" alt="" />
						<?php else : ?>
							<span class="caselens-pick-text"><?php esc_html_e( 'Choose image', 'caselens-before-after-gallery' ); ?></span>
						<?php endif; ?>
					</button>
					<input
						type="hidden"
						class="caselens-slot-id"
						name="caselens_pairs[<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $slot ); ?>]"
						value="<?php echo esc_attr( $id ); ?>" />
					<button type="button" class="button-link caselens-clear"<?php echo $url ? '' : ' hidden'; ?>>
						<?php esc_html_e( 'Remove', 'caselens-before-after-gallery' ); ?>
					</button>
				</div>
			<?php endforeach; ?>

			<div class="caselens-pair-meta">
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Caption', 'caselens-before-after-gallery' ); ?></span>
					<input
						type="text"
						class="widefat"
						placeholder="<?php esc_attr_e( 'Caption (optional)', 'caselens-before-after-gallery' ); ?>"
						name="caselens_pairs[<?php echo esc_attr( $index ); ?>][caption]"
						value="<?php echo esc_attr( isset( $pair['caption'] ) ? $pair['caption'] : '' ); ?>" />
				</label>
				<button type="button" class="button-link delete caselens-remove-pair">
					<?php esc_html_e( 'Delete row', 'caselens-before-after-gallery' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * The case detail fields.
	 *
	 * @param WP_Post $post Case being edited.
	 * @return void
	 */
	public function render_details( $post ) {
		$groups = CaseLens_Fields::by_group();
		?>
		<div class="caselens-details">
			<?php foreach ( $groups as $group => $fields ) : ?>
				<h4 class="caselens-group"><?php echo esc_html( $group ); ?></h4>
				<table class="form-table" role="presentation">
					<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<?php
						$name  = 'caselens_fields[' . $field['key'] . ']';
						$input = 'caselens-field-' . $field['key'];
						$value = get_post_meta( $post->ID, CaseLens_Fields::meta_key( $field['key'] ), true );
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $input ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
							</th>
							<td>
								<?php if ( 'select' === $field['type'] ) : ?>
									<select id="<?php echo esc_attr( $input ); ?>" name="<?php echo esc_attr( $name ); ?>">
										<option value=""><?php esc_html_e( '— Not set —', 'caselens-before-after-gallery' ); ?></option>
										<?php foreach ( $field['choices'] as $choice_value => $choice_label ) : ?>
											<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( (string) $value, (string) $choice_value ); ?>>
												<?php echo esc_html( $choice_label ); ?>
											</option>
										<?php endforeach; ?>
									</select>

								<?php elseif ( 'multiselect' === $field['type'] ) : ?>
									<?php $selected = is_array( $value ) ? array_map( 'strval', $value ) : array(); ?>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html( $field['label'] ); ?></legend>
										<?php foreach ( $field['choices'] as $choice_value => $choice_label ) : ?>
											<label class="caselens-check">
												<input
													type="checkbox"
													name="<?php echo esc_attr( $name ); ?>[]"
													value="<?php echo esc_attr( $choice_value ); ?>"
													<?php checked( in_array( (string) $choice_value, $selected, true ) ); ?> />
												<?php echo esc_html( $choice_label ); ?>
											</label>
										<?php endforeach; ?>
									</fieldset>

								<?php elseif ( 'number' === $field['type'] ) : ?>
									<input
										type="number"
										step="any"
										id="<?php echo esc_attr( $input ); ?>"
										name="<?php echo esc_attr( $name ); ?>"
										value="<?php echo esc_attr( is_scalar( $value ) ? $value : '' ); ?>"
										class="small-text" />
									<?php if ( '' !== $field['suffix'] ) : ?>
										<span class="description"><?php echo esc_html( $field['suffix'] ); ?></span>
									<?php endif; ?>

								<?php else : ?>
									<input
										type="text"
										id="<?php echo esc_attr( $input ); ?>"
										name="<?php echo esc_attr( $name ); ?>"
										value="<?php echo esc_attr( is_scalar( $value ) ? $value : '' ); ?>"
										class="regular-text" />
									<?php if ( '' !== $field['suffix'] ) : ?>
										<span class="description"><?php echo esc_html( $field['suffix'] ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>
		</div>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to the Case Fields screen. */
				esc_html__( 'These fields are defined under %s.', 'caselens-before-after-gallery' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=' . CASELENS_CPT . '&page=caselens-fields' ) ) . '">' . esc_html__( 'Case Fields', 'caselens-before-after-gallery' ) . '</a>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Save both boxes.
	 *
	 * @param int     $post_id Case ID.
	 * @param WP_Post $post    Case object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$pairs = isset( $_POST['caselens_pairs'] ) ? wp_unslash( $_POST['caselens_pairs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in save_pairs().
		CaseLens_Case::save_pairs( $post_id, is_array( $pairs ) ? $pairs : array() );

		$submitted = isset( $_POST['caselens_fields'] ) ? wp_unslash( $_POST['caselens_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.
		$submitted = is_array( $submitted ) ? $submitted : array();

		foreach ( CaseLens_Fields::all() as $field ) {
			$meta_key = CaseLens_Fields::meta_key( $field['key'] );
			$raw      = isset( $submitted[ $field['key'] ] ) ? $submitted[ $field['key'] ] : '';
			$value    = CaseLens_Fields::sanitize_value( $field, $raw );

			if ( '' === $value || array() === $value ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}

		CaseLens_Query::flush_counts();
	}
}
