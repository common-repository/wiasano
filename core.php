<?php
const WIASANO_DEFAULT_POST_TYPE = 'post';

/**
 * This hook runs after an update and executes wiasano_after_update() if our plugin was updated.
 *
 * @param mixed $upgrader_object WP param.
 * @param mixed $options WP param.
 *
 * @return void
 */
function wiasano_check_update( $upgrader_object, $options ) {
	if ( $options['action'] === 'update' && $options['type'] === 'plugin' ) {
		// Check if our plugin is in the list of updated plugins.
		foreach ( $options['plugins'] as $plugin ) {
			if ( $plugin === plugin_basename( __FILE__ ) ) {
				wiasano_after_update();
			}
		}
	}
}

/**
 * Runs after this plugin was updated.
 *
 * @return void
 */
function wiasano_after_update() {
	wiasano_start_cron();
	wiasano_send_categories();
}


/**
 * Loads the wiasano text domain.
 *
 * @return void
 */
function wiasano_load_plugin_textdomain() {
	load_plugin_textdomain( 'wiasano', false, basename( __DIR__ ) . '/languages/' );
}

/**
 * Returns a new WiasanoClient using the wiasano token from the settings.
 *
 * @return WiasanoClient
 */
function wiasano_client(): WiasanoClient {
	return new WiasanoClient( 'https://app.wiasano.com', wiasano_token() );
}

/**
 * Returns the wiasano token stored in the settings.
 *
 * @return string
 */
function wiasano_token(): string {
	return wiasano_get( wiasano_settings(), 'wiasano_token', '' );
}

/**
 * Returns the post type that should be used when creating posts.
 *
 * @return string
 */
function wiasano_post_type(): string {
	return wiasano_get( wiasano_settings(), 'wiasano_post_type', WIASANO_DEFAULT_POST_TYPE );
}

/**
 * Returns a list of all field mappings
 *
 * @return array|null
 */
function wiasano_field_mapping(): array {
	return wiasano_get( wiasano_settings(), 'wiasano_field_mapping', array() );
}

/**
 * A function that can get array items using the dot notation.
 *
 * @param array  $arr An array.
 * @param string $key A key, e.g. "title".
 * @param mixed  $def A default value.
 *
 * @return mixed|null
 */
function wiasano_get( $arr, $key, $def = null ) {
	if ( is_null( $arr ) || is_null( $key ) ) {
		return null;
	}
	$dot_pos = strpos( $key, '.' );
	if ( false === $dot_pos ) {
		$val = array_key_exists( $key, $arr ) ? $arr[ $key ] : null;
		return is_null( $val ) ? $def : $val;
	} else {
		$first_key  = substr( $key, 0, $dot_pos );
		$rest_key   = substr( $key, $dot_pos + 1 );
		$nested_arr = array_key_exists( $first_key, $arr ) ? $arr[ $first_key ] : null;
		return is_null( $nested_arr ) ? $def : wiasano_get( $nested_arr, $rest_key, $def );
	}
}

/**
 * Returns the wiasano settings object
 *
 * @return mixed
 */
function wiasano_settings(): mixed {
	return get_option(
		'wiasano_options',
		array(
			'wiasano_token'         => '',
			'wiasano_post_type'     => WIASANO_DEFAULT_POST_TYPE,
			'wiasano_field_mapping' => array(),
		)
	);
}

/**
 * Returns all custom fields that are defined in WordPress.
 * NOTE: they have the be in use to actually be found.
 *
 * @return array
 */
function wiasano_get_wordpress_custom_fields() {
	global $wpdb;
	$meta_keys = array();

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"
        SELECT DISTINCT pm.meta_key
        FROM {$wpdb->postmeta} pm
        LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key NOT LIKE %s
    ",
			'\_%'
		)
	);

	foreach ( $results as $result ) {
		$meta_keys[] = $result->meta_key;
	}

	return $meta_keys;
}

/**
 * An array of all fields that can be mapped from a wiasano to-do to a WordPress post.
 * If the plugin is connected to the user's wiasano account, then this list contains the defined custom fields.
 *
 * @return array
 */
function wiasano_get_wiasano_all_fields(): array {
	$standard_fields = array(
		'title'      => 'Titel (Text)',
		'content'    => 'Blog-Post (HTML)',
		'cta'        => 'Call-To-Action (HTML)',
		'topic'      => 'Thema (Text)',
		'media.0'    => 'Beitragsbild',
		'categories' => 'Kategorien (Text)',
	);

	$wiasano_custom_field_names = wiasano_client()->list_custom_fields() ?? array();
	$wiasano_custom_fields      = array();
	foreach ( $wiasano_custom_field_names as $wiasano_custom_field_name ) {
		$wiasano_custom_fields[ "custom.$wiasano_custom_field_name" ] = "$wiasano_custom_field_name (HTML)";
	}

	return array_merge( $standard_fields, $wiasano_custom_fields );
}

/**
 * Send the WP Categories to wiasano so they may be used when writing blog posts.
 *
 * @return void
 */
function wiasano_send_categories() {
	if ( ! function_exists( 'get_categories' ) ) {
		include_once ABSPATH . 'wp-includes/category.php';
	}
	$categories = get_categories(
		array(
			'hide_empty' => false,
		)
	);
	wiasano_client()->send_categories( $categories );
}

/**
 * Checks wiasano CMS API for Todos to be published.
 * Iterates over these todos (if any) and creates a WordPress posts for each of them.
 *
 * @return void
 */
function wiasano_check_posts(): void {
	$todos = wiasano_client()->list_todos() ?? array();

	foreach ( $todos as $todo ) {
		wiasano_add_post( $todo );
	}
}

/**
 * Add a post for a wiasano Todo.
 * Posts are created in "published" state. If the Todo has a featured image,
 * then it is sideloaded into the media library and added to the post.
 *
 * @param array $todo A wiasano ToDo.
 *
 * @return void
 */
function wiasano_add_post( $todo ) {
	$id = wiasano_get( $todo, 'id' );

	if ( is_null( ! $id ) ) {
		error_log( 'No ID for todo: ' . wp_json_encode( $todo ) );
	}
	wiasano_ensure_todo( $id );

	try {
		$props = wiasano_get( $todo, 'properties' );

		if ( ! $props ) {
			wiasano_report_error( $id, "Todo $id has no properties" );
		}

		if ( wiasano_post_exists_for_todo( $id ) ) {
			return;
		}

		// Post options.
		$todo_title = wiasano_get( $props, 'title' );
		$content    = wiasano_get( $props, 'content' );
		$cta        = wiasano_get( $props, 'cta' );
		$cats       = wiasano_get( $props, 'categories', array() );

		// CTA.
		if ( ! is_null( $cta ) ) {
			$content .= '<div>' . $cta . '</div>';
		}

		// Post Options.
		$options = array(
			'post_title'   => $todo_title,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => wiasano_post_type(),
		);

		// Categories.
		$needs_category_sync = false;
		if ( is_array( $cats ) && count( $cats ) > 0 ) {
			if ( ! function_exists( 'category_exists' ) ) {
				include_once ABSPATH . 'wp-admin/includes/taxonomy.php';
			}
			$cat_ids    = array();
			$cats_count = count( $cats );
			for ( $i = 0; $i < $cats_count; $i++ ) {
				$cat = $cats[ $i ];
				if ( is_array( $cat ) && array_key_exists( 'cat_ID', $cat ) ) {
					// we have a cat_ID that we can use.
					$cat_id = wiasano_get( $cat, 'cat_ID' );
					if ( ! is_null( $cat_id ) && ! is_null( category_exists( intval( $cat_id ) ) ) ) {
						// it still exists, so we can use it.
						$cat_ids[] = intval( $cat_id );
					} else {
						// it doesn't exist, so we need to update the cats in wiasano.
						// Note: we don't create the category here because that is not the user's intent in this case.
						$needs_category_sync = true;
					}
				} elseif ( is_string( $cat ) ) {
					// we don't have a cat_ID but maybe it already exists?
					$existing_cat_id = category_exists( $cat );
					if ( is_null( $existing_cat_id ) ) {
						// new category.
						$new_cat_id = wp_create_category( $cat );
						if ( 0 !== $new_cat_id ) {
							$cat_ids[]           = $new_cat_id;
							$needs_category_sync = true;
						} else {
							error_log( "Error creating category for ToDo $id: " . wp_json_encode( $cat ) );
						}
					} else {
						// existing category.
						$cat_ids[] = $existing_cat_id;
					}
				} else {
					// some unknown structure.
					error_log( "Invalid category in ToDo $id: " . wp_json_encode( $cat ) );
				}
			}
			if ( count( $cat_ids ) > 0 ) {
				$options['post_category'] = $cat_ids;
			}
		}

		// Create Post.
		$post_id = wp_insert_post( $options, true );

		// Error handling.
		if ( is_wp_error( $post_id ) ) {
			wiasano_report_error( $id, $post_id->get_error_message() );
			return;
		}

		// Store $post_id in DB.
		$published_at_mysql = current_time( 'mysql', 1 );
		wiasano_db_update_todo( $id, $post_id, $todo_title, $published_at_mysql );

		// Send $post_id back to wiasano app, along with url and timestamp.
		$url            = get_permalink( $post_id );
		$published_at_u = time();
		wiasano_client()->update_todo( $id, $post_id, $published_at_u, $url, null );

		// Set thumbnail.
		$media_id = null;
		$medias   = wiasano_get( $props, 'media', array() );
		if ( count( $medias ) > 0 ) {
			$media     = $medias[0];
			$media_url = $media['url'];
			if ( ! function_exists( 'download_url' ) ) {
				include_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$file = download_url( $media_url );
			if ( is_wp_error( $file ) ) {
				wiasano_report_error( $id, "wiasano_add_post: cannot download $media_url. " . $file->get_error_message() );
			} else {
				$title       = wiasano_get( $media, 'title' );
				$description = wiasano_get( $media, 'description' );
				$alt         = wiasano_get( $media, 'alt' );
				$filename    = wiasano_get( $media, 'filename' );

					$file_array = array(
						'name'     => $filename,
						'tmp_name' => $file,
					);

					if ( ! function_exists( 'wp_read_image_metadata' ) ) {
						include_once ABSPATH . 'wp-admin/includes/image.php';
					}
					if ( ! function_exists( 'media_handle_sideload' ) ) {
						include_once ABSPATH . 'wp-admin/includes/media.php';
					}
					$media_id = media_handle_sideload( $file_array, $post_id, $title );

					if ( is_wp_error( $media_id ) ) {
						wiasano_report_error( $id, 'wiasano_add_post: media_handle_sideload failed: ' . $media_id->get_error_message() );
					} else {
						// media alt text.
						if ( ! is_null( $alt ) ) {
							update_post_meta( $media_id, '_wp_attachment_image_alt', $alt );
						}
						// media caption and description.
						if ( ! is_null( $description ) ) {
							wp_update_post(
								array(
									'ID'           => $media_id,
									'post_content' => $description,
									'post_excerpt' => $description,
								),
								true
							);
						}
						// post thumbnail.
						set_post_thumbnail( $post_id, $media_id );
					}
			}
			wp_delete_file( $file );
		}

		// Custom Mappings.
		$mappings = wiasano_field_mapping();
		foreach ( $mappings as $mapping ) {
			$wp_field      = wiasano_get( $mapping, 'wp' );
			$wiasano_field = wiasano_get( $mapping, 'wiasano' );
			if ( str_starts_with( $wiasano_field, 'custom.' ) ) {
				$custom_field_name     = explode( '.', $wiasano_field )[1];
				$custom_fields         = wiasano_get( $props, 'custom', array() );
				$matched_custom_fields = array_filter( $custom_fields, fn( $cf ) => wiasano_get( $cf, 'name' ) === $custom_field_name );
				$val                   = count( $matched_custom_fields ) === 0 ? null : wiasano_get( array_values( $matched_custom_fields )[0], 'value' );
			} else {
				$val = wiasano_get( $props, $wiasano_field );
			}
			$sanitized_val = match ( $wiasano_field ) {
				'categories' => is_null( $val ) ? null : implode( ', ', array_map( fn( $c ) => wiasano_get( $c, 'name', $c ), $val ) ),
				'media.0' => $media_id,
				default => $val
			};
			if ( ! is_null( $sanitized_val ) ) {
				update_post_meta( $post_id, $wp_field, $sanitized_val );
			}
		}

		// Update Categories in wiasano.
		if ( $needs_category_sync ) {
			wiasano_send_categories();
		}
	} catch ( Exception $e ) {
		wiasano_report_error( $id, $e->getMessage() );
	}
}

/**
 * Write the error in the error_log, store it in the DB and report it back to wiasano app.
 *
 * @param int    $todo_id wiasano Todo ID.
 * @param string $message Error message.
 *
 * @return void
 */
function wiasano_report_error( $todo_id, $message ): void {
	error_log( "Error regarding todo $todo_id: $message" );
	wiasano_db_update_todo_error( $todo_id, $message );
	wiasano_client()->update_todo( $todo_id, null, null, null, $message );
}

/**
 * Checks if a wiasano_posts entry exists in DB. If not, a new row is created.
 *
 * @param int $todo_id wiasano Todo ID.
 *
 * @return void
 */
function wiasano_ensure_todo( $todo_id ) {
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM ' . $wpdb->prefix . 'wiasano_posts WHERE todo_id = %d', $todo_id ) ) > 0;
	if ( ! $exists ) {
		wiasano_db_insert_todo( $todo_id );
	}
}

/**
 * Checks if $post_id is set for the given $todo_id
 *
 * @param int $todo_id wiasano Todo ID.
 *
 * @return bool
 */
function wiasano_post_exists_for_todo( $todo_id ) {
	global $wpdb;
	$result = $wpdb->get_var( $wpdb->prepare( 'SELECT count(*) FROM ' . $wpdb->prefix . 'wiasano_posts WHERE todo_id = %d AND post_id IS NOT NULL', $todo_id ) );
	return $result > 0;
}

/**
 * Create a row for $todo_id in the wiasano_posts table. Other columns are null.
 *
 * @param int $todo_id wiasano Todo ID.
 *
 * @return bool
 */
function wiasano_db_insert_todo( $todo_id ) {
	global $wpdb;
	$result = $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . $wpdb->prefix . 'wiasano_posts (todo_id) VALUES (%d)', $todo_id ) );
	return $result > 0; // 1 row affected.
}

/**
 * Update a Todo's $post_id, $title, $published_at in the wiasano_posts table
 *
 * @param int    $todo_id wiasano Todo ID.
 * @param int    $post_id Post ID.
 * @param string $title Title.
 * @param mixed  $published_at Date of Publishing.
 *
 * @return bool
 */
function wiasano_db_update_todo( $todo_id, $post_id, $title, $published_at ) {
	global $wpdb;
	$result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'wiasano_posts SET post_id = %d, title = %s, published_at = %s WHERE todo_id = %d', $post_id, $title, $published_at, $todo_id ) );
	return $result > 0; // 1 row affected.
}

/**
 * Update a Todo's error in the wiasano_posts table
 *
 * @param int    $todo_id wiasano Todo ID.
 * @param string $error error message.
 *
 * @return bool
 */
function wiasano_db_update_todo_error( $todo_id, $error ) {
	global $wpdb;
	$result = $wpdb->query( $wpdb->prepare( 'UPDATE ' . $wpdb->prefix . 'wiasano_posts SET error = %s WHERE todo_id = %d', $error, $todo_id ) );
	return $result > 0; // 1 row affected.
}
