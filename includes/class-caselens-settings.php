<?php
/**
 * Plugin settings: storage, defaults and sanitising.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single `caselens_settings` option.
 */
class CaseLens_Settings {

	const OPTION = 'caselens_settings';

	/**
	 * Cached settings for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Factory defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'base'            => 'gallery',
			'page_id'         => 0,
			'per_page'        => 12,
			'hide_empty'      => 1,
			'empty_is_404'    => 1,
			'accent'          => '#4fb3b3',
			'heading'         => __( 'Before and After Photo Gallery', 'caselens-before-after-gallery' ),
			'disclaimer'      => __( 'Individual results may vary', 'caselens-before-after-gallery' ),
			'case_noun'       => __( 'Patient', 'caselens-before-after-gallery' ),
			'number_label'    => __( 'Patient number', 'caselens-before-after-gallery' ),
			'enable_filters'  => 1,
			'enable_number'   => 1,
			'show_prev_next'  => 1,
			'cta_label'       => __( 'Schedule a consultation', 'caselens-before-after-gallery' ),
			'cta_url'         => '',
			'back_label'      => __( 'Return to search results', 'caselens-before-after-gallery' ),
			'procedure_order' => '',
			'lightbox'        => 1,
			'delete_data'     => 0,
		);
	}

	/**
	 * All settings, defaults filled in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Returned when the setting is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Replace the stored settings.
	 *
	 * @param array $values Raw values.
	 * @return void
	 */
	public static function update( array $values ) {
		update_option( self::OPTION, self::sanitize( $values ) );
		self::$cache = null;
	}

	/**
	 * Forget the per-request cache (used after an option write elsewhere).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Sanitise a full settings array.
	 *
	 * @param mixed $input Raw input, normally from $_POST.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$out    = self::defaults();
		$checks = array( 'hide_empty', 'empty_is_404', 'enable_filters', 'enable_number', 'show_prev_next', 'lightbox', 'delete_data' );

		$base = isset( $input['base'] ) ? sanitize_title( wp_unslash( $input['base'] ) ) : '';
		if ( '' !== $base ) {
			$out['base'] = $base;
		}

		$out['page_id']  = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;
		$out['per_page'] = isset( $input['per_page'] ) ? min( 60, max( 3, absint( $input['per_page'] ) ) ) : 12;

		foreach ( $checks as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$accent = isset( $input['accent'] ) ? sanitize_hex_color( wp_unslash( $input['accent'] ) ) : '';
		if ( $accent ) {
			$out['accent'] = $accent;
		}

		foreach ( array( 'heading', 'disclaimer', 'case_noun', 'number_label', 'cta_label', 'back_label' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( wp_unslash( $input[ $key ] ) );
			}
		}

		$out['cta_url'] = isset( $input['cta_url'] ) ? esc_url_raw( wp_unslash( $input['cta_url'] ) ) : '';

		if ( isset( $input['procedure_order'] ) ) {
			$lines = preg_split( '/[\r\n,]+/', (string) wp_unslash( $input['procedure_order'] ) );
			$lines = array_filter( array_map( 'sanitize_title', (array) $lines ) );
			$out['procedure_order'] = implode( "\n", array_unique( $lines ) );
		}

		return $out;
	}

	/**
	 * Procedure order as a list of slugs.
	 *
	 * @return string[]
	 */
	public static function order_slugs() {
		$raw = (string) self::get( 'procedure_order', '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/**
	 * URL of the gallery index page.
	 *
	 * @return string
	 */
	public static function gallery_url() {
		$page_id = (int) self::get( 'page_id' );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			$link = get_permalink( $page_id );
			if ( $link ) {
				return $link;
			}
		}
		return user_trailingslashit( home_url( '/' . self::get( 'base' ) ) );
	}
}
