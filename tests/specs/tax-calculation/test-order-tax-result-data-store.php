<?php

namespace TaxJar;

use WP_UnitTestCase;

class Test_Order_Tax_Result_Data_Store extends WP_UnitTestCase {

	function test_result_is_stored_on_cart() {
		$test_json = 'test';
		$order = new \WC_Order();
		$result_stub = $this->createMock( Tax_Calculation_Result::class );
		$result_stub->method( 'to_json' )->willReturn( $test_json );
		$cart_result_data_store = new Order_Tax_Calculation_Result_Data_Store( $order );

		$cart_result_data_store->update( $result_stub );

		$this->assertEquals( $test_json, $order->get_meta( '_taxjar_tax_result' ) );
	}
}
