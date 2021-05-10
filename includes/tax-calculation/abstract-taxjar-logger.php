<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class TaxJar_Logger {

	protected $logger;

	abstract function log_success( $details );
	abstract function log_failure( $details );

	public function __construct( $logger ) {
		$this->set_logger( $logger );
	}

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
		$this->logger->log( $level, $message );
	}

}
