<?php

namespace TaxJar;

use WC_Tax;
use WP_UnitTestCase;
use TaxJar_Test_Order_Factory;

class Test_Order_Tax_Request_Body_Builder extends WP_UnitTestCase {

	private $order;

	public function setUp(): void {
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		$this->order = TaxJar_Test_Order_Factory::create();
	}

	public function test_get_ship_to_address() {
		$request_body     = $this->create_request_body();
		$expected_address = TaxJar_Test_Order_Factory::$default_options['shipping_address'];

		$this->assertEquals( $expected_address['country'], $request_body->get_to_country() );
		$this->assertEquals( $expected_address['state'], $request_body->get_to_state() );
		$this->assertEquals( $expected_address['city'], $request_body->get_to_city() );
		$this->assertEquals( $expected_address['postcode'], $request_body->get_to_zip() );
		$this->assertEquals( $expected_address['address_1'], $request_body->get_to_street() );
	}

	public function test_get_address_with_no_shipping_address() {
		$this->order->set_shipping_country( '' );
		$request_body = $this->create_request_body();

		$expected_address = TaxJar_Test_Order_Factory::$default_options['billing_address'];
		$this->assertEquals( $expected_address['address_1'], $request_body->get_to_street() );
	}

	public function test_get_line_items() {
		$expected_tax_code      = '20010';
		$order_options_override = array(
			'products' => array(
				0 => array(
					'quantity'  => 2,
					'tax_class' => 'clothing-rate-' . $expected_tax_code,
				),
			),
		);
		$this->order            = TaxJar_Test_Order_Factory::create( $order_options_override );
		$request_body           = $this->create_request_body();
		$line_items             = $request_body->get_line_items();

		$expected_product_data = TaxJar_Test_Order_Factory::$default_options['products'][0];
		$this->assertEquals( $order_options_override['products'][0]['quantity'], $line_items[0]['quantity'] );
		$this->assertEquals( $expected_product_data['price'], $line_items[0]['unit_price'] );
		$this->assertEquals( 0, $line_items[0]['discount'] );
		$this->assertEquals( $expected_tax_code, $line_items[0]['product_tax_code'] );
		$this->assertNotEmpty( $line_items[0]['id'] );
	}

	public function test_get_shipping_amount() {
		$request_body = $this->create_request_body();

		$expected_shipping_total = TaxJar_Test_Order_Factory::$default_options['totals']['shipping_total'];
		$this->assertEquals( $expected_shipping_total, $request_body->get_shipping_amount() );
	}

	public function test_get_customer_id() {
		$request_body = $this->create_request_body();

		$expected_customer_id = TaxJar_Test_Order_Factory::$default_options['customer_id'];
		$this->assertEquals( $expected_customer_id, $request_body->get_customer_id() );
	}

	public function test_get_exemption_type() {
		add_filter( 'taxjar_order_calculation_exemption_type', array( $this, 'add_test_exemption_type' ) );
		$request_body = $this->create_request_body();
		remove_filter( 'taxjar_order_calculation_exemption_type', array( $this, 'add_test_exemption_type' ) );

		$this->assertEquals( 'test_exemption_type', $request_body->get_exemption_type() );
	}

	public function test_get_fee_line_items() {
		$expected_tax_code = '20010';
		$fee_amount        = 10;

		$test_order_factory = new TaxJar_Test_Order_Factory();
		$test_order_factory->create_order_from_options( TaxJar_Test_Order_Factory::$default_options );
		$fee_details              = TaxJar_Test_Order_Factory::$default_fee_details;
		$fee_details['tax_class'] = 'clothing-rate-' . $expected_tax_code;
		$test_order_factory->add_fee( $fee_details );
		$this->order = $test_order_factory->get_order();
		$this->order->calculate_totals( false );

		$request_body = $this->create_request_body();
		$line_items   = $request_body->get_line_items();

		$this->assertEquals( 2, count( $line_items ) );
		$this->assertEquals( 1, $line_items[1]['quantity'] );
		$this->assertEquals( $fee_amount, $line_items[1]['unit_price'] );
		$this->assertEquals( 0, $line_items[1]['discount'] );
		$this->assertEquals( $expected_tax_code, $line_items[1]['product_tax_code'] );
		$this->assertNotEmpty( $line_items[1]['id'] );
	}

	private function create_request_body() {
		$order_tax_request_body_factory = new Order_Tax_Request_Body_Builder( $this->order );
		return $order_tax_request_body_factory->create();
	}

	public function add_test_exemption_type( $exemption_type ) {
		return 'test_exemption_type';

	}
}
