<?php
/**
 * Plugin Name:       Acreage Core
 * Plugin URI:        https://github.com/sanket5257/acreage-core
 * Description:       Owns the farm listings — post type, taxonomies, fields and the combined filter. Independent of the active theme, so the farms survive any redesign.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Acreage
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acreage
 * Domain Path:       /languages
 * GitHub Plugin URI: sanket5257/acreage-core
 *
 * THE RULE THIS PLUGIN EXISTS TO ENFORCE
 *
 * Every listing, taxonomy term and custom field is registered here, never in a
 * theme. Switch theme tomorrow and all 63 farms, their photographs and their
 * filter terms are still exactly where they were. That is the single most
 * valuable thing we deliver, and it is the deal-breaker in the brief.
 */

defined( 'ABSPATH' ) || exit;

define( 'ACREAGE_CORE_VERSION', '1.2.0' );
define( 'ACREAGE_CORE_FILE', __FILE__ );
define( 'ACREAGE_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACREAGE_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'ACREAGE_CORE_BASENAME', plugin_basename( __FILE__ ) );

/** Repo that publishes releases for this plugin. */
if ( ! defined( 'ACREAGE_CORE_GITHUB_REPO' ) ) {
	define( 'ACREAGE_CORE_GITHUB_REPO', 'sanket5257/acreage-core' );
}

require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-post-types.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-permalinks.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-fields.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-filters.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-query.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-enquiries.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-admin.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-quick-add.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-updater.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-enquiry.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-grid.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-species.php';
require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-search.php';
require_once ACREAGE_CORE_DIR . 'includes/elementor/class-acreage-elementor.php';

/** Boot. */
add_action( 'plugins_loaded', 'acreage_core_boot' );
function acreage_core_boot() {
	load_plugin_textdomain( 'acreage', false, dirname( ACREAGE_CORE_BASENAME ) . '/languages' );

	new Acreage_Core_Post_Types();

	// Elementor widgets. The widget API is free — only Pro's own widgets are paid.
	if ( did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' ) ) {
		new Acreage_Core_Elementor();
	}

	new Acreage_Core_Enquiry();
	new Acreage_Core_Fields();
	new Acreage_Core_Query();
	new Acreage_Core_Enquiries();
	new Acreage_Core_Grid();
	new Acreage_Core_Species();
	new Acreage_Core_Search();

	if ( is_admin() ) {
		new Acreage_Core_Admin();
		new Acreage_Core_Permalinks();
		new Acreage_Core_Quick_Add();
		$GLOBALS['acreage_core_updater'] = new Acreage_Core_Updater( ACREAGE_CORE_GITHUB_REPO );
	} elseif ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$GLOBALS['acreage_core_updater'] = new Acreage_Core_Updater( ACREAGE_CORE_GITHUB_REPO );
	}
}

/**
 * Activation: register everything once, seed the terms the client would
 * otherwise have to type by hand, then flush rewrites so /farms/ works
 * immediately rather than after a mystifying 404.
 */
register_activation_hook( __FILE__, 'acreage_core_activate' );
function acreage_core_activate() {
	require_once ACREAGE_CORE_DIR . 'includes/class-acreage-core-post-types.php';

	$types = new Acreage_Core_Post_Types();
	$types->register_post_type();
	$types->register_taxonomies();
	$types->seed_terms();

	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'acreage_core_deactivate' );
function acreage_core_deactivate() {
	// Rewrites only. Listings and terms stay in the database — deactivating a
	// plugin must never destroy a client's inventory.
	flush_rewrite_rules();
}
