<?php
/**
 * Titles, descriptions and canonical URLs for procedure pages.
 *
 * Works on plain WordPress and, when Yoast SEO is active, through its filters as well.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Document head values for /{base}/{procedure}/ and single cases.
 */
class CaseLens_SEO {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		foreach ( array( 'wpseo_canonical', 'wpseo_opengraph_url', 'get_canonical_url' ) as $hook ) {
			add_filter( $hook, array( $this, 'canonical' ) );
		}

		foreach ( array( 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title', 'pre_get_document_title' ) as $hook ) {
			add_filter( $hook, array( $this, 'title' ) );
		}

		foreach ( array( 'wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description' ) as $hook ) {
			add_filter( $hook, array( $this, 'description' ) );
		}

		add_action( 'wp_head', array( $this, 'meta_description' ), 1 );
	}

	/**
	 * Canonical URL.
	 *
	 * @param string $url Current canonical URL.
	 * @return string
	 */
	public function canonical( $url ) {
		$term = CaseLens_Rewrites::current_term();
		if ( $term ) {
			return CaseLens_Rewrites::term_url( $term );
		}

		// A case always belongs at its live permalink, even if an SEO plugin cached an older one.
		if ( is_singular( CASELENS_CPT ) ) {
			$link = get_permalink();
			if ( $link ) {
				return $link;
			}
		}

		return $url;
	}

	/**
	 * Document title on a procedure page.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function title( $title ) {
		$term = CaseLens_Rewrites::current_term();
		if ( $term ) {
			$meta = $this->term_meta( $term );
			return $meta['title'];
		}

		// A case with no title of its own would otherwise fall back to the site name.
		if ( is_singular( CASELENS_CPT ) && '' === trim( (string) get_the_title() ) ) {
			$heading = CaseLens_Case::heading( get_queried_object_id() );
			$site    = wp_strip_all_tags( get_bloginfo( 'name' ) );

			return '' !== $site ? $heading . ' - ' . $site : $heading;
		}

		return $title;
	}

	/**
	 * Meta description on a procedure page.
	 *
	 * @param string $description Current description.
	 * @return string
	 */
	public function description( $description ) {
		$term = CaseLens_Rewrites::current_term();
		if ( ! $term ) {
			return $description;
		}

		$meta = $this->term_meta( $term );

		return $meta['desc'];
	}

	/**
	 * Print a description tag on procedure pages when no SEO plugin is doing it.
	 *
	 * @return void
	 */
	public function meta_description() {
		$term = CaseLens_Rewrites::current_term();
		if ( ! $term || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) {
			return;
		}

		$meta = $this->term_meta( $term );
		if ( '' === $meta['desc'] ) {
			return;
		}

		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $meta['desc'] ) );
	}

	/**
	 * Title and description for one procedure.
	 *
	 * @param WP_Term $term Procedure term.
	 * @return array array( 'title' => string, 'desc' => string )
	 */
	private function term_meta( $term ) {
		$name = wp_strip_all_tags( $term->name );
		$site = wp_strip_all_tags( get_bloginfo( 'name' ) );

		$title = sprintf(
			/* translators: 1: procedure name, 2: site name. */
			__( '%1$s Before and After Photos - %2$s', 'caselens-before-and-after-gallery' ),
			$name,
			$site
		);

		$desc = trim( wp_strip_all_tags( (string) $term->description ) );
		if ( '' === $desc ) {
			$desc = sprintf(
				/* translators: %s: procedure name. */
				__( 'See real before and after results for %s. Browse case photos and details.', 'caselens-before-and-after-gallery' ),
				$name
			);
		}

		/**
		 * Filter the generated title and description of a procedure page.
		 *
		 * @param array   $meta array( 'title' => string, 'desc' => string ).
		 * @param WP_Term $term The procedure term.
		 */
		return apply_filters(
			'caselens_procedure_meta',
			array(
				'title' => html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ),
				'desc'  => html_entity_decode( mb_substr( $desc, 0, 155 ), ENT_QUOTES, 'UTF-8' ),
			),
			$term
		);
	}
}
