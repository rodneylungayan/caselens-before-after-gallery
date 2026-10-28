<?php
/**
 * The [caselens_gallery] shortcode.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the gallery wherever the shortcode is placed.
 */
class CaseLens_Shortcode {

	/**
	 * Templates service, used to queue assets.
	 *
	 * @var CaseLens_Templates
	 */
	private $templates;

	/**
	 * Constructor.
	 *
	 * @param CaseLens_Templates $templates Templates service.
	 */
	public function __construct( CaseLens_Templates $templates ) {
		$this->templates = $templates;
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'caselens_gallery', array( $this, 'render' ) );
	}

	/**
	 * Render the gallery.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'procedure' => '',
				'per_page'  => (int) CaseLens_Settings::get( 'per_page', 12 ),
				'sidebar'   => 'yes',
				'filters'   => CaseLens_Settings::get( 'enable_filters' ) ? 'yes' : 'no',
				'search'    => CaseLens_Settings::get( 'enable_number' ) ? 'yes' : 'no',
				'columns'   => 3,
				'heading'   => CaseLens_Settings::get( 'heading' ),
			),
			$atts,
			'caselens_gallery'
		);

		$this->templates->enqueue_gallery();

		$term = CaseLens_Rewrites::current_term();
		if ( ! $term && '' !== $atts['procedure'] ) {
			$found = get_term_by( 'slug', sanitize_title( $atts['procedure'] ), CASELENS_TAX );
			if ( $found && ! is_wp_error( $found ) ) {
				$term = $found;
			}
		}

		return CaseLens_Templates::render(
			'gallery.php',
			array(
				'atts'       => $atts,
				'term'       => $term,
				'columns'    => max( 1, min( 6, (int) $atts['columns'] ) ),
				'sidebar'    => 'no' !== $atts['sidebar'],
				'filters'    => 'no' !== $atts['filters'],
				'search'     => 'no' !== $atts['search'],
				'per_page'   => max( 1, min( 60, (int) $atts['per_page'] ) ),
				'settings'   => CaseLens_Settings::all(),
				'procedures' => CaseLens_Query::procedure_tree(),
			)
		);
	}
}
