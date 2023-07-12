<?php

namespace TaxJar;

use WP_UnitTestCase;
use TaxJar_Test_Order_Factory;
use Exception;
use WC_Log_Levels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Order_Calculation_Logger extends WP_UnitTestCase {

	private $test_order;
	private $mock_wc_logger;

	public function setUp(): void {
		$this->test_order     = TaxJar_Test_Order_Factory::create();
		$this->mock_wc_logger = $this->createMock( 'WC_Logger' );
	}

	public function test_log_failure_with_taxjar_exemption() {
		$calculation_logger    = new Order_Calculation_Logger( $this->mock_wc_logger, $this->test_order );
		$taxjar_exception_mock = $this->createMock( Tax_Calculation_Exception::class );
		$calculation_result = new Tax_Calculation_Result();
		$calculation_result->set_context( 'context' );

		$this->mock_wc_logger->expects( $this->once() )->method( 'log' )->with( WC_Log_Levels::NOTICE );
		$calculation_logger->log_failure( $calculation_result, $taxjar_exception_mock );
	}

	public function test_log_failure_with_generic_exception() {
		$calculation_logger = new Order_Calculation_Logger( $this->mock_wc_logger, $this->test_order );
		$exception_mock     = $this->createMock( 'Exception' );
		$calculation_result = new Tax_Calculation_Result();
		$calculation_result->set_context( 'context' );

		$this->mock_wc_logger->expects( $this->once() )->method( 'log' )->with( WC_Log_Levels::ERROR );
		$calculation_logger->log_failure( $calculation_result, $exception_mock );
	}

	public function test_log_success_log_level() {
		$calculation_logger = new Order_Calculation_Logger( $this->mock_wc_logger, $this->test_order );
		$calculation_result = new Tax_Calculation_Result();
		$calculation_result->set_context( 'context' );

		$this->mock_wc_logger->expects( $this->once() )->method( 'log' )->with( WC_Log_Levels::INFO );
		$calculation_logger->log_success( $calculation_result );
	}
}
