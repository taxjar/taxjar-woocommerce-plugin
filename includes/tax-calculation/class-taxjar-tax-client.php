<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Client {

	private $tax_request_body;

	public function __construct( $tax_request_body ) {
		$this->tax_request_body = $tax_request_body;
	}

	public function get_taxes() {
		$request = new TaxJar_API_Request( 'taxes', $this->tax_request_body->to_json() );
		$response = $request->send_request();
		$this->check_response_for_errors( $response );
		return new TaxJar_Tax_Details( $response );
	}

	private function check_response_for_errors( $response ) {
		$this->check_for_wp_error( $response );
		$this->check_for_ok_status( $response );
	}

	private function check_for_wp_error( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'Tax calculation request failed. Details: ', 'taxjar' ) . $response->get_error_message() );
		}
	}

	private function check_for_ok_status( $response ) {
		if ( 200 !== $response['response']['code'] ) {
			throw new Exception( __( 'Tax calculation request failed with code: ', 'taxjar' ) . $response['response']['code'] );
		}
	}
}