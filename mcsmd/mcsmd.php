<?php
/**
 * Plugin Name:       Server Status for MC by MrDino
 * Plugin URI:        https://mrdino.es/mcsmd
 * Description:       Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.
 * Version:           0.0.3
 * Author:            MrDino
 * Author URI:        https://mrdino.es
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mcsmd
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin constants.
define( 'MCSMD_VERSION', time() );
define( 'MCSMD_PLUGIN_FILE', __FILE__ );
define( 'MCSMD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCSMD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include required files.
 */
function mcsmd_includes() {
	require_once MCSMD_PLUGIN_DIR . 'includes/class-mcsmd-admin.php';
	require_once MCSMD_PLUGIN_DIR . 'includes/class-mcsmd-frontend.php';
	require_once __DIR__  . '/includes/class-mcsmd-players.php';
}
add_action( 'plugins_loaded', 'mcsmd_includes' );

/**
 * Initialize plugin classes.
 */
function mcsmd_init() {
	// Admin (settings page).
	if ( is_admin() ) {
		new MCSMD_Admin();
	}

	// Frontend (shortcodes, assets).
	new MCSMD_Frontend();
	new MCSMD_Players_List();
}
add_action( 'init', 'mcsmd_init' );
