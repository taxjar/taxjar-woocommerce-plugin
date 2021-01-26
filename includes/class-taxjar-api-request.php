<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_API_Request {

	private $ua;
	private $api_token;
	private $endpoint;
	private $request_type;
	private $request_body;
	private $base_url;

	public static $x_api_version = '2020-08-07';

	public function __construct( $endpoint, $body = null, $type = 'post' ) {
		$this->set_api_token( TaxJar()->settings['api_token'] );
		$this->set_user_agent( TaxJar()->ua );
		$this->set_base_url( TaxJar()->uri );
		$this->set_request_type( $type );
		$this->set_endpoint( $endpoint );
		$this->set_request_body( $body );
	}

	public function _log( $message ) {

		if ( $this->endpoint === 'taxes' ) {
			do_action( 'taxjar_log', $message );

			if ( TaxJar()->debug ) {
				$logger = new WC_Logger();

				if ( is_array( $message ) || is_object( $message ) ) {
					$logger->add( 'taxjar', print_r( $message, true ) );
				} else {
					$logger->add( 'taxjar', $message );
				}
			}
		}
	}

	public function get_request_args() {
		$request_args = array(
			'headers'    => array(
				'Authorization' => 'Token token="' . $this->get_api_token() . '"',
				'Content-Type'  => 'application/json',
				'x-api-version' => self::get_x_api_version()
			),
			'user-agent' => $this->ua
		);

		if ( !empty( $this->get_request_body() ) ) {
			$request_args[ 'body' ] = $this->get_request_body();
		}

		return $request_args;
	}

	public function send_request() {
		switch( $this->get_request_type() ) {
			case 'get':
				return $this->send_get_request();
				break;
			case 'put':
				return $this->send_put_request();
				break;
			default:
				return $this->send_post_request();
		}
	}

	public function send_post_request() {
		$url = $this->get_full_url();
		$this->_log( 'Requesting: ' . $url . ' - ' . $this->get_request_body() );
		return wp_remote_post( $url, $this->get_request_args() );
	}

	public function send_get_request() {
		$url = $this->get_full_url();
		return wp_remote_get( $url, $this->get_request_args() );
	}

	public function send_put_request() {

	}

	public static function get_x_api_version() {
		return apply_filters( 'taxjar_x_api_version', self::$x_api_version );
	}

	public function get_full_url() {
		return $this->base_url . $this->endpoint;
	}

	public function get_request_body() {
		return $this->request_body;
	}

	public function set_request_body( $body ) {
		$this->request_body = $body;
	}

	public function get_api_token() {
		return $this->api_token;
	}

	public function set_api_token( $token ) {
		$this->api_token = $token;
	}

	public function get_user_agent() {
		return $this->user_agent;
	}

	public function set_user_agent( $user_agent ) {
		$this->ua = $user_agent;
	}

	public function get_request_type() {
		return $this->request_type;
	}

	public function set_request_type( $type ) {
		$this->request_type = $type;
	}

	public function get_endpoint() {
		return $this->endpoint;
	}

	public function set_endpoint( $endpoint ) {
		$this->endpoint = $endpoint;
	}

	public function get_base_url() {
		return $this->base_url;
	}

	public function set_base_url( $url ) {
		$this->base_url = $url;
	}




}