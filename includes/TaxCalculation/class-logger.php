<?php
/**
 * TaxJar Logger
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use Exception, WC_Logger_Interface;
use TaxJar_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Abstract Class Logger
 */
abstract class Logger {

	/**
	 * Handles writing of logs.
	 *
	 * @var WC_Logger_Interface $logger WC Logger.
	 */
	protected $logger;

	/**
	 * Logs a message for a success event.
	 *
	 * @param array $details Details of event.
	 */
	abstract public function log_success( $details );

	/**
	 * Logs a message for a failure event.
	 *
	 * @param array $details Details of event.
	 */
	abstract public function log_failure( $details );

	/**
	 * Logger constructor.
	 *
	 * @param WC_Logger_Interface $logger Logger used to write log messages.
	 *
	 * @throws Exception When logger isn't instance of WC_Logger_Interface.
	 */
	public function __construct( $logger ) {
		$this->set_logger( $logger );
	}

	/**
	 * Sets the logger.
	 *
	 * @param WC_Logger_Interface $logger Logger used to write log messages.
	 *
	 * @throws Exception When logger isn't instance of WC_Logger_Interface.
	 */
	final protected function set_logger( $logger ) {
		if ( $logger instanceof WC_Logger_Interface ) {
			$this->logger = $logger;
		} else {
			throw new Exception( 'Logger must implement WC_Logger_Interface' );
		}
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
	protected function is_logging_enabled() {
		return TaxJar_Settings::is_setting_enabled( 'debug' );
	}
}
