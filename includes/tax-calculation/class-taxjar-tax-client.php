<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Client {

	private $tax_request_body;
	private $tax_details;

	public function __construct( $tax_request_body ) {
		$this->tax_request_body = $tax_request_body;
	}

	public function get_taxes() {
		$request = new TaxJar_API_Request( 'taxes', $this->tax_request_body->to_json() );
		$response = $request->send_request();
		$this->check_response_for_errors( $response );
		$this->build_tax_details( $response );
		return $this->tax_details;
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

	private function build_tax_details( $response ) {
		$this->tax_details = new TaxJar_Tax_Details( $response );
		$this->tax_details->set_country( $this->tax_request_body->get_to_country() );
		$this->tax_details->set_state( $this->tax_request_body->get_to_state() );
		$this->tax_details->set_zip( $this->tax_request_body->get_to_zip() );
		$this->tax_details->set_city( $this->tax_request_body->get_to_city() );
	}
}