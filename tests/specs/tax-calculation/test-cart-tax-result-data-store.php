<?php

namespace TaxJar;

use WP_UnitTestCase;

class Test_Cart_Tax_Result_Data_Store extends WP_UnitTestCase {

	public function tearDown(): void {
		WC()->cart->empty_cart();
		unset( WC()->cart->tax_calculation_results );
		parent::tearDown();
	}

	function test_result_is_stored_on_cart() {
		$test_json = 'test';
		$cart = WC()->cart;
		$result_stub = $this->createMock( Tax_Calculation_Result::class );
		$result_stub->method( 'to_json' )->willReturn( $test_json );
		$cart_result_data_store = new Cart_Tax_Calculation_Result_Data_Store( $cart );

		$cart_result_data_store->update( $result_stub );

		$this->assertEquals( $test_json, $cart->tax_calculation_results );
	}
}
