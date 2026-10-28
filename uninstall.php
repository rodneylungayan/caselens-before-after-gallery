<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Settings are always removed. Cases, procedures and their photos are only
 * removed when "Also delete every case, procedure and setting" is ticked under
 * Gallery Cases > Settings, so deleting the plugin does not throw away content
 * by surprise.
 *
 * @package CaseLens
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Remove every trace of the plugin from one site.
 *
 * @return void
 */
function caselens_uninstall_site() {
	$settings    = get_option( 'caselens_settings', array() );
	$delete_data = is_array( $settings ) && ! empty( $settings['delete_data'] );

	if ( $delete_data ) {
		$case_ids = get_posts(
			array(
				'post_type'        => 'caselens_case',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $case_ids as $case_id ) {
			wp_delete_post( $case_id, true );
		}

		$term_ids = get_terms(
			array(
				'taxonomy'   => 'caselens_procedure',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( ! is_wp_error( $term_ids ) ) {
			foreach ( $term_ids as $term_id ) {
				wp_delete_term( (int) $term_id, 'caselens_procedure' );
			}
		}
	}

	delete_option( 'caselens_settings' );
	delete_option( 'caselens_fields' );
	delete_option( 'caselens_rules' );
	delete_option( 'caselens_version' );

	wp_cache_delete( 'counts', 'caselens' );
}

if ( is_multisite() ) {
	$caselens_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $caselens_sites as $caselens_site_id ) {
		switch_to_blog( (int) $caselens_site_id );
		caselens_uninstall_site();
		restore_current_blog();
	}
} else {
	caselens_uninstall_site();
}
