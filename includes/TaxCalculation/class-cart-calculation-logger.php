<?php
/**
 * Cart Calculation Logger
 *
 * @package TaxJar
 */

namespace TaxJar;

use WC_Log_Levels;
use WC_Logger_Interface;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cart_Calculation_Logger
 */
class Cart_Calculation_Logger extends Tax_Calculation_Logger {

	/**
	 * Logs failure event.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 * @param Exception              $e Exception that indicates cause of failed calculation.
	 */
	public function log_failure( Tax_Calculation_Result $result, Exception $e ) {
		if ( $this->is_taxjar_calculation_exception( $e ) ) {
			$this->log( WC_Log_Levels::NOTICE, $this->format_failed_calculation_message( $result ) );
		} else {
			$this->log( WC_Log_Levels::ERROR, $this->format_unexpected_exception_message( $result ) );
		}
	}

	/**
	 * Formats message for calculation failure.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 *
	 * @return string
	 */
	private function format_failed_calculation_message( Tax_Calculation_Result $result ): string {
		$message  = 'TaxJar could not calculate tax on cart. Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_message( $result->get_error_message() );
		$message .= $this->format_context( $result->get_context() );
		$message .= $this->format_request_details( $result->get_raw_request() );
		$message .= $this->format_response_details( $result->get_raw_response() );
		$message .= PHP_EOL;
		return $message;
	}

	/**
	 * Formats log message for any unexpected errors during tax calculation.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 *
	 * @return string
	 */
	private function format_unexpected_exception_message( Tax_Calculation_Result $result ): string {
		$message  = 'TaxJar tax calculation on cart failed unexpectedly. Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_message( $result->get_error_message() );
		$message .= $this->format_context( $result->get_context() );
		$message .= $this->format_request_details( $result->get_raw_request() );
		$message .= $this->format_response_details( $result->get_raw_response() );
		$message .= PHP_EOL;
		return $message;
	}

	/**
	 * Logs successful tax calculation message.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 */
	public function log_success( Tax_Calculation_Result $result ) {
		$message = $this->format_success_message( $result );
		$this->log( WC_Log_Levels::INFO, $message );
	}

	/**
	 * Formats successful calculation log message.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 *
	 * @return string
	 */
	private function format_success_message( Tax_Calculation_Result $result ): string {
		$message  = 'TaxJar tax calculation on cart successful.';
		$message .= $this->format_context( $result->get_context() );
		$message .= $this->format_request_details( $result->get_raw_request() );
		$message .= $this->format_response_details( $result->get_raw_response() );
		$message .= PHP_EOL;
		return $message;
	}
}

