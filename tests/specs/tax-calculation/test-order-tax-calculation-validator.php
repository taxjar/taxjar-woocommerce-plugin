<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Order_Tax_Calculation_Validator extends WP_UnitTestCase {

	private $mock_order;
	private $mock_tax_request_body;
	private $mock_nexus;

	public function setUp() {
		$this->mock_order = $this->createMock( WC_Order::class );
		$this->mock_tax_request_body = $this->createMock( TaxJar_Tax_Request_Body::class );
		$this->mock_nexus = $this->createMock( WC_Taxjar_Nexus::class );
	}

	public function test_vat_exempt() {
		$this->mock_order->method( 'get_meta' )->willReturn( 'yes' );
		$order_tax_calculation_validator = $this->build_order_tax_validator();

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );
		$order_tax_calculation_validator->validate( $this->mock_tax_request_body );
	}

	public function test_order_without_nexus() {
		$this->mock_nexus->method( 'has_nexus_check' )->willReturn( false );
		$order_tax_calculation_validator = $this->build_order_tax_validator();

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );
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

	private function build_order_tax_validator() {
		return new TaxJar_Order_Tax_Calculation_Validator( $this->mock_order, $this->mock_nexus  );
	}

}