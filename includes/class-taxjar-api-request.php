<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TaxJar_API_Request
 */
class TaxJar_API_Request {

	private $ua;
	private $api_token;
	private $endpoint;
	private $request_type;
	private $request_body;
	private $content_type;

	public static $x_api_version = '2020-08-07';
	public static $base_url = 'https://api.taxjar.com/v2/';

	/**
	 * TaxJar_API_Request constructor.
	 *
	 * @param $endpoint - endpoint of TaxJar API
	 * @param null $body - request body
	 * @param string $type - type of supported requests: post, get, delete, put
	 * @param string $content_type - content type header for request
	 */
	public function __construct( $endpoint, $body = null, $type = 'post', $content_type = 'application/json' ) {
		$this->set_api_token( TaxJar()->settings['api_token'] );
		$this->set_user_agent( self::create_ua_header() );
		$this->set_request_type( $type );
		$this->set_endpoint( $endpoint );
		$this->set_request_body( $body );
		$this->set_content_type( $content_type );
	}

	/**
	 * Generates the request args to use with wp_remote_request
	 *
	 * @return array
	 */
	public function get_request_args() {
		$request_args = array(
			'headers'    => array(
				'Authorization' => 'Token token="' . $this->get_api_token() . '"',
				'Content-Type'  => $this->get_content_type(),
				'x-api-version' => $this->get_x_api_version()
			),
			'user-agent' => $this->get_user_agent()
		);

		if ( $this->get_request_type() === 'put' ) {
			$request_args[ 'method' ] = 'PUT';
		}

		if ( $this->get_request_type() === 'delete' ) {
			$request_args[ 'method' ] = 'DELETE';
		}

		if ( !empty( $this->get_request_body() ) ) {
			$request_args[ 'body' ] = $this->get_request_body();
		}

		return $request_args;
	}

	/**
	 * Sends request to TaxJar API
	 *
	 * @return array|WP_Error
	 */
	public function send_request() {
		switch( $this->get_request_type() ) {
			case 'get':
				return $this->send_get_request();
				break;
			case 'put':
				return $this->send_put_request();
				break;
			case 'delete':
				return $this->send_delete_request();
				break;
			default:
				return $this->send_post_request();
		}
	}

	/**
	 * Sends post request to TaxJar API
	 *
	 * @return array|WP_Error
	 */
	public function send_post_request() {
		$url = $this->get_full_url();
		return wp_remote_post( $url, $this->get_request_args() );
	}

	/**
	 * Sends get request to TaxJar API
	 *
	 * @return array|WP_Error
	 */
	public function send_get_request() {
		$url = $this->get_full_url();
		return wp_remote_get( $url, $this->get_request_args() );
	}

	/**
	 * Sends put request to TaxJar API
	 *
	 * @return array|WP_Error
	 */
	public function send_put_request() {
		$url = $this->get_full_url();
		return wp_remote_request( $url, $this->get_request_args() );
	}

	/**
	 * Sends delete request to TaxJar API
	 *
	 * @return array|WP_Error
	 */
	public function send_delete_request() {
		$url = $this->get_full_url();
		return wp_remote_request( $url, $this->get_request_args() );
	}

	/**
	 * Gets the x-api-version header to use in requests
	 *
	 * @return mixed|void
	 */
	public function get_x_api_version() {

		/**
		 * Filter x-api-version header
		 *
		 * @param string $x_api_version x-api-version header
		 * @param TaxJar_API_Request    request data
		 */
		return apply_filters( 'taxjar_x_api_version', self::$x_api_version, $this );
	}

	/**
	 * Create user agent header
	 *
	 * @return string - user agent header
	 */
	static function create_ua_header() {
		$curl_version = '';
		if ( function_exists( 'curl_version' ) ) {
			$curl_version = curl_version();
			$curl_version = $curl_version['version'] . '; ' . $curl_version['ssl_version'];
		}

		$php_version       = phpversion();
		$taxjar_version    = WC_Taxjar::$version;
		$woo_version       = WC()->version;
		$wordpress_version = get_bloginfo( 'version' );
		$site_url          = get_bloginfo( 'url' );
		$user_agent        = "TaxJar/WooCommerce (PHP $php_version; cURL $curl_version; WordPress $wordpress_version; WooCommerce $woo_version) WC_Taxjar/$taxjar_version $site_url";
		return $user_agent;
	}

	/**
	 * Gets full url to use in requests
	 *
	 * @return string
	 */
	public function get_full_url() {
		return self::$base_url . $this->endpoint;
	}

	/**
	 * @return mixed
	 */
	public function get_request_body() {
		return $this->request_body;
	}

	/**
	 * @param $body
	 */
	public function set_request_body( $body ) {
		$this->request_body = $body;
	}

	/**
	 * @return mixed
	 */
	public function get_api_token() {
		return $this->api_token;
	}

	/**
	 * @param $token
	 */
	public function set_api_token( $token ) {
		$this->api_token = $token;
	}

	/**
	 * @return mixed
	 */
	public function get_user_agent() {
		return $this->ua;
	}

	/**
	 * @param $user_agent
	 */
	public function set_user_agent( $user_agent ) {
		$this->ua = $user_agent;
	}

	/**
	 * @return mixed
	 */
	public function get_request_type() {
		return $this->request_type;
	}

	/**
	 * @param string $type - valid values: post, get, put, delete
	 */
	public function set_request_type( $type ) {
		$this->request_type = $type;
	}

	/**
	 * @return mixed
	 */
	public function get_endpoint() {
		return $this->endpoint;
	}

	/**
	 * @param $endpoint
	 */
	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
	}

	/**
	 * @return mixed
	 */
	public function get_content_type() {
		return $this->content_type;
	}

	/**
	 * @param $content_type
	 */
	public function set_content_type( $content_type ) {
		$this->content_type = $content_type;
	}

}