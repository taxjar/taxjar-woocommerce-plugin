<?php

class Test_Tax_Detail_Line_Item extends WP_UnitTestCase {

	public function test_get_tax_rate() {
		$expected_tax_rate = 10;
		$tax_line_item = new TaxJar_Tax_Detail_Line_Item( array(
			'id' => '1',
			'combined_tax_rate' => $expected_tax_rate,
			'tax_collectable' => 100,
			'taxable_amount' => 1000
		) );

		$this->assertEquals( $expected_tax_rate, $tax_line_item->get_tax_rate() );
	}

	public function test_get_tax_rate_with_zero_tax_collectable() {
		$expected_tax_rate = 0;
		$tax_line_item = new TaxJar_Tax_Detail_Line_Item( array(
			'id' => '1',
			'combined_tax_rate' => 10,
			'tax_collectable' => 0.0,
			'taxable_amount' => 1000
		) );

		$this->assertEquals( $expected_tax_rate, $tax_line_item->get_tax_rate() );
	}
}