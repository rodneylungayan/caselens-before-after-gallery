<?php
/**
 * The Gallery Case post type and the Procedure taxonomy.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's own content types.
 */
class CaseLens_Post_Types {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register' ), 5 );
	}

	/**
	 * Register the post type and taxonomy.
	 *
	 * @return void
	 */
	public function register() {
		$base = CaseLens_Settings::get( 'base', 'gallery' );

		register_post_type(
			CASELENS_CPT,
			array(
				'labels'              => array(
					'name'                  => _x( 'Gallery Cases', 'post type general name', 'caselens-before-after-gallery' ),
					'singular_name'         => _x( 'Gallery Case', 'post type singular name', 'caselens-before-after-gallery' ),
					'menu_name'             => _x( 'Gallery Cases', 'admin menu', 'caselens-before-after-gallery' ),
					'name_admin_bar'        => _x( 'Gallery Case', 'add new on admin bar', 'caselens-before-after-gallery' ),
					'add_new'               => __( 'Add Case', 'caselens-before-after-gallery' ),
					'add_new_item'          => __( 'Add New Case', 'caselens-before-after-gallery' ),
					'new_item'              => __( 'New Case', 'caselens-before-after-gallery' ),
					'edit_item'             => __( 'Edit Case', 'caselens-before-after-gallery' ),
					'view_item'             => __( 'View Case', 'caselens-before-after-gallery' ),
					'all_items'             => __( 'All Cases', 'caselens-before-after-gallery' ),
					'search_items'          => __( 'Search Cases', 'caselens-before-after-gallery' ),
					'not_found'             => __( 'No cases found.', 'caselens-before-after-gallery' ),
					'not_found_in_trash'    => __( 'No cases found in Trash.', 'caselens-before-after-gallery' ),
					'featured_image'        => __( 'Card image', 'caselens-before-after-gallery' ),
					'set_featured_image'    => __( 'Set card image', 'caselens-before-after-gallery' ),
					'remove_featured_image' => __( 'Remove card image', 'caselens-before-after-gallery' ),
					'archives'              => __( 'Case archives', 'caselens-before-after-gallery' ),
					'items_list'            => __( 'Cases list', 'caselens-before-after-gallery' ),
				),
				'description'         => __( 'A single before and after case.', 'caselens-before-after-gallery' ),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => true,
				'show_in_rest'        => true,
				'rest_base'           => 'caselens-cases',
				'menu_position'       => 22,
				'menu_icon'           => 'dashicons-images-alt2',
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes' ),
				'taxonomies'          => array( CASELENS_TAX ),
				'has_archive'         => false,
				'exclude_from_search' => false,
				'rewrite'             => array(
					'slug'       => $base,
					'with_front' => false,
				),
			)
		);

		register_taxonomy(
			CASELENS_TAX,
			array( CASELENS_CPT ),
			array(
				'labels'            => array(
					'name'              => _x( 'Procedures', 'taxonomy general name', 'caselens-before-after-gallery' ),
					'singular_name'     => _x( 'Procedure', 'taxonomy singular name', 'caselens-before-after-gallery' ),
					'menu_name'         => __( 'Procedures', 'caselens-before-after-gallery' ),
					'all_items'         => __( 'All Procedures', 'caselens-before-after-gallery' ),
					'parent_item'       => __( 'Parent Procedure', 'caselens-before-after-gallery' ),
					'parent_item_colon' => __( 'Parent Procedure:', 'caselens-before-after-gallery' ),
					'edit_item'         => __( 'Edit Procedure', 'caselens-before-after-gallery' ),
					'update_item'       => __( 'Update Procedure', 'caselens-before-after-gallery' ),
					'add_new_item'      => __( 'Add New Procedure', 'caselens-before-after-gallery' ),
					'new_item_name'     => __( 'New Procedure Name', 'caselens-before-after-gallery' ),
					'search_items'      => __( 'Search Procedures', 'caselens-before-after-gallery' ),
					'not_found'         => __( 'No procedures found.', 'caselens-before-after-gallery' ),
				),
				'description'       => __( 'Groups of cases, for example a procedure or a service.', 'caselens-before-after-gallery' ),
				'public'            => true,
				'publicly_queryable' => false,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'caselens-procedures',
				'rewrite'           => false,
			)
		);
	}
}
