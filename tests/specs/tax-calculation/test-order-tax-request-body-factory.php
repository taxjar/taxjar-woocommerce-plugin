<?php

class Test_Order_Tax_Request_Body_Factory extends WP_UnitTestCase {

	public function setUp() {
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
	}

	public function test_get_ship_to_address() {
		$order = TaxJar_Test_Order_Factory::create();
		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );
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
		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );
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
		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );

		$expected_shipping_total = TaxJar_Test_Order_Factory::$default_options['totals']['shipping_total'];
		$this->assertEquals( $expected_shipping_total, $request_body->get_shipping_amount() );
	}

	public function test_get_customer_id() {
		$order = TaxJar_Test_Order_Factory::create();
		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );

		$expected_customer_id = TaxJar_Test_Order_Factory::$default_options['customer_id'];
		$this->assertEquals( $expected_customer_id, $request_body->get_customer_id() );
	}

	public function test_get_exemption_type() {
		$order = TaxJar_Test_Order_Factory::create();
		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );

		$expected_customer_id = TaxJar_Test_Order_Factory::$default_options['customer_id'];
		$this->assertEquals( $expected_customer_id, $request_body->get_customer_id() );
	}

	public function test_get_fee_line_items() {
		$expected_tax_code = '20010';
		$fee_amount = 10;
		$order = TaxJar_Test_Order_Factory::create();

		$fee = new WC_Order_Item_Fee();
		$fee->set_name( "Test Fee" );
		$fee->set_amount( $fee_amount );
		$fee->set_tax_class( 'clothing-rate-' . $expected_tax_code );
		$fee->set_tax_status( 'taxable' );
		$fee->set_total( $fee_amount );

		$order->add_item( $fee );
		$order->calculate_totals();

		$request_body = TaxJar_Tax_Request_Body_Factory::create_request_body( $order );
		$line_items = $request_body->get_line_items();

		$this->assertEquals( 2, count( $line_items ) );

		$this->assertEquals( 1, $line_items[1]['quantity'] );
		$this->assertEquals( $fee_amount, $line_items[1]['unit_price'] );
		$this->assertEquals( 0, $line_items[1]['discount'] );
		$this->assertEquals( $expected_tax_code, $line_items[1]['product_tax_code'] );
		$this->assertNotEmpty( $line_items[1]['id'] );
	}
}