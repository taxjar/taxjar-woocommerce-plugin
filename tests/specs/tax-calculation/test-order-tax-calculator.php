<?php

namespace TaxJar;
use WP_UnitTestCase;
use WC_Order;
use Exception;

class Test_Order_Tax_Calculator extends WP_UnitTestCase {

	private $mock_logger;
	private $mock_cache;
	private $mock_request_body;
	private $mock_request_body_factory;
	private $mock_tax_client;
	private $mock_applicator;
	private $mock_tax_details;
	private $mock_order;
	private $mock_validator;

	public function setUp() {
		$this->mock_request_body = $this->createMock( Tax_Request_Body::class );
		$this->mock_request_body->method( 'get_to_country' )->willReturn( 'US' );
		$this->mock_request_body->method( 'get_to_state' )->willReturn( 'UT' );

		$this->mock_request_body_factory = $this->createMock( Tax_Request_Body_Builder::class );
		$this->mock_request_body_factory->method( 'create' )->willReturn( $this->mock_request_body );

		$this->mock_tax_details = $this->createMock( Tax_Details::class );
		$this->mock_tax_client = $this->createMock( Tax_Client_Interface::class );
		$this->mock_tax_client->method( 'get_taxes' )->willReturn( $this->mock_tax_details );

		$this->mock_logger = $this->createMock( Logger::class );
		$this->mock_cache = $this->createMock( Cache_Interface::class );
		$this->mock_applicator = $this->createMock( Tax_Applicator_Interface::class );
		$this->mock_order = $this->createMock( WC_Order::class );

		$this->mock_validator = $this->createMock( Tax_Calculation_Validator_Interface::class );
	}

	public function test_invalid_logger() {
		$this->mock_logger = 'invalid logger';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	public function test_invalid_cache() {
		$this->mock_cache = 'invalid cache';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	public function test_invalid_request_body_factory() {
		$this->mock_request_body_factory = 'invalid request body factory';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	public function test_invalid_tax_client() {
		$this->mock_tax_client = 'invalid tax client';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	public function test_invalid_applicator() {
		$this->mock_applicator = 'invalid applicator';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	public function test_invalid_validator() {
		$this->mock_validator = 'invalid validator';
		$this->expectException( Exception::class );
		$calculator = $this->build_calculator();
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_setters_using_valid_objects() {
		$calculator = $this->build_calculator();
	}

	public function test_get_tax_from_cache() {
		$this->mock_cache->method( 'contains_hashed_value' )->willReturn( true );
		$this->mock_cache->expects($this->once())->method( 'read_hashed_value' );
		$this->mock_tax_client->expects($this->never())->method( 'get_taxes' );
		$calculator = $this->build_calculator();
		$calculator->maybe_calculate_and_apply_tax();
	}

	public function test_get_tax_from_client() {
		$this->mock_cache->method( 'contains_hashed_value' )->willReturn( false );
		$this->mock_cache->expects($this->never())->method( 'read_hashed_value' );
		$this->mock_tax_client->expects($this->once())->method( 'get_taxes' );
		$calculator = $this->build_calculator();
		$calculator->maybe_calculate_and_apply_tax();
	}

	private function build_calculator() {
		$calculator = new Tax_Calculator( $this->mock_order );
		$calculator->set_logger( $this->mock_logger );
		$calculator->set_cache( $this->mock_cache );
		$calculator->set_request_body_builder( $this->mock_request_body_factory );
		$calculator->set_tax_client( $this->mock_tax_client );
		$calculator->set_applicator( $this->mock_applicator );
		$calculator->set_validator( $this->mock_validator );
		return $calculator;
	}
}