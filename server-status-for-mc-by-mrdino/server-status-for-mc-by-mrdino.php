<?php
/**
 * Plugin Name:       Server Status for MC by MrDino
 * Plugin URI:        https://mrdino.es/mcsmd
 * Description:       Display your Minecraft server status on your WordPress site. Basic mode works without any Minecraft plugin.
 * Version:           0.0.6
 * Author:            MrDino
 * Author URI:        https://mrdino.es
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       server-status-for-mc-by-mrdino
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
if ( ! defined( 'MCSMD_VERSION' ) ) {
	define( 'MCSMD_VERSION', time() );
}

if ( ! defined( 'MCSMD_PLUGIN_FILE' ) ) {
	define( 'MCSMD_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MCSMD_PLUGIN_DIR' ) ) {
	define( 'MCSMD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MCSMD_PLUGIN_URL' ) ) {
	define( 'MCSMD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Include required class files.
 * Asegúrate de que estos archivos existen con estos nombres
 * dentro de la carpeta /includes/.
 */
require_once MCSMD_PLUGIN_DIR . 'includes/class-mcsmd-admin.php';
require_once MCSMD_PLUGIN_DIR . 'includes/class-mcsmd-frontend.php';
require_once MCSMD_PLUGIN_DIR . 'includes/class-mcsmd-players.php';

/**
 * Bootstrap del plugin.
 */
function mcsmd_init_plugin() {

	// Página de ajustes (solo en admin).
	if ( is_admin() && class_exists( 'MCSMD_Admin' ) ) {
		new MCSMD_Admin();
	}

	// Frontend (shortcode principal).
	if ( class_exists( 'MCSMD_Frontend' ) ) {
		new MCSMD_Frontend();
	}

	// Shortcode de lista de jugadores.
	if ( class_exists( 'MCSMD_Players_List' ) ) {
		new MCSMD_Players_List();
	}
}
add_action( 'init', 'mcsmd_init_plugin' );

