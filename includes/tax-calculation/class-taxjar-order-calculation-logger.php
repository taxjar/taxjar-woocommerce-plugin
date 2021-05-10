<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Calculation_Logger extends TaxJar_Logger {

	private $order;

	public function __construct( $logger, $order ) {
		$this->order = $order;
		parent::__construct( $logger );
	}

	public function log_failure( $details ) {
		$level = $this->get_failed_log_level( $details['exception'] );

		if ( $this->is_taxjar_exception( $details['exception'] ) ) {
			$message = $this->format_failed_calculation_message( $details );
		} else {
			$message = $this->format_unexpected_exception_message( $details );
		}

		$this->log( $level, $message );
	}

	private function get_failed_log_level( $exception ) {
		if ( $this->is_taxjar_exception( $exception ) ) {
			return WC_Log_Levels::NOTICE;
		} else {
			return WC_Log_Levels::ERROR;
		}
	}

	private function is_taxjar_exception( $exception ) {
		return $exception instanceof TaxJar_Tax_Calculation_Exception;
	}

	private function format_failed_calculation_message( $details ) {
		$message = 'TaxJar could not calculate tax on order #' . $this->order->get_id() . '. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_reason( $details['exception'] );
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		return $message;
	}

	private function format_reason( $exception ) {
		return PHP_EOL . 'Reason: ' . $exception->getMessage();
	}

	private function format_context( $context ) {
		return PHP_EOL . 'Context: ' . $context;
	}

	private function format_request_details( $details ) {
		if ( ! empty( $details['request_body'] ) ) {
			return PHP_EOL . 'Request: ' . $details['request_body']->to_json();
		} else {
			return '';
		}
	}

	private function format_response_details( $details ) {
		if ( ! empty( $details['tax_details'] ) ) {
			return PHP_EOL . 'Response: ' . $details['tax_details']->get_json_response();
		} else {
			return '';
		}
	}

	private function format_unexpected_exception_message( $details ) {
		$message = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' failed unexpectedly. ';
		$message .= 'Reverting to default WooCommerce tax calculation.';
		$message .= $this->format_reason( $details['exception'] );
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		return $message;
	}

	public function log_success( $details ) {
		$message = $this->format_success_message( $details );
		$this->log( WC_Log_Levels::INFO, $message );
	}

	private function format_success_message( $details ) {
		$message = 'TaxJar tax calculation on order #' . $this->order->get_id() . ' successful.';
		$message .= $this->format_context( $details['context'] );
		$message .= $this->format_request_details( $details );
		$message .= $this->format_response_details( $details );
		return $message;
	}

}




