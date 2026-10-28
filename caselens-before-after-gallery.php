<?php
/**
 * Plugin Name:       CaseLens Before and After Gallery
 * Plugin URI:        https://rodneylungayan.com/wordpress-plugins/caselens-before-after-gallery/
 * Description:       A filterable before and after case gallery. Adds a Gallery Cases post type with procedure categories, patient detail fields you define yourself, and a searchable front-end gallery with before/after photo pairs.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Rodney Lungayan
 * Author URI:        https://rodneylungayan.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caselens-before-and-after-gallery
 *
 * @package CaseLens
 */

defined( 'ABSPATH' ) || exit;

define( 'CASELENS_VERSION', '1.0.0' );
define( 'CASELENS_FILE', __FILE__ );
define( 'CASELENS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CASELENS_URL', plugin_dir_url( __FILE__ ) );

/** Post type and taxonomy keys. */
define( 'CASELENS_CPT', 'caselens_case' );
define( 'CASELENS_TAX', 'caselens_procedure' );

require_once CASELENS_DIR . 'includes/class-caselens-settings.php';
require_once CASELENS_DIR . 'includes/class-caselens-fields.php';
require_once CASELENS_DIR . 'includes/class-caselens-post-types.php';
require_once CASELENS_DIR . 'includes/class-caselens-case.php';
require_once CASELENS_DIR . 'includes/class-caselens-rewrites.php';
require_once CASELENS_DIR . 'includes/class-caselens-query.php';
require_once CASELENS_DIR . 'includes/class-caselens-rest.php';
require_once CASELENS_DIR . 'includes/class-caselens-templates.php';
require_once CASELENS_DIR . 'includes/class-caselens-shortcode.php';
require_once CASELENS_DIR . 'includes/class-caselens-seo.php';
require_once CASELENS_DIR . 'includes/class-caselens-meta-box.php';
require_once CASELENS_DIR . 'includes/class-caselens-admin.php';
require_once CASELENS_DIR . 'includes/class-caselens-activator.php';
require_once CASELENS_DIR . 'includes/class-caselens-plugin.php';

/**
 * Boot the plugin once all other plugins are loaded.
 *
 * @return void
 */
function caselens_boot() {
	CaseLens_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'caselens_boot' );

register_activation_hook( __FILE__, array( 'CaseLens_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CaseLens_Activator', 'deactivate' ) );
