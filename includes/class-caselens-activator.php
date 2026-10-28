<?php
/**
 * What happens when the plugin is switched on and off.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activation and deactivation routines.
 */
class CaseLens_Activator {

	/**
	 * Seed the options, make sure a gallery page exists, then refresh the rewrite rules.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( CaseLens_Settings::OPTION, false ) ) {
			add_option( CaseLens_Settings::OPTION, CaseLens_Settings::defaults() );
		}

		if ( ! is_array( get_option( CaseLens_Fields::OPTION, null ) ) ) {
			update_option( CaseLens_Fields::OPTION, CaseLens_Fields::normalize( CaseLens_Fields::defaults() ) );
		}

		CaseLens_Settings::flush_cache();
		self::ensure_gallery_page();

		// The post type has to exist before the rules are rebuilt.
		$post_types = new CaseLens_Post_Types();
		$post_types->register();

		$rewrites = new CaseLens_Rewrites();
		$rewrites->add_rules();

		flush_rewrite_rules( false );

		update_option( 'caselens_version', CASELENS_VERSION );
	}

	/**
	 * Clear the rewrite rules so the gallery URLs stop resolving.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules( false );
	}

	/**
	 * Create the gallery page the first time, unless one is already configured.
	 *
	 * @return void
	 */
	private static function ensure_gallery_page() {
		$settings = get_option( CaseLens_Settings::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$page_id  = isset( $settings['page_id'] ) ? (int) $settings['page_id'] : 0;

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return;
		}

		$base     = isset( $settings['base'] ) && '' !== $settings['base'] ? $settings['base'] : 'gallery';
		$existing = get_page_by_path( $base );

		if ( $existing instanceof WP_Post ) {
			$settings['page_id'] = (int) $existing->ID;
			$settings['base']    = $existing->post_name;
			update_option( CaseLens_Settings::OPTION, $settings );
			CaseLens_Settings::flush_cache();
			return;
		}

		$new_id = wp_insert_post(
			array(
				'post_title'   => __( 'Gallery', 'caselens-before-after-gallery' ),
				'post_name'    => $base,
				'post_content' => '[caselens_gallery]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => get_current_user_id(),
			)
		);

		if ( $new_id && ! is_wp_error( $new_id ) ) {
			$settings['page_id'] = (int) $new_id;
			$settings['base']    = get_post_field( 'post_name', $new_id );
			update_option( CaseLens_Settings::OPTION, $settings );
			CaseLens_Settings::flush_cache();
		}
	}
}
