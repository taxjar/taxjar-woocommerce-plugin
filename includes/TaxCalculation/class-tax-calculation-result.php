<?php
/**
 * TaxJar Tax_Calculation_Result
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Calculation_Result
 */
class Tax_Calculation_Result {

	/**
	 * JSON string of request payload.
	 *
	 * @var string
	 */
	private $raw_request = '';

	/**
	 * JSON string of response.
	 *
	 * @var string
	 */
	private $raw_response = '';

	/**
	 * Error message
	 *
	 * @var string
	 */
	private $error_message = '';

	/**
	 * Whether the tax calculation API request and application was successful.
	 *
	 * @var bool
	 */
	private $success = false;

	/**
	 * Context of calculation.
	 *
	 * @var string
	 */
	private $context = '';

	/**
	 * Creates a Tax_Calculation_Result object from a JSON string
	 *
	 * @param string $json_string JSON representation of Tax_Calculation_Result object.
	 *
	 * @return Tax_Calculation_Result
	 */
	public static function from_json_string( string $json_string ): Tax_Calculation_Result {
		$tax_calculation_result = new static();
		$result_data            = json_decode( $json_string, true );
		$tax_calculation_result->set_context( $result_data['context'] ?? '' );
		$tax_calculation_result->set_raw_response( $result_data['raw_response'] ?? '' );
		$tax_calculation_result->set_raw_request( $result_data['raw_request'] ?? '' );
		$tax_calculation_result->set_success( $result_data['success'] ?? false );
		$tax_calculation_result->set_error_message( $result_data['error_message'] ?? '' );
		return $tax_calculation_result;
	}

	/**
	 * Get request JSON.
	 *
	 * @return string
	 */
	public function get_raw_request(): string {
		return $this->raw_request;
	}

	/**
	 * Set raw request.
	 *
	 * @param string $raw_request raw request JSON.
	 */
	public function set_raw_request( string $raw_request ) {
		$this->raw_request = $raw_request;
	}

	/**
	 * Get response JSON.
	 *
	 * @return string
	 */
	public function get_raw_response(): string {
		return $this->raw_response;
	}

	/**
	 * Set raw response.
	 *
	 * @param string $raw_response response JSON.
	 */
	public function set_raw_response( string $raw_response ) {
		$this->raw_response = $raw_response;
	}

	/**
	 * Get calculation error message.
	 *
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->error_message;
	}

	/**
	 * Set calculation error message.
	 *
	 * @param string $error_message Error message.
	 */
	public function set_error_message( string $error_message ) {
		$this->error_message = $error_message;
	}

	/**
	 * Get the calculation status.
	 *
	 * @return bool True when calculation through API was successful, false otherwise.
	 */
	public function get_success(): bool {
		return $this->success;
	}

	/**
	 * Set the calculation status.
	 *
	 * @param bool $success rue when calculation through API was successful, false otherwise.
	 */
	public function set_success( bool $success ) {
		$this->success = $success;
	}

	/**
	 * Get the context of the calculation.
	 *
	 * @return string
	 */
	public function get_context(): string {
		return $this->context;
	}

	/**
	 * Set the context of the calculation.
	 *
	 * @param string $context Calculation context.
	 */
	public function set_context( string $context ) {
		$this->context = $context;
	}

	/**
	 * Get the JSON representation of the object.
	 *
	 * @return false|string
	 */
	public function to_json() {
		return wp_json_encode(
			[
				'context' => $this->get_context(),
				'raw_request' => $this->get_raw_request(),
				'raw_response' => $this->get_raw_response(),
				'error_message' => $this->get_error_message(),
				'success' => $this->get_success()
			]
		);
	}

}
