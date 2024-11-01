<?php
/**
 * Plugin Name: wiasano
 * Plugin URI: https://wordpress.org/plugins/wiasano/
 * Description: The official wiasano WordPress Plugin allows you to connect your blog with wiasano so you can schedule and publish your posts automatically.
 * Version: 1.2.1
 * Author: wiasano GmbH
 * Requires PHP: 8.0
 * Text Domain: wiasano
 * Domain Path: /languages/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 **/

namespace wiasano;

// Deny direct access.
if ( ! function_exists( 'add_action' ) ) {
	echo esc_html__( 'Direct access to this plugin is not supported.', 'wiasano' );
	exit;
}

define( 'WIASANO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );


require_once WIASANO_PLUGIN_DIR . 'class-wiasanoclient.php';
require_once WIASANO_PLUGIN_DIR . 'core.php';
require_once WIASANO_PLUGIN_DIR . 'scaffold.php';
require_once WIASANO_PLUGIN_DIR . 'class-wiasanopoststable.php';
require_once WIASANO_PLUGIN_DIR . 'admin.php';


/**
 * PLUGIN REGISTRATION & CRON (scaffold)
 */
add_action( 'plugins_loaded', 'wiasano_load_plugin_textdomain' );
add_filter( 'cron_schedules', 'wiasano_cron_schedules_callback' );
register_activation_hook( __FILE__, 'wiasano_activate_plugin_callback' );
register_deactivation_hook( __FILE__, 'wiasano_deactivate_plugin_callback' );


/**
 * ADMIN
 */

add_action( 'admin_init', 'wiasano_admin_init_callback' );
add_action( 'admin_menu', 'wiasano_admin_menu_callback' );
add_action( 'admin_enqueue_scripts', 'wiasano_enqueue_admin_scripts_callback' );
add_action( 'updated_option', 'wiasano_on_option_updated' );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wiasano_settings_link_callback' );

/**
 * CORE
 */
add_action( 'upgrader_process_complete', 'wiasano_check_update', 10, 2 );
add_action( 'wiasano_check_posts', 'wiasano_check_posts' );
add_action( 'wiasano_send_categories', 'wiasano_send_categories' );
