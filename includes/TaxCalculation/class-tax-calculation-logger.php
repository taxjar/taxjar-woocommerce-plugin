<?php
/**
 * TaxJar Tax_Calculation_Logger
 *
 * @package TaxJar
 */

namespace TaxJar;

use Exception, WC_Logger_Interface;
use TaxJar_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Class Tax_Calculation_Logger
 */
abstract class Tax_Calculation_Logger {

	/**
	 * Handles writing of logs.
	 *
	 * @var WC_Logger_Interface $logger WC Logger.
	 */
	protected $logger;

	/**
	 * Logs a message for a success event.
	 *
	 * @param Tax_Calculation_Result $result Details of event.
	 */
	abstract public function log_success( Tax_Calculation_Result $result );

	/**
	 * Logs a message for a failure event.
	 *
	 * @param Tax_Calculation_Result $result Details of event.
	 * @param Exception              $e Exception that indicates cause of failed calculation.
	 */
	abstract public function log_failure( Tax_Calculation_Result $result, Exception $e );

	/**
	 * Logger constructor.
	 *
	 * @param WC_Logger_Interface $logger Logger used to write log messages.
	 */
	public function __construct( WC_Logger_Interface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $level One of the following:
	 *     'emergency': System is unusable.
	 *     'alert': Action must be taken immediately.
	 *     'critical': Critical conditions.
	 *     'error': Error conditions.
	 *     'warning': Warning conditions.
	 *     'notice': Normal but significant condition.
	 *     'info': Informational messages.
	 *     'debug': Debug-level messages.
	 * @param string $message Log message.
	 */
	final protected function log( $level, $message ) {
		if ( $this->is_logging_enabled() ) {
			$context = array( 'source' => 'taxjar' );
			$this->logger->log( $level, $message, $context );
		}
	}

	/**
	 * Checks if logging has been enabled in TaxJar settings.
	 *
	 * @return bool
	 */
	protected function is_logging_enabled(): bool {
		return TaxJar_Settings::is_setting_enabled( 'debug' );
	}

	/**
	 * Determine if exception is a Tax_Calculation_Exception
	 *
	 * @param Exception $exception Exception thrown.
	 *
	 * @return bool
	 */
	protected function is_taxjar_calculation_exception( Exception $exception ): bool {
		return $exception instanceof Tax_Calculation_Exception;
	}

	/**
	 * Formats failure reason.
	 *
	 * @param string $error_message Error message.
	 *
	 * @return string
	 */
	protected function format_message( string $error_message ): string {
		return PHP_EOL . 'Message: ' . $error_message;
	}

	/**
	 * Formats context of tax calculation.
	 *
	 * @param string $context Context of calculation.
	 *
	 * @return string
	 */
	protected function format_context( string $context ): string {
		return PHP_EOL . 'Context: ' . $context;
	}

	/**
	 * Formats the request body details of tax calculation.
	 *
	 * @param string $request_json Tax calculation details.
	 *
	 * @return string
	 */
	protected function format_request_details( string $request_json ): string {
		return PHP_EOL . 'Request: ' . $request_json;
	}

	/**
	 * Formats response details from TaxJar API.
	 *
	 * @param string $response_json Tax calculation details.
	 *
	 * @return string
	 */
	protected function format_response_details( string $response_json ): string {
		if ( ! empty( $response_json ) ) {
			$response = json_decode( $response_json, true );
			$message  = $this->format_response_message( $response );
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
	protected function format_response_message( array $response ): string {
		if ( ! empty( $response['response'] ) ) {
			return PHP_EOL . 'Response: ' . wp_json_encode( $response['response'] );
		} else {
			return '';
		}
	}

	/**
	 * Formats response body from TaxJar API response.
	 *
	 * @param array $response Response from TaxJar API.
	 *
	 * @return string
	 */
	protected function format_response_body( array $response ): string {
		if ( ! empty( $response['body'] ) ) {
			return PHP_EOL . 'Response Body: ' . $response['body'];
		} else {
			return '';
		}
	}
}
