<?php
/**
 * This file contains callbacks for plugin activation/deactivation
 * and schedules the wiasano_check_posts action and ugrades the database.
 */

/**
 * Callback for when the plugin is activated.
 *
 * This causes the database to be upgraded and starts the cron.
 *
 * @return void
 */
function wiasano_activate_plugin_callback(): void {
	wiasano_upgrade_database();
	wiasano_send_categories();
	wiasano_check_posts();
	wiasano_start_cron();
}

/**
 * Callback for deactivation of the plugin.
 *
 * This stops the cron.
 *
 * @return void
 */
function wiasano_deactivate_plugin_callback(): void {
	wiasano_stop_cron();
}

/**
 * Callback for cron schedules.
 *
 * This adds the wiasano 5min cron interval that is used to check for new posts to be published.
 *
 * @param array $schedules Schedules passed by WP.
 *
 * @return mixed
 */
function wiasano_cron_schedules_callback( $schedules ) {
	$min = 5;
	if ( ! isset( $schedules[ "{$min}min" ] ) ) {
		$schedules['5min'] = array(
			'interval' => $min * 60,
			/* translators: interval. %d: number of minutes between scheduled runs */
			'display'  => sprintf( esc_html__( 'Every %d minutes', 'wiasano' ), esc_html( $min ) ),
		);
	}
	return $schedules;
}

/**
 * Callback for the settings link
 *
 * Adds the link to wiasano settings to the given $links
 *
 * @param array $links Links passed by WP.
 *
 * @return mixed
 */
function wiasano_settings_link_callback( $links ) {
	$url           = esc_url(
		add_query_arg(
			'page',
			'wiasano',
			get_admin_url() . 'admin.php'
		)
	);
	$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
	$links[]       = $settings_link;
	return $links;
}

/**
 * Upgrade the database to the latest DDL using dbDelta.
 *
 * @return void
 */
function wiasano_upgrade_database(): void {
	global $wpdb;
	include_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta(
		'CREATE TABLE ' . $wpdb->prefix . 'wiasano_posts (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
	  todo_id varchar(100) NOT NULL,
	  post_id varchar(100) DEFAULT NULL,
	  title varchar(255) DEFAULT NULL,
	  published_at datetime DEFAULT NULL,
	  error text DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ' . $wpdb->get_charset_collate()
	);
}

/**
 * Schedules the wiasano_check_posts action every 5min
 *
 * @return void
 */
function wiasano_start_cron(): void {
	wiasano_stop_cron();
	if ( ! wp_next_scheduled( 'wiasano_check_posts' ) ) {
		$result = wp_schedule_event( time(), '5min', 'wiasano_check_posts', array(), true );
		if ( is_wp_error( $result ) ) {
			error_log( $result->get_error_message() );
		}
	}
	if ( ! wp_next_scheduled( 'wiasano_send_categories' ) ) {
		$result = wp_schedule_event( time(), 'daily', 'wiasano_send_categories', array(), true );
		if ( is_wp_error( $result ) ) {
			error_log( $result->get_error_message() );
		}
	}
}

/**
 * Un-Schedules the wiasano_check_posts action.
 *
 * @return void
 */
function wiasano_stop_cron(): void {
	$time = wp_next_scheduled( 'wiasano_check_posts' );
	if ( $time ) {
		wp_unschedule_event( $time, 'wiasano_check_posts' );
	}
	$time = wp_next_scheduled( 'wiasano_send_categories' );
	if ( $time ) {
		wp_unschedule_event( $time, 'wiasano_send_categories' );
	}
}
