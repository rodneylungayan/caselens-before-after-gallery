<?php
/**
 * Wires the plugin's parts together.
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin container.
 */
class CaseLens_Plugin {

	/**
	 * The single instance.
	 *
	 * @var CaseLens_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Set to true once init() has run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Templates service, shared with the shortcode.
	 *
	 * @var CaseLens_Templates
	 */
	private $templates;

	/**
	 * The one instance.
	 *
	 * @return CaseLens_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor: use instance().
	 */
	private function __construct() {}

	/**
	 * Register every hook the plugin uses.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$this->templates = new CaseLens_Templates();

		$services = array(
			new CaseLens_Post_Types(),
			new CaseLens_Rewrites(),
			new CaseLens_REST(),
			new CaseLens_SEO(),
			$this->templates,
			new CaseLens_Shortcode( $this->templates ),
		);

		if ( is_admin() ) {
			$services[] = new CaseLens_Meta_Box();
			$services[] = new CaseLens_Admin();
		}

		foreach ( $services as $service ) {
			$service->init();
		}
	}

	/**
	 * The templates service, for themes and other plugins.
	 *
	 * @return CaseLens_Templates
	 */
	public function templates() {
		return $this->templates;
	}

}
