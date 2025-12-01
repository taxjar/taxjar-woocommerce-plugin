<?php

namespace TaxJar;

use PHPUnit\Framework\TestCase;
use TaxJar\WooCommerce\TaxCalculation\Block_Flag;

class Test_Block_Flag extends TestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		Block_Flag::clear_flag();
		parent::tearDown();
	}

	public function test_cart_route() {
		$this->assertFalse( Block_Flag::was_block_initialized() );
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/cart', null );
		$this->assertTrue( Block_Flag::was_block_initialized() );
	}

	public function test_versioned_cart_route() {
		$this->assertFalse( Block_Flag::was_block_initialized() );
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/v1/cart/update-customer', null );
		$this->assertTrue( Block_Flag::was_block_initialized() );
	}

	public function test_checkout_route() {
		$this->assertFalse( Block_Flag::was_block_initialized() );
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/checkout', null );
		$this->assertTrue( Block_Flag::was_block_initialized() );
	}

	public function test_versioned_checkout_route() {
		$this->assertFalse( Block_Flag::was_block_initialized() );
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/v2/checkout', null );
		$this->assertTrue( Block_Flag::was_block_initialized() );
	}

	public function test_incorrect_route() {
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/invalid', null );
		$this->assertFalse( Block_Flag::was_block_initialized() );
	}

	public function test_flag_reset() {
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/checkout', null );
		apply_filters( 'rest_dispatch_request', null, null, '/wc/store/invalid', null );
		$this->assertFalse( Block_Flag::was_block_initialized() );
	}
}
