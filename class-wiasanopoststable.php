<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * A WP_List_Table for displaying wiasano posts.
 */
class WiasanoPostsTable extends WP_List_Table {

	/**
	 * Overriden getter for the colums
	 *
	 * @return array columns
	 */
	public function get_columns() {
		return array(
			'title'        => __( 'Title', 'wiasano' ),
			'published_at' => __( 'Published', 'wiasano' ),
			'post_id'      => __( 'Post ID', 'wiasano' ),
			'todo_id'      => __( 'Todo ID', 'wiasano' ),
			'error'        => __( 'Errors', 'wiasano' ),
		);
	}

	/**
	 * Override column defaults
	 *
	 * @param mixed  $item A wiasano post.
	 * @param string $column_name A column name.
	 *
	 * @return mixed|void
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Override prepare items to prepare column headers and items
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$this->_column_headers = array(
			$this->get_columns(),           // columns.
			array(),                        // hidden.
			$this->get_sortable_columns(),  // sortable.
		);
		$this->items           = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'wiasano_posts ORDER BY id DESC LIMIT 25', ARRAY_A );
	}
}
