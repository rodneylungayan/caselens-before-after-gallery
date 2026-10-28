<?php
/**
 * Template loading, asset loading and the single-case layout.
 *
 * Themes can override any template by copying it into a `caselens` folder
 * inside the theme, for example `wp-content/themes/your-theme/caselens/single-case.php`.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output.
 */
class CaseLens_Templates {

	/**
	 * Set to true once gallery assets have been queued for this request.
	 *
	 * @var bool
	 */
	private $assets_done = false;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'the_content', array( $this, 'single_case_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_single' ) );
	}

	/**
	 * Find a template, preferring the theme's copy.
	 *
	 * @param string $name File name, for example `single-case.php`.
	 * @return string Absolute path.
	 */
	public static function locate( $name ) {
		$found = locate_template( array( 'caselens/' . $name ) );
		if ( $found ) {
			return $found;
		}
		return CASELENS_DIR . 'templates/' . $name;
	}

	/**
	 * Render a template and return its output.
	 *
	 * @param string $name Template file name.
	 * @param array  $vars Variables made available to the template.
	 * @return string
	 */
	public static function render( $name, $vars = array() ) {
		$file = self::locate( $name );
		if ( ! is_readable( $file ) ) {
			return '';
		}

		// Make each entry available to the template under its own name.
		foreach ( $vars as $caselens_name => $caselens_value ) {
			if ( is_string( $caselens_name ) && '' !== $caselens_name ) {
				${$caselens_name} = $caselens_value;
			}
		}
		unset( $caselens_name, $caselens_value );

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Replace the content of a single case with the before/after layout.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function single_case_content( $content ) {
		if ( ! is_singular( CASELENS_CPT ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// A theme template of its own takes over, so leave the content alone.
		if ( locate_template( array( 'single-' . CASELENS_CPT . '.php' ) ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		return self::render(
			'single-case.php',
			array(
				'case_id'     => $post_id,
				'description' => $content,
				'heading'     => CaseLens_Case::heading( $post_id ),
				'photos'      => CaseLens_Case::photos( $post_id ),
				'terms'       => CaseLens_Case::terms( $post_id ),
				'details'     => self::detail_rows( $post_id ),
				'settings'    => CaseLens_Settings::all(),
				'previous'    => CaseLens_Settings::get( 'show_prev_next' ) ? CaseLens_Case::sibling( $post_id, true ) : null,
				'next'        => CaseLens_Settings::get( 'show_prev_next' ) ? CaseLens_Case::sibling( $post_id, false ) : null,
			)
		);
	}

	/**
	 * The procedure menu, as a nested list of real links.
	 *
	 * @param array  $procedures Tree from CaseLens_Query::procedure_tree().
	 * @param string $current    Slug of the procedure being shown.
	 * @return string
	 */
	public static function procedure_nav( $procedures, $current = '' ) {
		if ( empty( $procedures ) ) {
			return '';
		}

		$html = '<ul>';

		foreach ( $procedures as $node ) {
			$has_children = ! empty( $node['children'] );
			$is_current   = $node['slug'] === $current;
			$in_branch    = $is_current || self::branch_contains( $node['children'], $current );

			$classes = array();
			if ( $has_children ) {
				$classes[] = 'caselens-parent';
			}
			if ( $in_branch && $has_children ) {
				$classes[] = 'is-open';
			}

			$html .= '<li' . ( $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '' ) . '>';
			$html .= sprintf(
				'<a class="caselens-term%1$s" href="%2$s" data-slug="%3$s"%4$s>%5$s</a>',
				$is_current ? ' is-current' : '',
				esc_url( $node['url'] ),
				esc_attr( $node['slug'] ),
				$is_current ? ' aria-current="page"' : '',
				esc_html( $node['name'] )
			);

			if ( $has_children ) {
				$html .= self::procedure_nav( $node['children'], $current );
			}

			$html .= '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * True when a slug sits somewhere inside a branch.
	 *
	 * @param array  $children Child nodes.
	 * @param string $slug     Slug to look for.
	 * @return bool
	 */
	private static function branch_contains( $children, $slug ) {
		if ( '' === $slug ) {
			return false;
		}
		foreach ( (array) $children as $child ) {
			if ( $child['slug'] === $slug || self::branch_contains( $child['children'], $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The detail rows shown beside a case, grouped.
	 *
	 * @param int $post_id Case ID.
	 * @return array Group title => array( label => value ).
	 */
	public static function detail_rows( $post_id ) {
		$out = array();

		foreach ( CaseLens_Fields::visible() as $field ) {
			$value = CaseLens_Fields::display_value( $field, $post_id );
			if ( '' === $value ) {
				continue;
			}
			$group = '' !== $field['group'] ? $field['group'] : __( 'Details', 'caselens-before-after-gallery' );
			$out[ $group ][] = array(
				'label' => $field['label'],
				'value' => $value,
			);
		}

		return $out;
	}

	/**
	 * Load the single-case assets when one is being viewed.
	 *
	 * @return void
	 */
	public function maybe_enqueue_single() {
		if ( ! is_singular( CASELENS_CPT ) ) {
			return;
		}
		$this->enqueue_base();
		wp_enqueue_script(
			'caselens-single',
			CASELENS_URL . 'assets/js/single.js',
			array(),
			CASELENS_VERSION,
			true
		);
	}

	/**
	 * Stylesheet plus the accent colour, loaded once per request.
	 *
	 * @return void
	 */
	public function enqueue_base() {
		if ( $this->assets_done ) {
			return;
		}
		$this->assets_done = true;

		wp_enqueue_style(
			'caselens',
			CASELENS_URL . 'assets/css/gallery.css',
			array(),
			CASELENS_VERSION
		);

		$accent = CaseLens_Settings::get( 'accent', '#4fb3b3' );
		wp_add_inline_style(
			'caselens',
			':root{--caselens-accent:' . esc_attr( $accent ) . ';}'
		);
	}

	/**
	 * Everything the gallery script needs.
	 *
	 * @return void
	 */
	public function enqueue_gallery() {
		$this->enqueue_base();

		wp_enqueue_script(
			'caselens-gallery',
			CASELENS_URL . 'assets/js/gallery.js',
			array(),
			CASELENS_VERSION,
			true
		);

		wp_localize_script(
			'caselens-gallery',
			'caseLensData',
			array(
				'rest'       => esc_url_raw( rest_url( CaseLens_REST::NAMESPACE_V1 . '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'base'       => user_trailingslashit( wp_make_link_relative( CaseLens_Settings::gallery_url() ) ),
				'perPage'    => (int) CaseLens_Settings::get( 'per_page', 12 ),
				'procedures' => CaseLens_Query::procedure_tree(),
				'current'    => CaseLens_Rewrites::current_term() ? CaseLens_Rewrites::current_term()->slug : '',
				'strings'    => array(
					'all'        => __( 'All procedures', 'caselens-before-after-gallery' ),
					'loading'    => __( 'Loading…', 'caselens-before-after-gallery' ),
					'none'       => __( 'No cases match your filters.', 'caselens-before-after-gallery' ),
					'error'      => __( 'The gallery could not be loaded. Please try again.', 'caselens-before-after-gallery' ),
					'loadMore'   => __( 'Load more', 'caselens-before-after-gallery' ),
					'filter'     => __( 'Filter', 'caselens-before-after-gallery' ),
					'filterCases' => __( 'Filter cases', 'caselens-before-after-gallery' ),
					'clearAll'   => __( 'Clear all', 'caselens-before-after-gallery' ),
					'apply'      => __( 'Apply filters', 'caselens-before-after-gallery' ),
					'close'      => __( 'Close', 'caselens-before-after-gallery' ),
					'remove'     => __( 'Remove filter', 'caselens-before-after-gallery' ),
					'min'        => __( 'Min', 'caselens-before-after-gallery' ),
					'max'        => __( 'Max', 'caselens-before-after-gallery' ),
					'to'         => __( 'to', 'caselens-before-after-gallery' ),
					'notFound'   => __( 'No case found with that number.', 'caselens-before-after-gallery' ),
				),
			)
		);
	}
}
