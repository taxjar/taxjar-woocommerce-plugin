<?php
/**
 * Order Calculation Logger
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use WC_Log_Levels;
use WC_Logger_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Calculation_Logger
 */
class Order_Calculation_Logger extends Logger {

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
	 *
	 * @throws \Exception When $logger is not instance of WC_Logger_Interface.
	 */
	public function __construct( $logger, $order ) {
		$this->order = $order;
		parent::__construct( $logger );
	}

	/**
	 * Logs failure event.
	 *
	 * @param array $details Tax calculation details.
	 */
	public function log_failure( $details ) {
		$level = $this->get_failed_log_level( $details['exception'] );

		if ( $this->is_taxjar_exception( $details['exception'] ) ) {
			$message = $this->format_failed_calculation_message( $details );
		} else {
			$message = $this->format_unexpected_exception_message( $details );
		}

		$this->log( $level, $message );
	}

	/**
	 * Determines log level of failure event.
	 *
	 * @param Exception $exception Exception thrown to cause failure.
	 *
	 * @return string Log level.
	 */
	private function get_failed_log_level( $exception ) {
		if ( $this->is_taxjar_exception( $exception ) ) {
			return WC_Log_Levels::NOTICE;
		} else {
			return WC_Log_Levels::ERROR;
		}
	}

	/**
	 * Determine if exception is a Tax_Calculation_Exception
	 *
	 * @param Exception $exception Exception thrown.
	 *
	 * @return bool
	 */
	private function is_taxjar_exception( $exception ) {
		return $exception instanceof Tax_Calculation_Exception;
	}

	/**
	 * Formats message for calculation failure.
	 *
	 * @param array $details Tax calculation details.
	 *
	 * @return string
	 */
	private function format_failed_calculation_message( $details ) {
		$message  = 'TaxJar could not calculate tax on order #' . $this->order->get_id() . '. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_reason( $details['exception'] );
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		$message .= PHP_EOL;
		return $message;
	}

	/**
	 * Formats failure reason.
	 *
	 * @param Exception $exception Exception thrown.
	 *
	 * @return string
	 */
	private function format_reason( $exception ) {
		return PHP_EOL . 'Reason: ' . $exception->getMessage();
	}

	/**
	 * Formats context of tax calculation.
	 *
	 * @param string $context Context of calculation.
	 *
	 * @return string
	 */
	private function format_context( $context ) {
		return PHP_EOL . 'Context: ' . $context;
	}

	/**
	 * Formats the request body details of tax calculation.
	 *
	 * @param array $details Tax calculation details.
	 *
	 * @return string
	 */
	private function format_request_details( $details ) {
		if ( ! empty( $details['request_body'] ) ) {
			return PHP_EOL . 'Request: ' . $details['request_body']->to_json();
		} else {
			return '';
		}
	}

	/**
	 * Formats response details from TaxJar API.
	 *
	 * @param array $details Tax calculation details.
	 *
	 * @return string
	 */
	private function format_response_details( $details ) {
		if ( ! empty( $details['tax_details'] ) ) {
			$response = $details['tax_details']->get_raw_response();
			$message  = '';
			$message .= $this->format_response_message( $response );
			$message .= $this->format_response_body( $response );
			return $message;
		} else {
			return '';
		}
	}

	/**
	 * Format response message from TaxJar API.
	 *
	 * @param array $response Response from TaxJar API.
	 *
	 * @return string
	 */
	private function format_response_message( $response ) {
		if ( ! empty( $response['response'] ) ) {
			return PHP_EOL . 'Response: ' . wp_json_encode( $response['response'] );
		}
	}

	/**
	 * Formats response body from TaxJar API response.
	 *
	 * @param array $response Response from TaxJar API.
	 *
	 * @return string
	 */
	private function format_response_body( $response ) {
		if ( ! empty( $response['body'] ) ) {
			return PHP_EOL . 'Response Body: ' . $response['body'];
		}
	}

	/**
	 * Formats log message for any unexpected errors during tax calculation.
	 *
	 * @param array $details Tax calculation details.
	 *
	 * @return string
	 */
	private function format_unexpected_exception_message( $details ) {
		$message  = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' failed unexpectedly. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_reason( $details['exception'] );
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		$message .= PHP_EOL;
		return $message;
	}

	/**
	 * Logs successful tax calculation message.
	 *
	 * @param array $details Tax calculation details.
	 */
	public function log_success( $details ) {
		$message = $this->format_success_message( $details );
		$this->log( WC_Log_Levels::INFO, $message );
	}

	/**
	 * Formats successful calculation log message.
	 *
	 * @param array $details Tax calculation details.
	 *
	 * @return string
	 */
	private function format_success_message( $details ) {
		$message  = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' successful.';
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		$message .= PHP_EOL;
		return $message;
	}
}

