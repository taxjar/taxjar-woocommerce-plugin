<?php

namespace TaxJar;

use TaxJar\Tests\Framework\Cart_Builder;
use WC_Taxjar_Nexus;
use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Cart_Tax_Calculation_Validator extends WP_UnitTestCase {

	private $nexus_stub;
	private $tax_request_body_stub;

	public function setUp(): void {
		$this->nexus_stub = $this->createMock( WC_Taxjar_Nexus::class );
		$this->tax_request_body_stub = $this->createMock( Tax_Request_Body::class );
	}

	public function tearDown(): void {
		remove_all_filters( 'taxjar_should_calculate_cart_tax' );
	}

	public function test_valid_cart_throws_no_exceptions() {
		$cart = Cart_Builder::a_cart()->build();
		$this->nexus_stub->method( 'has_nexus_check' )->willReturn( true );
		$cart_tax_calculation_validator = new Cart_Tax_Calculation_Validator( $cart, $this->nexus_stub );
		$exception_thrown = false;

		try {
			$cart_tax_calculation_validator->validate( $this->tax_request_body_stub );
		} catch ( Tax_Calculation_Exception $e ) {
			$exception_thrown = true;
		}

		$this->assertFalse( $exception_thrown );
	}

	public function test_filter_interrupt_throws_exception() {
		$cart = Cart_Builder::a_cart()->build();
		$this->nexus_stub->method( 'has_nexus_check' )->willReturn( true );
		$cart_tax_calculation_validator = new Cart_Tax_Calculation_Validator( $cart, $this->nexus_stub );
		add_filter( 'taxjar_should_calculate_cart_tax', function( $order ) {
			return false;
		});

		$this->expectException( Tax_Calculation_Exception::class );
		$cart_tax_calculation_validator->validate( $this->tax_request_body_stub );
	}

	public function test_no_nexus_throws_exception() {
		$cart = Cart_Builder::a_cart()->build();
		$this->nexus_stub->method( 'has_nexus_check' )->willReturn( false );
		$cart_tax_calculation_validator = new Cart_Tax_Calculation_Validator( $cart, $this->nexus_stub );

		$this->expectException( Tax_Calculation_Exception::class );
		$cart_tax_calculation_validator->validate( $this->tax_request_body_stub );
	}

	public function test_vat_exempt_cart_throws_exception() {
		$cart = Cart_Builder::a_cart()->with_vat_exemption()->build();
		$this->nexus_stub->method( 'has_nexus_check' )->willReturn( true );
		$cart_tax_calculation_validator = new Cart_Tax_Calculation_Validator( $cart, $this->nexus_stub );

		$this->expectException( Tax_Calculation_Exception::class );
		$cart_tax_calculation_validator->validate( $this->tax_request_body_stub );
	}

	public function test_zero_cart_total_throws_exception() {
		$cart = Cart_Builder::a_cart()->with_shipping_total( 0 )->build();
		$this->nexus_stub->method( 'has_nexus_check' )->willReturn( true );
		$cart_tax_calculation_validator = new Cart_Tax_Calculation_Validator( $cart, $this->nexus_stub );

		$this->expectException( Tax_Calculation_Exception::class );
		$cart_tax_calculation_validator->validate( $this->tax_request_body_stub );
	}

}
