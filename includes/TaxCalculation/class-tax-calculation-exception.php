<?php
/**
 * Tax Calculation Exception
 *
 * Thrown for all expected exceptions during tax calculation process.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Calculation_Exception
 */
class Tax_Calculation_Exception extends Exception {

	/**
	 * Machine readable error code.
	 *
	 * @var string sanitized error code
	 */
	protected $error_code;

	/**
	 * Exception constructor
	 *
	 * @param string $error_code machine readable error code.
	 * @param string $error_message user friendly error message.
	 * @param int    $http_status_code HTTP status code to respond with.
	 */
	public function __construct( $error_code, $error_message, $http_status_code = 0 ) {
		$this->error_code = $error_code;
		parent::__construct( $error_message, $http_status_code );
	}

	/**
	 * Returns the error code
	 *
	 * @return string
	 */
	public function getErrorCode() {
		return $this->error_code;
	}
}
