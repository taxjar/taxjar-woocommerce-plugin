<?php
/**
 * Order Calculation Logger
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use WC_Log_Levels;
use WC_Logger_Interface;
use WC_Order;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Calculation_Logger
 */
class Order_Calculation_Logger extends Tax_Calculation_Logger {

	/**
	 * Order having tax calculated.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Order_Calculation_Logger constructor.
	 *
	 * @param WC_Logger_Interface $logger Logger for writing logs.
	 * @param WC_Order            $order Order having tax calculated.
	 */
	public function __construct( WC_Logger_Interface $logger, $order ) {
		$this->order = $order;
		parent::__construct( $logger );
	}

	/**
	 * Logs failure event.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation details.
	 * @param Throwable              $e Exception or error that indicates cause of failed calculation.
	 */
	public function log_failure( Tax_Calculation_Result $result, Throwable $e ) {
		if ( $this->is_taxjar_calculation_exception( $e ) ) {
			$this->log( WC_Log_Levels::NOTICE, $this->format_failed_calculation_message( $result ) );
		} else {
			$this->log( WC_Log_Levels::ERROR, $this->format_unexpected_exception_message( $result, $e ) );
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
		$message  = 'TaxJar could not calculate tax on order #' . $this->order->get_id() . '. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
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
	 * @param Throwable              $e Exception or error that caused the failure.
	 *
	 * @return string
	 */
	private function format_unexpected_exception_message( Tax_Calculation_Result $result, Throwable $e ): string {
		$message  = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' failed unexpectedly. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_message( $result->get_error_message() );
		$message .= $this->format_error_source( $e );
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
		$message  = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' successful.';
		$message .= $this->format_context( $result->get_context() );
		$message .= $this->format_request_details( $result->get_raw_request() );
		$message .= $this->format_response_details( $result->get_raw_response() );
		$message .= PHP_EOL;
		return $message;
	}
}

