<?php

namespace TaxJar;

use WP_UnitTestCase;

class Test_Tax_Details extends WP_UnitTestCase {

	private $tax_response;

	public function setUp() {
		$this->tax_response = $this->build_tax_response();
	}

	public function test_get_location() {
		$expected_location = array(
			'country' => 'US',
			'state'   => 'UT',
			'city'    => 'Test',
			'zip'     => '11111',
		);

		$tax_details = new Tax_Details( $this->tax_response );
		$tax_details->set_country( $expected_location['country'] );
		$tax_details->set_state( $expected_location['state'] );
		$tax_details->set_city( $expected_location['city'] );
		$tax_details->set_zip( $expected_location['zip'] );

		$location = $tax_details->get_location();
		foreach ( $location as $location_field => $location_value ) {
			$this->assertEquals( $expected_location[ $location_field ], $location_value );
		}
	}

	public function test_add_and_get_line_items() {
		$tax_body      = json_decode( $this->tax_response['body'] );
		$tax_details   = new Tax_Details( $this->tax_response );
		$line_item_one = $tax_details->get_line_item( '1' );
		$line_item_two = $tax_details->get_line_item( '2' );

		$this->assertEquals( $tax_body->tax->breakdown->line_items[0]->combined_tax_rate, $line_item_one->get_tax_rate() );
		$this->assertEquals( $tax_body->tax->breakdown->line_items[1]->combined_tax_rate, $line_item_two->get_tax_rate() );
	}

	public function test_has_nexus() {
		$tax_details = new Tax_Details( $this->tax_response );
		$this->assertTrue( $tax_details->has_nexus() );
	}

	public function test_has_nexus_when_no_nexus() {
		$tax_body                   = json_decode( $this->tax_response['body'] );
		$tax_body->tax->has_nexus   = false;
		$this->tax_response['body'] = wp_json_encode( $tax_body );
		$tax_details                = new Tax_Details( $this->tax_response );
		$this->assertFalse( $tax_details->has_nexus() );
	}

	public function test_is_shipping_taxable() {
		$tax_details = new Tax_Details( $this->tax_response );
		$this->assertTrue( $tax_details->is_shipping_taxable() );
	}

	public function test_is_shipping_taxable_false() {
		$tax_body                       = json_decode( $this->tax_response['body'] );
		$tax_body->tax->freight_taxable = false;
		$this->tax_response['body']     = wp_json_encode( $tax_body );
		$tax_details                    = new Tax_Details( $this->tax_response );
		$this->assertFalse( $tax_details->is_shipping_taxable() );
	}

	public function test_no_exception_thrown_using_response_with_no_breakdown() {
		$tax_body = json_decode( $this->tax_response['body'] );
		unset( $tax_body->tax->breakdown );
		$tax_body->tax->has_nexus   = false;
		$this->tax_response['body'] = wp_json_encode( $tax_body );
		$tax_details                = new Tax_Details( $this->tax_response );
		$this->assertFalse( $tax_details->has_nexus() );
	}

	public function test_response_with_shipping_breakdown() {
		$expected_shipping_tax_rate         = 0.1;
		$tax_body                           = json_decode( $this->tax_response['body'] );
		$tax_body->tax->breakdown->shipping = (object) array(
			'taxable_amount'    => 10.0,
			'tax_collectable'   => 1.0,
			'combined_tax_rate' => $expected_shipping_tax_rate,
		);
		$this->tax_response['body']         = wp_json_encode( $tax_body );
		$tax_details                        = new Tax_Details( $this->tax_response );
		$this->assertEquals( $expected_shipping_tax_rate, $tax_details->get_shipping_tax_rate() );
	}

	public function test_response_with_no_shipping_breakdown() {
		$expected_shipping_tax_rate = 0.0;
		$tax_details                = new Tax_Details( $this->tax_response );
		$this->assertEquals( $expected_shipping_tax_rate, $tax_details->get_shipping_tax_rate() );
	}

	public function test_tax_detail_rate() {
		$tax_body    = json_decode( $this->tax_response['body'] );
		$tax_details = new Tax_Details( $this->tax_response );

		$this->assertEquals( $tax_body->tax->rate, $tax_details->get_rate() );
	}

	public function test_response_with_no_rate() {
		$expected_rate = 0.0;
		$tax_body      = json_decode( $this->tax_response['body'] );
		unset( $tax_body->tax->rate );
		$this->tax_response['body'] = wp_json_encode( $tax_body );
		$tax_details                = new Tax_Details( $this->tax_response );

		$this->assertEquals( $expected_rate, $tax_details->get_rate() );
	}

	private function build_tax_response() {
		return array(
			'body' => wp_json_encode(
				(object) array(
					'tax' => (object) array(
						'amount_to_collect' => 51,
						'breakdown'         => (object) array(
							'combined_tax_rate' => 10,
							'line_items'        => array(
								(object) array(
									'id'                => '1',
									'combined_tax_rate' => 0.1,
									'tax_collectable'   => 10,
									'taxable_amount'    => 100,
								),
								(object) array(
									'id'                => '2',
									'combined_tax_rate' => 0.2,
									'tax_collectable'   => 40,
									'taxable_amount'    => 200,
								),
							),
						),
						'freight_taxable'   => true,
						'has_nexus'         => true,
						'rate'              => 0.1,
					),
				)
			),
		);
	}
}

