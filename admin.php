<?php

/**
 * Callback for Admin init
 *
 * Adds a "wiasano" section to the general settings.
 * There, the user can enter their wiasano token which is needed to interact with the wiasano CMS API
 *
 * @return void
 */
function wiasano_admin_init_callback(): void {
	register_setting( 'wiasano', 'wiasano_options' );
	add_settings_section( 'wiasano_section_general', __( 'General' ), 'wiasano_section_general_callback', 'wiasano' );
	add_settings_field(
		'wiasano_token',
		__( 'wiasano WordPress token', 'wiasano' ),
		'wiasano_token_callback',
		'wiasano',
		'wiasano_section_general',
		array(
			'label_for' => 'wiasano_token',
			'class'     => 'wporg_row',
		)
	);
	add_settings_field(
		'wiasano_post_type',
		__( 'Post Type', 'wiasano' ),
		'wiasano_post_type_callback',
		'wiasano',
		'wiasano_section_general',
		array(
			'label_for' => 'wiasano_post_type',
			'class'     => 'wporg_row',
		)
	);
	add_settings_field(
		'wiasano_field_mapping',
		__( 'Field Mappings', 'wiasano' ),
		'wiasano_field_mapping_callback',
		'wiasano',
		'wiasano_section_general',
		array(
			'label_for' => 'wiasano_field_mapping',
			'class'     => 'wporg_row',
		)
	);
}

/**
 * Sends categories to wiasano backend when the token is saved.
 *
 * @param mixed $option_name The name of the option.
 *
 * @return void
 */
function wiasano_on_option_updated( $option_name ) {
	if ( 'wiasano_options' === $option_name ) {
		wiasano_send_categories();
	}
}

/**
 * Callback for enqueuing admin scripts
 *
 * @param mixed $hook The hook name.
 *
 * @return void
 */
function wiasano_enqueue_admin_scripts_callback( $hook ) {
	// Ensure you only add scripts to your plugin's settings page.
	if ( 'settings_page_wiasano' !== $hook ) {
		return;
	}

	// Set the correct path to your JavaScript file.
	wp_enqueue_script(
		'wiasano-admin-script', // Handle for the script.
		plugins_url( '/js/admin-script.js', __FILE__ ), // Path to the script file, relative to the WordPress root directory.
		array(), // Dependencies, jQuery in this case.
		'1.0.0', // Version number of the script file.
		true  // Whether to place it in the footer. `true` is recommended to avoid blocking page load.
	);

	// Assuming $wp_custom_fields is available here and contains the custom fields.
	wp_localize_script(
		'wiasano-admin-script', // Same handle as the enqueued script.
		'wiasanoState', // The name of the JavaScript object that will contain the data.
		array(
			'wordpressFields' => wiasano_get_wordpress_custom_fields(),
			'wiasanoFields'   => wiasano_get_wiasano_all_fields(),
			'btnRemoveLabel'  => esc_html__( 'Remove', 'wiasano' ),
		)
	);
}

/**
 * Return some text describing the wiasano settings page.
 *
 * @param array $args Arguments passed by WP.
 *
 * @return void
 */
function wiasano_section_general_callback( $args ): void {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>">
		<?php echo esc_html__( 'Enter your wiasano WordPress token here. You can find it in the', 'wiasano' ); ?>
		<a href="https://app.wiasano.com/strategy/overview#wordpress" target="_blank">
			<?php echo esc_html__( 'wiasano strategy settings', 'wiasano' ); ?>
		</a>
	</p>
	<?php
}

/**
 * Render an input field for the wiasano token
 *
 * @param array $args Arguments passed by WP.
 *
 * @return void
 */
function wiasano_token_callback( $args ) {
	$wiasano_token = wiasano_token();
	$id            = $args['label_for'];
	?>
	<input  type="text"
			id="<?php echo esc_attr( $id ); ?>"
			name="wiasano_options[<?php echo esc_attr( $id ); ?>]"
			value="<?php echo esc_attr( $wiasano_token ); ?>"
			style="min-width: 400px">
	<?php
}

/**
 * Render a Select for the available post types
 *
 * @param mixed $args WordPress args.
 *
 * @return void
 */
function wiasano_post_type_callback( $args ) {
	$wiasano_post_type = wiasano_post_type();
	$post_types        = get_post_types( array( 'public' => true ), 'objects' );
	$id                = $args['label_for'];
	echo "<select id='" . esc_attr( $id ) . "' name='wiasano_options[" . esc_attr( $id ) . "]'>";
	foreach ( $post_types as $post_type ) {
		$selected = ( $wiasano_post_type === $post_type->name ) ? 'selected="selected"' : '';
		echo "<option value='" . esc_attr( $post_type->name ) . "' " . esc_attr( $selected ) . '>' . esc_html( $post_type->labels->singular_name ) . '</option>';
	}
	echo '</select>';
}

/**
 * Render the mappings table that allows the user to map wiasano fields to ACF fields.
 *
 * @return void
 */
function wiasano_field_mapping_callback() {
	$wiasano_field_mapping = wiasano_field_mapping();
	$wp_custom_fields      = wiasano_get_wordpress_custom_fields();
	$wiasano_fields        = wiasano_get_wiasano_all_fields();
	?>
		<table id="mapping-table">
		<thead>
			<tr>
				<th style="padding-top:3px;"><?php echo esc_html__( 'ACF Field', 'wiasano' ); ?></th>
				<th style="padding-top:3px;"><?php echo esc_html__( 'wiasano Field', 'wiasano' ); ?></th>
				<th style="padding-top:3px;"><?php echo esc_html__( 'Actions', 'wiasano' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( is_array( $wiasano_field_mapping ) ) : ?>
			<?php foreach ( $wiasano_field_mapping as $index => $mapping ) : ?>
				<tr>
					<td style="padding-left:0px;">
						<select name="wiasano_options[wiasano_field_mapping][<?php echo esc_attr( $index ); ?>][wp]">
						<?php
						foreach ( $wp_custom_fields as $wp_custom_field ) {
							$selected = ( $wp_custom_field === $mapping['wp'] ) ? 'selected="selected"' : '';
							echo "<option value='" . esc_attr( $wp_custom_field ) . "' " . esc_attr( $selected ) . '>' . esc_html( $wp_custom_field ) . '</option>';
						}
						?>
						</select>
					</td>
					<td style="padding-left:0px;">
						<select name="wiasano_options[wiasano_field_mapping][<?php echo esc_attr( $index ); ?>][wiasano]">
						<?php
						foreach ( $wiasano_fields as $name => $label ) {
							$selected = ( $name === $mapping['wiasano'] ) ? 'selected="selected"' : '';
							echo "<option value='" . esc_attr( $name ) . "' " . esc_attr( $selected ) . '>' . esc_html( $label ) . '</option>';
						}
						?>
						</select>
					</td>

					<td style="padding-left:0px;"><button type="button" class="button remove-row"><?php echo esc_html__( 'Remove', 'wiasano' ); ?></button></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="3" style="padding-left:0px;"><button type="button" class="button" id="add-mapping"><?php echo esc_html__( 'Add Mapping', 'wiasano' ); ?></button></td>
			</tr>
		</tfoot>
	</table>
	<?php
}

/**
 * Add "wiasano" submenu to the settings menu.
 *
 * @return void
 */
function wiasano_admin_menu_callback(): void {
	add_submenu_page( 'options-general.php', 'wiasano', 'wiasano', 'manage_options', 'wiasano', 'wiasano_render_admin_menu' );
}

/**
 * Render the settings page.
 * Contains a form to submit user input. Also contains a list of recently published posts by this plugin.
 *
 * @return void
 */
function wiasano_render_admin_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'wiasano_messages' );

	$wiasano_posts_table = new WiasanoPostsTable();
	$wiasano_posts_table->prepare_items();

	?>
	<div class="wrap">
		<h1>wiasano</h1>
		<p>
			<?php echo esc_html__( 'Use the wiasano WordPress Plugin to schedule and publish blog posts you create with', 'wiasano' ); ?>
			<a href="https://app.wiasano.com" target="_blank">wiasano</a>
		</p>

		<form action="options.php" method="post">
	<?php
	settings_fields( 'wiasano' );
	do_settings_sections( 'wiasano' );
	submit_button( __( 'Save settings', 'wiasano' ) );
	?>
		</form>

		<hr>

		<h2>
	<?php echo esc_html( __( 'History' ) ); ?>
		</h2>
		<div>
	<?php $wiasano_posts_table->display(); ?>
		</div>
	</div>
	<?php
}