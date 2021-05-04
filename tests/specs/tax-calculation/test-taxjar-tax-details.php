<?php

class Test_TaxJar_Tax_Details extends WP_UnitTestCase {

	public function test_add_and_get_line_items() {
		$tax_response = $this->build_tax_response();
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$line_item_one = $tax_details->get_line_item( '1' );
		$line_item_two = $tax_details->get_line_item( '2' );

		$this->assertEquals( $tax_response['tax']['breakdown']['line_items'][0]['combined_tax_rate'], $line_item_one->get_tax_rate() );
		$this->assertEquals( $tax_response['tax']['breakdown']['line_items'][1]['combined_tax_rate'], $line_item_two->get_tax_rate() );
	}

	public function test_has_nexus() {
		$tax_response = $this->build_tax_response();
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$this->assertTrue( $tax_details->has_nexus() );
	}

	public function test_has_nexus_when_no_nexus() {
		$tax_response = $this->build_tax_response();
		$tax_response['tax']['has_nexus'] = false;
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$this->assertFalse( $tax_details->has_nexus() );
	}

	public function test_is_shipping_taxable() {
		$tax_response = $this->build_tax_response();
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$this->assertTrue( $tax_details->is_shipping_taxable() );
	}

	public function test_is_shipping_taxable_false() {
		$tax_response = $this->build_tax_response();
		$tax_response['tax']['freight_taxable'] = false;
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$this->assertFalse( $tax_details->is_shipping_taxable() );
	}

	public function test_response_with_no_breakdown() {
		$tax_response = $this->build_tax_response();
		unset( $tax_response['tax']['breakdown'] );
		$tax_response['tax']['has_nexus'] = false;
		$tax_details = new TaxJar_Tax_Details( $tax_response );
	}

	public function test_response_with_shipping_breakdown() {
		$expected_shipping_tax_rate = 0.1;
		$tax_response = $this->build_tax_response();
		$tax_response['tax']['breakdown']['shipping'] = array(
			'taxable_amount' => 10.0,
			'tax_collectable' => 1.0,
			'combined_tax_rate' => $expected_shipping_tax_rate
		);
		$tax_details = new TaxJar_Tax_Details( $tax_response );
		$this->assertEquals( $expected_shipping_tax_rate, $tax_details->get_shipping_tax_rate() );
	}

	public function test_response_with_no_shipping_breakdown() {
		$expected_shipping_tax_rate = 0.0;
		$tax_response = $this->build_tax_response();
		$tax_details = new TaxJar_Tax_Details( $tax_response );

		$this->assertEquals( $expected_shipping_tax_rate, $tax_details->get_shipping_tax_rate() );
	}

	public function test_tax_detail_rate() {
		$tax_response = $this->build_tax_response();
		$tax_details = new TaxJar_Tax_Details( $tax_response );

		$this->assertEquals( $tax_response['tax']['rate'], $tax_details->get_rate() );
	}

	public function test_response_with_no_rate() {
		$expected_rate = 0.0;
		$tax_response = $this->build_tax_response();
		unset( $tax_response['tax']['rate'] );
		$tax_details = new TaxJar_Tax_Details( $tax_response );

		$this->assertEquals( $expected_rate, $tax_details->get_rate() );
	}

	private function build_tax_response() {
		return array(
			'tax' => array(
				'amount_to_collect' => 51,
				'breakdown' => array(
					'combined_tax_rate' => 10,
					'line_items' => array(
						array(
							'id' => '1',
							'combined_tax_rate' => 0.1,
							'tax_collectable' => 10,
							'taxable_amount' => 100
						),
						array(
							'id' => '2',
							'combined_tax_rate' => 0.2,
							'tax_collectable' => 40,
							'taxable_amount' => 200
						),
					)
				),
				'freight_taxable' => true,
				'has_nexus' => true,
				'rate' => 0.1
			)
		);
	}
}

