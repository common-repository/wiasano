<?php

/**
 * REST Client for the wiasano CMS API.
 *
 * Use this to get a list of posts that should be published and report back to wiasano once a post has been published.
 */
class WiasanoClient {

	/**
	 * The host with which the client communicates
	 *
	 * @var string
	 */
	private $host;

	/**
	 * Headers that are required in every request
	 *
	 * @var array[]
	 */
	private $basic_request_data;

	public function __construct( $host, $token ) {
		$this->host               = $host;
		$this->basic_request_data = array(
			'headers' => array(
				'Authorization' => "Bearer $token",
				'Accept'        => 'application/json',
			),
		);
	}

	/**
	 * Get a list of Todos that should be published
	 *
	 * @return WP_Error|array
	 */
	public function list_todos(): null|array {
		$url = $this->build_url( 'todos' );

		$result = wp_remote_get( $url, $this->basic_request_data );

		return $this->handle_response( $result, 'list_todos' );
	}

	/**
	 * Update a Todo in the wiasano backend.
	 *
	 * @param int    $wiasano_todo_id ID of the Todo in wiasano.
	 * @param int    $post_id ID of the post in WordPress.
	 * @param int    $published_at Milliseconds.
	 * @param string $published_url The URL to the published post.
	 * @param string $error (optional) error message.
	 *
	 * @return array|WP_Error
	 */
	public function update_todo( $wiasano_todo_id, $post_id, $published_at, $published_url, $error ) {
		$url  = $this->build_url( "todos/$wiasano_todo_id" );
		$data = array(
			'post_id'       => $post_id,
			'published_at'  => $published_at,
			'published_url' => $published_url,
			'error'         => $error,
		);

		$result = wp_remote_post(
			$url,
			array_merge( $this->basic_request_data, array( 'body' => $data ) )
		);

		return $this->handle_response( $result, 'update_todo' );
	}

	/**
	 * Send Categories to wiasano.
	 *
	 * @param mixed $categories The categories from WordPress.
	 *
	 * @return array|WP_Error
	 */
	public function send_categories( $categories ) {
		$url  = $this->build_url( 'categories' );
		$data = array(
			'categories' => $categories,
		);

		$result = wp_remote_post(
			$url,
			array_merge(
				$this->basic_request_data,
				array(
					'body' => $data,
				)
			)
		);

		return $this->handle_response( $result, 'send_categories' );
	}

	/**
	 * Get a list of custom fields defined on wiasano ToDos
	 *
	 * @return WP_Error|array
	 */
	public function list_custom_fields(): null|array {
		$url = $this->build_url( 'custom-fields' );

		$result = wp_remote_get( $url, $this->basic_request_data );

		return $this->handle_response( $result, 'list_custom_fields' );
	}

	/**
	 * Build a url for the given endpoint.
	 *
	 * @param string $endpoint Endpoint on the wiasano CMS API path.
	 *
	 * @return string
	 */
	private function build_url( $endpoint ) {
		return $this->host . "/cms/v1/de/$endpoint";
	}

	/**
	 * If an API call was successful, then the JSON decoded body is returned.
	 * If error: log the error and return null.
	 *
	 * @param WP_Error|array $result The result from the API call.
	 * @param string         $function_name Pass the name of the calling function so we can log it in case of an error.
	 *
	 * @return array|WP_Error
	 */
	protected function handle_response( WP_Error|array $result, string $function_name ): mixed {
		if ( is_wp_error( $result ) ) {
			$msg = $result->get_error_message();
			error_log( "WiasanoClient->$function_name: $msg" );
			return null;
		}

		$code = $result['response']['code'];
		if ( $code < 200 || $code > 399 ) {
			error_log( "WiasanoClient->$function_name: HTTP Status Code $code" );
			return null;
		}

		return json_decode( wiasano_get( $result, 'body', 'null' ), true );
	}
}
