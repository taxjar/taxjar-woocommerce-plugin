<?php

class Test_Order_Tax_Request_Body_Factory extends WP_UnitTestCase {

	public function setUp() {
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
	}

	public function test_get_ship_to_address() {
		$order = TaxJar_Test_Order_Factory::create();
		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();
		$expected_address = TaxJar_Test_Order_Factory::$default_options['shipping_address'];

		$this->assertEquals( $expected_address['country'], $request_body->get_to_country() );
		$this->assertEquals( $expected_address['state'], $request_body->get_to_state() );
		$this->assertEquals( $expected_address['city'], $request_body->get_to_city() );
		$this->assertEquals( $expected_address['postcode'], $request_body->get_to_zip() );
		$this->assertEquals( $expected_address['address_1'], $request_body->get_to_street() );
	}

	public function test_get_line_items() {
		$expected_tax_code = '20010';
		$order_options_override = array(
			'products' => array(
				0 => array(
					'quantity'     => 2,
					'tax_class'    => 'clothing-rate-' . $expected_tax_code,
				)
			)
		);
		$order = TaxJar_Test_Order_Factory::create( $order_options_override );
		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();
		$line_items = $request_body->get_line_items();

		$expected_product_data = TaxJar_Test_Order_Factory::$default_options['products'][0];

		$this->assertEquals( $order_options_override['products'][0]['quantity'], $line_items[0]['quantity'] );
		$this->assertEquals( $expected_product_data['price'], $line_items[0]['unit_price'] );
		$this->assertEquals( 0, $line_items[0]['discount'] );
		$this->assertEquals( $expected_tax_code, $line_items[0]['product_tax_code'] );
		$this->assertNotEmpty( $line_items[0]['id'] );
	}

	public function test_get_shipping_amount() {
		$order = TaxJar_Test_Order_Factory::create();
		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();

		$expected_shipping_total = TaxJar_Test_Order_Factory::$default_options['totals']['shipping_total'];
		$this->assertEquals( $expected_shipping_total, $request_body->get_shipping_amount() );
	}

	public function test_get_customer_id() {
		$order = TaxJar_Test_Order_Factory::create();
		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();

		$expected_customer_id = TaxJar_Test_Order_Factory::$default_options['customer_id'];
		$this->assertEquals( $expected_customer_id, $request_body->get_customer_id() );
	}

	public function test_get_exemption_type() {
		$order = TaxJar_Test_Order_Factory::create();
		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();

		$expected_customer_id = TaxJar_Test_Order_Factory::$default_options['customer_id'];
		$this->assertEquals( $expected_customer_id, $request_body->get_customer_id() );
	}

	public function test_get_fee_line_items() {
		$expected_tax_code = '20010';
		$fee_amount = 10;

		$test_order_factory = new TaxJar_Test_Order_Factory();
		$test_order_factory->create_order_from_options( TaxJar_Test_Order_Factory::$default_options );
		$fee_details = TaxJar_Test_Order_Factory::$default_fee_details;
		$fee_details['tax_class'] = 'clothing-rate-' . $expected_tax_code;
		$test_order_factory->add_fee( $fee_details );
		$order = $test_order_factory->get_order();
		$order->calculate_totals();

		$order_tax_request_body_factory = new TaxJar_Order_Tax_Request_Body_Factory( $order );
		$request_body = $order_tax_request_body_factory->create();
		$line_items = $request_body->get_line_items();

		$this->assertEquals( 2, count( $line_items ) );

		$this->assertEquals( 1, $line_items[1]['quantity'] );
		$this->assertEquals( $fee_amount, $line_items[1]['unit_price'] );
		$this->assertEquals( 0, $line_items[1]['discount'] );
		$this->assertEquals( $expected_tax_code, $line_items[1]['product_tax_code'] );
		$this->assertNotEmpty( $line_items[1]['id'] );
	}
}