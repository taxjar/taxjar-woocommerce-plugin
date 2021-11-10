<?php

namespace TaxJar;

use WC_Taxjar_Nexus;
use WP_UnitTestCase;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Order_Tax_Calculation_Validator extends WP_UnitTestCase {

	private $mock_order;
	private $mock_tax_request_body;
	private $mock_nexus;

	public function setUp() {
		$this->mock_order = $this->createMock( WC_Order::class );
		$this->mock_order->method( 'get_subtotal' )->willReturn( 1.0 );
		$this->mock_tax_request_body = $this->createMock( Tax_Request_Body::class );
		$this->mock_nexus            = $this->createMock( \WC_Taxjar_Nexus::class );
	}

	public function tearDown() {
		remove_filter( 'taxjar_should_calculate_order_tax', array( $this, 'filter_interrupt' ) );
	}

	public function test_vat_exempt() {
		$this->mock_order->method( 'get_meta' )->willReturn( 'yes' );
		$order_tax_calculation_validator = $this->build_order_tax_validator();

		$this->expectException( Tax_Calculation_Exception::class );
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	public function test_order_without_nexus() {
		$this->mock_nexus->method( 'has_nexus_check' )->willReturn( false );
		$order_tax_calculation_validator = $this->build_order_tax_validator();

		$this->expectException( Tax_Calculation_Exception::class );
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_valid_order_and_request_body() {
		$this->mock_nexus->method( 'has_nexus_check' )->willReturn( true );
		$this->mock_order->method( 'get_meta' )->willReturn( 'no' );
		$order_tax_calculation_validator = $this->build_order_tax_validator();
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	public function test_zero_subtotal_order() {
		$this->mock_order->method( 'get_subtotal' )->willReturn( 0 );
		$order_tax_calculation_validator = $this->build_order_tax_validator();

		$this->expectException( Tax_Calculation_Exception::class );
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	public function test_filter_interrupt() {
		$this->mock_nexus->method( 'has_nexus_check' )->willReturn( true );
		$this->mock_order->method( 'get_meta' )->willReturn( 'no' );
		add_filter( 'taxjar_should_calculate_order_tax', array( $this, 'filter_interrupt' ) );

		$this->expectException( Tax_Calculation_Exception::class );
		$order_tax_calculation_validator = $this->build_order_tax_validator();
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	public function filter_interrupt( $order ) {
		return false;
	}

	private function build_order_tax_validator() {
		return new Order_Tax_Calculation_Validator( $this->mock_order, $this->mock_nexus );
	}

}
