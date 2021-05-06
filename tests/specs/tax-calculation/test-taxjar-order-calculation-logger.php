<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_TaxJar_Order_Calculation_Logger extends WP_UnitTestCase {

	public function test_invalid_wc_logger() {
		$invalid_logger = 'not a wc logger object';
		$this->expectException( Exception::class );
		$calculation_logger = new TaxJar_Order_Calculation_Logger( $invalid_logger );
	}

	public function test_log_failure_with_taxjar_exemption() {
		$wc_logger_mock = $this->build_mock_wc_logger();
		$calculation_logger = new TaxJar_Order_Calculation_Logger( $wc_logger_mock );
		$taxjar_exception_mock = $this->build_mock_taxjar_exception();
		$log_details = array(
			'exception' => $taxjar_exception_mock,
			'order_id' => 1,
			'context' => 'context'
		);

		$wc_logger_mock->expects($this->once())->method('log')->with( WC_Log_Levels::NOTICE );

		$calculation_logger->log_failure( $log_details );
	}

	public function test_log_failure_with_generic_exception() {
		$wc_logger_mock = $this->build_mock_wc_logger();
		$calculation_logger = new TaxJar_Order_Calculation_Logger( $wc_logger_mock );
		$exception_mock = $this->createMock( 'Exception' );
		$log_details = array(
			'exception' => $exception_mock,
			'order_id' => 1,
			'context' => 'context'
		);

		$wc_logger_mock->expects($this->once())->method('log')->with( WC_Log_Levels::ERROR );

		$calculation_logger->log_failure( $log_details );
	}

	public function test_log_success_log_level() {
		$wc_logger_mock = $this->build_mock_wc_logger();
		$calculation_logger = new TaxJar_Order_Calculation_Logger( $wc_logger_mock );
		$log_details = array(
			'order_id' => 1,
			'context' => 'context'
		);

		$wc_logger_mock->expects($this->once())->method('log')->with( WC_Log_Levels::INFO );

		$calculation_logger->log_success( $log_details );
	}

	private function build_mock_taxjar_exception() {
		return $this->createMock( 'TaxJar_Tax_Calculation_Exception' );
	}

	private function build_mock_wc_logger() {
		return $this->createMock( 'WC_Logger' );
	}
}