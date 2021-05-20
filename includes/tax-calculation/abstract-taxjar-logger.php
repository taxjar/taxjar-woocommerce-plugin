<?php

namespace TaxJar;

use Exception, WC_Logger_Interface;
use TaxJar_Settings;

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
		if ( $this->is_logging_enabled() ) {
			$context = array( 'source'  => 'taxjar' );
			$this->logger->log( $level, $message, $context );
		}
	}

	protected function is_logging_enabled() {
		$settings = TaxJar_Settings::get_taxjar_settings();
		return isset( $settings['debug'] ) && 'yes' === $settings['debug'];
	}

}
