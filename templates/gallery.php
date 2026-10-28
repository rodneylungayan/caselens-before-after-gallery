<?php
/**
 * The gallery: procedure menu, filter bar and the grid of cases.
 *
 * Copy this file to `caselens/gallery.php` inside your theme to change it.
 *
 * Available variables:
 *
 * @var array        $atts       Shortcode attributes.
 * @var WP_Term|null $term       Procedure being shown, if any.
 * @var int          $columns    Grid columns.
 * @var bool         $sidebar    Whether to show the procedure menu.
 * @var bool         $filters    Whether to show the filter button.
 * @var bool         $search     Whether to show the number search.
 * @var int          $per_page   Cases per page.
 * @var array        $settings   Plugin settings.
 * @var array        $procedures Procedure tree.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

$caselens_slug  = $term ? $term->slug : '';
$caselens_first = CaseLens_Query::cards(
	array(
		'procedure' => $caselens_slug,
		'per_page'  => $per_page,
		'page'      => 1,
	)
);
$caselens_heading = $term ? $term->name : $atts['heading'];
$caselens_intro   = $term ? wp_strip_all_tags( (string) $term->description ) : '';
?>
<div
	class="caselens-app<?php echo $sidebar ? '' : ' caselens-app--full'; ?>"
	data-base="<?php echo esc_attr( user_trailingslashit( wp_make_link_relative( CaseLens_Settings::gallery_url() ) ) ); ?>"
	data-procedure="<?php echo esc_attr( $caselens_slug ); ?>"
	data-heading="<?php echo esc_attr( $atts['heading'] ); ?>"
	data-per-page="<?php echo esc_attr( $per_page ); ?>"
	data-columns="<?php echo esc_attr( $columns ); ?>"
	data-pages="<?php echo esc_attr( $caselens_first['pages'] ); ?>">

	<?php if ( $sidebar ) : ?>
		<aside class="caselens-side">
			<?php if ( ! empty( $procedures ) ) : ?>
				<p class="caselens-kicker"><?php esc_html_e( 'Procedures', 'caselens-before-and-after-gallery' ); ?></p>
				<nav class="caselens-nav" aria-label="<?php esc_attr_e( 'Procedures', 'caselens-before-and-after-gallery' ); ?>">
					<ul class="caselens-nav-all">
						<li>
							<a class="caselens-term<?php echo '' === $caselens_slug ? ' is-current' : ''; ?>"
								href="<?php echo esc_url( CaseLens_Settings::gallery_url() ); ?>"
								data-slug=""
								<?php echo '' === $caselens_slug ? ' aria-current="page"' : ''; ?>>
								<?php esc_html_e( 'All procedures', 'caselens-before-and-after-gallery' ); ?>
							</a>
						</li>
					</ul>
					<?php
					// Built and escaped in CaseLens_Templates::procedure_nav().
					echo CaseLens_Templates::procedure_nav( $procedures, $caselens_slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</nav>
			<?php endif; ?>

			<?php if ( $search ) : ?>
				<form class="caselens-number" role="search">
					<label for="caselens-number-input"><?php echo esc_html( $settings['number_label'] ); ?></label>
					<div class="caselens-number-row">
						<input id="caselens-number-input" type="text" inputmode="numeric" autocomplete="off"
							placeholder="<?php esc_attr_e( 'e.g. 234', 'caselens-before-and-after-gallery' ); ?>" />
						<button type="submit"><?php esc_html_e( 'Go', 'caselens-before-and-after-gallery' ); ?></button>
					</div>
				</form>
			<?php endif; ?>
		</aside>
	<?php endif; ?>

	<section class="caselens-main">
		<?php if ( $filters ) : ?>
			<div class="caselens-filterbar">
				<button type="button" class="caselens-filter-btn" aria-haspopup="dialog" aria-expanded="false" hidden>
					<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>
					<span><?php esc_html_e( 'Filter', 'caselens-before-and-after-gallery' ); ?></span>
					<b class="caselens-filter-count" hidden></b>
				</button>
				<div class="caselens-chips" aria-live="polite"></div>
			</div>
		<?php endif; ?>

		<h2 class="caselens-heading"><?php echo esc_html( $caselens_heading ); ?></h2>

		<?php if ( '' !== $caselens_intro ) : ?>
			<p class="caselens-intro"><?php echo esc_html( $caselens_intro ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $settings['disclaimer'] ) : ?>
			<p class="caselens-note"><?php echo esc_html( $settings['disclaimer'] ); ?></p>
		<?php endif; ?>

		<p class="caselens-msg" role="status"></p>

		<div class="caselens-grid" style="--caselens-columns:<?php echo esc_attr( $columns ); ?>">
			<?php if ( empty( $caselens_first['cases'] ) ) : ?>
				<p class="caselens-empty"><?php esc_html_e( 'No cases have been published yet.', 'caselens-before-and-after-gallery' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $caselens_first['cases'] as $caselens_index => $caselens_card ) : ?>
				<a class="caselens-card" href="<?php echo esc_url( $caselens_card['url'] ); ?>">
					<span class="caselens-pair">
						<?php foreach ( $caselens_card['images'] as $caselens_image ) : ?>
							<img src="<?php echo esc_url( $caselens_image['url'] ); ?>"
								alt="<?php echo esc_attr( $caselens_image['alt'] ); ?>"
								<?php if ( $caselens_index >= $columns ) : ?>loading="lazy"<?php endif; ?>
								decoding="async" />
						<?php endforeach; ?>
					</span>
					<span class="caselens-cap"><?php echo esc_html( $caselens_card['title'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<p class="caselens-center">
			<button type="button" class="caselens-more" <?php echo $caselens_first['pages'] > 1 ? '' : 'hidden'; ?>>
				<?php esc_html_e( 'Load more', 'caselens-before-and-after-gallery' ); ?>
			</button>
		</p>
	</section>

	<?php if ( $filters ) : ?>
		<div class="caselens-modal" role="dialog" aria-modal="true" aria-labelledby="caselens-modal-title" hidden>
			<div class="caselens-modal-box">
				<div class="caselens-modal-head">
					<h3 id="caselens-modal-title"><?php esc_html_e( 'Filter cases', 'caselens-before-and-after-gallery' ); ?></h3>
					<button type="button" class="caselens-x" aria-label="<?php esc_attr_e( 'Close', 'caselens-before-and-after-gallery' ); ?>">&times;</button>
				</div>
				<form class="caselens-modal-body"></form>
				<div class="caselens-modal-foot">
					<button type="button" class="caselens-ghost caselens-clear"><?php esc_html_e( 'Clear all', 'caselens-before-and-after-gallery' ); ?></button>
					<button type="button" class="caselens-apply"><?php esc_html_e( 'Apply filters', 'caselens-before-and-after-gallery' ); ?></button>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
