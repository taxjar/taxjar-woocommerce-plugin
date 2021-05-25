<?php
/**
 * Tax Client
 *
 * Gets tax details for a tax request body.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_API_Request;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Client
 */
class Tax_Client implements Tax_Client_Interface {

	/**
	 * Stores tax request body used to get tax details.
	 *
	 * @var Tax_Request_Body
	 */
	private $tax_request_body;

	/**
	 * Stores tax details created from tax request body.
	 *
	 * @var Tax_Details
	 */
	private $tax_details;

	/**
	 * Gets tax rates from TaxJar API and builds tax details.
	 *
	 * @param Tax_Request_Body $tax_request_body Contains all information necessary to get tax rates.
	 *
	 * @throws Exception When error occurs in request to TaxJar API.
	 *
	 * @return Tax_Details
	 */
	public function get_taxes( $tax_request_body ) {
		$this->tax_request_body = $tax_request_body;
		$request                = new TaxJar_API_Request( 'taxes', $this->tax_request_body->to_json() );
		$response               = $request->send_request();
		$this->check_response_for_errors( $response );
		$this->build_tax_details( $response );
		return $this->tax_details;
	}

	/**
	 * Checks if request to TaxJar API had any errors.
	 *
	 * @param WP_Error|array $response Response from TaxJar API.
	 *
	 * @throws Exception When error occurs in request to TaxJar API.
	 */
	private function check_response_for_errors( $response ) {
		$this->check_for_wp_error( $response );
		$this->check_for_ok_status( $response );
	}

	/**
	 * Checks if response is WP_Error.
	 *
	 * @param WP_Error|array $response Response from TaxJar API.
	 *
	 * @throws Exception When response is WP_Error.
	 */
	private function check_for_wp_error( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'Tax calculation request failed. Details: ', 'taxjar' ) . $response->get_error_message() );
		}
	}

	/**
	 * Checks if TaxJar API returned expected HTTP Response Status Code.
	 *
	 * @param array $response Response from TaxJar API.
	 *
	 * @throws Exception When response status code is not 200.
	 */
	private function check_for_ok_status( $response ) {
		if ( 200 !== $response['response']['code'] ) {
			throw new Exception( __( 'Tax calculation request failed with code: ', 'taxjar' ) . $response['response']['code'] );
		}
	}

	/**
	 * Builds tax details from TaxJar API response.
	 *
	 * @param array $response Response from TaxJar API.
	 */
	private function build_tax_details( $response ) {
		$this->tax_details = new Tax_Details( $response );
	}
}
