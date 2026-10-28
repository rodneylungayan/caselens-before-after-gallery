<?php
/**
 * One case: before and after photos beside the case details.
 *
 * Copy this file to `caselens/single-case.php` inside your theme to change it.
 *
 * Available variables:
 *
 * @var int          $case_id     Case ID.
 * @var string       $description The case's own content.
 * @var string       $heading     Heading for the case.
 * @var array        $photos      Before/after pairs with image data.
 * @var WP_Term[]    $terms       Procedure terms.
 * @var array        $details     Detail rows grouped by heading.
 * @var array        $settings    Plugin settings.
 * @var WP_Post|null $previous    Previous case.
 * @var WP_Post|null $next        Next case.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

$caselens_lightbox = ! empty( $settings['lightbox'] );
?>
<div class="caselens-case" data-lightbox="<?php echo $caselens_lightbox ? '1' : '0'; ?>">

	<p class="caselens-back">
		<a href="<?php echo esc_url( CaseLens_Settings::gallery_url() ); ?>">
			&laquo; <?php echo esc_html( $settings['back_label'] ); ?>
		</a>
	</p>

	<h1 class="caselens-case-title"><?php echo esc_html( $heading ); ?></h1>

	<?php if ( $previous || $next ) : ?>
		<nav class="caselens-pager" aria-label="<?php esc_attr_e( 'Cases', 'caselens-before-after-gallery' ); ?>">
			<?php if ( $previous ) : ?>
				<a class="caselens-btn" href="<?php echo esc_url( get_permalink( $previous ) ); ?>" rel="prev">
					&laquo; <?php esc_html_e( 'Previous', 'caselens-before-after-gallery' ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $next ) : ?>
				<a class="caselens-btn" href="<?php echo esc_url( get_permalink( $next ) ); ?>" rel="next">
					<?php esc_html_e( 'Next', 'caselens-before-after-gallery' ); ?> &raquo;
				</a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>

	<div class="caselens-case-body">

		<div class="caselens-case-photos">
			<?php if ( empty( $photos ) ) : ?>
				<p class="caselens-empty"><?php esc_html_e( 'No photos have been added to this case yet.', 'caselens-before-after-gallery' ); ?></p>
			<?php else : ?>
				<div class="caselens-slider" data-count="<?php echo esc_attr( count( $photos ) ); ?>">
					<div class="caselens-slides">
						<?php foreach ( $photos as $caselens_index => $caselens_slide ) : ?>
							<div class="caselens-slide" <?php if ( 0 !== $caselens_index ) : ?>hidden<?php endif; ?>>
								<?php
								$caselens_slots = array(
									'before' => __( 'Before', 'caselens-before-after-gallery' ),
									'after'  => __( 'After', 'caselens-before-after-gallery' ),
								);
								?>
								<?php foreach ( $caselens_slots as $caselens_slot => $caselens_label ) : ?>
									<?php $caselens_image = $caselens_slide[ $caselens_slot ]; ?>
									<figure class="caselens-photo caselens-photo--<?php echo esc_attr( $caselens_slot ); ?>">
										<?php if ( $caselens_image && $caselens_image['url'] ) : ?>
											<img
												src="<?php echo esc_url( $caselens_image['url'] ); ?>"
												<?php if ( $caselens_image['srcset'] ) : ?>
													srcset="<?php echo esc_attr( $caselens_image['srcset'] ); ?>"
													sizes="(max-width: 780px) 100vw, 45vw"
												<?php endif; ?>
												alt="<?php echo esc_attr( '' !== $caselens_image['alt'] ? $caselens_image['alt'] : $caselens_label . ' — ' . $heading ); ?>"
												data-full="<?php echo esc_url( (string) $caselens_image['full'] ); ?>"
												<?php if ( $caselens_image['width'] ) : ?>
													width="<?php echo esc_attr( $caselens_image['width'] ); ?>"
													height="<?php echo esc_attr( $caselens_image['height'] ); ?>"
												<?php endif; ?>
												decoding="async"
												<?php if ( 0 !== $caselens_index ) : ?>loading="lazy"<?php endif; ?> />
										<?php else : ?>
											<span class="caselens-photo-missing" aria-hidden="true"></span>
										<?php endif; ?>
										<figcaption><?php echo esc_html( $caselens_label ); ?></figcaption>
									</figure>
								<?php endforeach; ?>

								<?php if ( '' !== $caselens_slide['caption'] ) : ?>
									<p class="caselens-slide-caption"><?php echo esc_html( $caselens_slide['caption'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( count( $photos ) > 1 ) : ?>
						<button type="button" class="caselens-arrow caselens-prev" aria-label="<?php esc_attr_e( 'Previous photos', 'caselens-before-after-gallery' ); ?>" hidden>&#8249;</button>
						<button type="button" class="caselens-arrow caselens-next" aria-label="<?php esc_attr_e( 'Next photos', 'caselens-before-after-gallery' ); ?>" hidden>&#8250;</button>
						<p class="caselens-slider-count" aria-live="polite" hidden></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( '' !== $settings['disclaimer'] ) : ?>
				<p class="caselens-note"><?php echo esc_html( $settings['disclaimer'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== trim( wp_strip_all_tags( $description ) ) ) : ?>
				<div class="caselens-case-text"><?php echo wp_kses_post( $description ); ?></div>
			<?php endif; ?>
		</div>

		<aside class="caselens-case-meta">
			<?php if ( ! empty( $terms ) ) : ?>
				<h2 class="caselens-meta-title"><?php esc_html_e( 'Procedures', 'caselens-before-after-gallery' ); ?></h2>
				<ul class="caselens-term-list">
					<?php foreach ( $terms as $caselens_term ) : ?>
						<li>
							<a href="<?php echo esc_url( CaseLens_Rewrites::term_url( $caselens_term ) ); ?>">
								<?php echo esc_html( $caselens_term->name ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $details ) ) : ?>
				<h2 class="caselens-meta-title"><?php esc_html_e( 'Case Details', 'caselens-before-after-gallery' ); ?></h2>
				<?php foreach ( $details as $caselens_group => $caselens_rows ) : ?>
					<?php if ( count( $details ) > 1 ) : ?>
						<h3 class="caselens-meta-group"><?php echo esc_html( $caselens_group ); ?></h3>
					<?php endif; ?>
					<dl class="caselens-details">
						<?php foreach ( $caselens_rows as $caselens_row ) : ?>
							<dt><?php echo esc_html( $caselens_row['label'] ); ?></dt>
							<dd><?php echo esc_html( $caselens_row['value'] ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( '' !== $settings['cta_url'] && '' !== $settings['cta_label'] ) : ?>
				<p class="caselens-cta">
					<a class="caselens-btn caselens-btn--solid" href="<?php echo esc_url( $settings['cta_url'] ); ?>">
						<?php echo esc_html( $settings['cta_label'] ); ?>
					</a>
				</p>
			<?php endif; ?>
		</aside>
	</div>
</div>
