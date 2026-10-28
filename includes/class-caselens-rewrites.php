<?php
/**
 * Clean gallery URLs.
 *
 *   Procedure  /gallery/tummy-tuck/                  the gallery page, filtered to that procedure
 *   Case       /gallery/tummy-tuck/tummy-tuck-910/   a single case
 *
 * Older links (/gallery/?procedure=x and /gallery/{case}/) are redirected to the new ones.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite rules, permalinks and canonical redirects for the gallery.
 */
class CaseLens_Rewrites {

	const RULES_OPTION = 'caselens_rules';

	/**
	 * The procedure term being shown on a /{base}/{procedure}/ URL.
	 *
	 * @var WP_Term|null
	 */
	private static $current_term = null;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'add_rules' ), 20 );
		add_filter( 'post_type_link', array( $this, 'case_permalink' ), 10, 2 );
		add_filter( 'request', array( $this, 'map_request' ) );
		add_filter( 'redirect_canonical', array( $this, 'keep_procedure_url' ) );
		add_action( 'template_redirect', array( $this, 'canonical_redirects' ), 9 );
		add_action( 'update_option_' . CaseLens_Settings::OPTION, array( __CLASS__, 'schedule_flush' ) );
	}

	/**
	 * The procedure term for the current request, if any.
	 *
	 * @return WP_Term|null
	 */
	public static function current_term() {
		return self::$current_term;
	}

	/**
	 * The URL base, without slashes.
	 *
	 * @return string
	 */
	public static function base() {
		return trim( (string) CaseLens_Settings::get( 'base', 'gallery' ), '/' );
	}

	/**
	 * Public URL of one procedure.
	 *
	 * @param WP_Term $term Procedure term.
	 * @return string
	 */
	public static function term_url( $term ) {
		return user_trailingslashit( home_url( '/' . self::base() . '/' . $term->slug ) );
	}

	/**
	 * Ask for a rewrite flush on the next request.
	 *
	 * @return void
	 */
	public static function schedule_flush() {
		update_option( self::RULES_OPTION, '' );
	}

	/**
	 * Register the case rule, and flush the rules when they are out of date.
	 *
	 * @return void
	 */
	public function add_rules() {
		$base = self::base();

		add_rewrite_rule(
			'^' . preg_quote( $base, '#' ) . '/([^/]+)/([^/]+)/?$',
			'index.php?post_type=' . CASELENS_CPT . '&name=$matches[2]',
			'top'
		);

		$stamp = md5( $base . '|' . CASELENS_VERSION );
		if ( get_option( self::RULES_OPTION ) !== $stamp ) {
			add_action(
				'wp_loaded',
				static function () use ( $stamp ) {
					flush_rewrite_rules( false );
					update_option( self::RULES_OPTION, $stamp );
				}
			);
		}
	}

	/**
	 * Point a case permalink at /{base}/{procedure}/{case}/.
	 *
	 * @param string  $link Current permalink.
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function case_permalink( $link, $post ) {
		if ( ! $post instanceof WP_Post || CASELENS_CPT !== $post->post_type || '' === (string) $post->post_name ) {
			return $link;
		}
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true ) ) {
			return $link;
		}

		$term = CaseLens_Case::primary_term( $post->ID );
		if ( ! $term ) {
			return $link; // No procedure yet, so keep the default URL.
		}

		return user_trailingslashit( home_url( '/' . self::base() . '/' . $term->slug . '/' . $post->post_name ) );
	}

	/**
	 * Serve /{base}/{procedure}/ with the gallery page, and redirect the old ?procedure= URL.
	 *
	 * @param array $query_vars Query vars for this request.
	 * @return array
	 */
	public function map_request( $query_vars ) {
		global $wp;

		$base    = self::base();
		$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';

		// Old format: /gallery/?procedure=tummy-tuck
		if ( $base === $request && isset( $_GET['procedure'] ) && is_scalar( $_GET['procedure'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$slug = sanitize_title( wp_unslash( $_GET['procedure'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$term = $slug ? get_term_by( 'slug', $slug, CASELENS_TAX ) : false;
			if ( $term && ! is_wp_error( $term ) && $this->term_is_public( $term ) ) {
				wp_safe_redirect( self::term_url( $term ), 301 );
				exit;
			}
		}

		// New format: /gallery/tummy-tuck/
		if ( preg_match( '#^' . preg_quote( $base, '#' ) . '/([^/]+)$#', $request, $m ) ) {
			$term = get_term_by( 'slug', sanitize_title( $m[1] ), CASELENS_TAX );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( ! $this->term_is_public( $term ) ) {
					// A real page may already live at that URL; leave it alone if so.
					if ( get_page_by_path( $base . '/' . $term->slug ) ) {
						return $query_vars;
					}
					return array( 'error' => '404' );
				}

				$page_id = $this->gallery_page_id();
				if ( ! $page_id ) {
					return $query_vars;
				}

				self::$current_term = $term;

				return array( 'page_id' => $page_id );
			}
		}

		return $query_vars;
	}

	/**
	 * Keep WordPress from "correcting" /gallery/tummy-tuck/ back to /gallery/.
	 *
	 * @param string|false $redirect_url Canonical URL WordPress wants to use.
	 * @return string|false
	 */
	public function keep_procedure_url( $redirect_url ) {
		return self::$current_term ? false : $redirect_url;
	}

	/**
	 * Send procedure and case URLs to their canonical form.
	 *
	 * @return void
	 */
	public function canonical_redirects() {
		global $wp;

		if ( self::$current_term ) {
			$want = self::term_url( self::$current_term );
			$have = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$want_path = (string) wp_parse_url( $want, PHP_URL_PATH );
			$have_path = '' !== $have ? (string) wp_parse_url( $have, PHP_URL_PATH ) : $want_path;

			if ( untrailingslashit( $have_path ) === untrailingslashit( $want_path ) && $have_path !== $want_path ) {
				wp_safe_redirect( $want, 301 );
				exit;
			}
			return;
		}

		if ( is_singular( CASELENS_CPT ) && ! is_preview() ) {
			$want = get_permalink();
			if ( ! $want ) {
				return;
			}
			$request   = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
			$want_path = trim( (string) wp_parse_url( $want, PHP_URL_PATH ), '/' );
			$have_path = trim( (string) wp_parse_url( home_url( '/' . $request ), PHP_URL_PATH ), '/' );

			if ( $want_path !== $have_path ) {
				wp_safe_redirect( $want, 301 );
				exit;
			}
		}
	}

	/**
	 * ID of the page that holds the gallery.
	 *
	 * @return int 0 when no usable page is configured.
	 */
	private function gallery_page_id() {
		$page_id = (int) CaseLens_Settings::get( 'page_id' );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return $page_id;
		}

		$page = get_page_by_path( self::base() );

		return $page ? (int) $page->ID : 0;
	}

	/**
	 * True when a procedure should be reachable: it has cases, or empty ones are allowed.
	 *
	 * @param WP_Term $term Procedure term.
	 * @return bool
	 */
	private function term_is_public( $term ) {
		if ( ! CaseLens_Settings::get( 'empty_is_404' ) ) {
			return true;
		}
		return CaseLens_Query::term_has_cases( $term );
	}
}
