<?php

namespace TaxJar;

use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Tax_Builder extends WP_UnitTestCase {

	public function test_build_line_tax() {
		$line_key = 'test';
		$tax_collectable = 1.1;
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_detail_line_item_stub = $this->createMock( Tax_Detail_Line_Item::class );
		$tax_detail_line_item_stub->method( 'get_tax_collectable' )->willReturn( $tax_collectable );
		$tax_details_stub->method( 'get_line_item' )->with( $line_key )->willReturn( $tax_detail_line_item_stub );

		$tax = Tax_Builder::build_line_tax( $line_key, $tax_details_stub );

		$this->assertEquals( [ 0 => wc_add_number_precision( $tax_collectable ) ], $tax );
	}

	public function test_build_line_tax_from_rate() {
		$rate = .1;
		$taxable_amount = 100;

		$tax = Tax_Builder::build_line_tax_from_rate( $rate, $taxable_amount );

		$this->assertEquals( [ 0 => $rate * $taxable_amount ], $tax );
	}
}
