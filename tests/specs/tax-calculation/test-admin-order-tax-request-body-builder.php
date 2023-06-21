<?php

namespace TaxJar;

use WP_UnitTestCase;
use TaxJar_Test_Order_Factory;

class Test_Admin_Order_Tax_Request_Body_Builder extends WP_UnitTestCase {

	private $order;

	public function setUp(): void {
		$this->order = TaxJar_Test_Order_Factory::create();
	}

	public function tearDown(): void {
		unset( $_POST['country'] );
		unset( $_POST['state'] );
		unset( $_POST['city'] );
		unset( $_POST['postcode'] );
		unset( $_POST['street'] );
		unset( $_POST['customer_user'] );
	}

	public function test_get_ship_to_address() {
		$_POST['country']  = 'US';
		$_POST['state']    = 'UT';
		$_POST['city']     = 'Payson';
		$_POST['postcode'] = '84651';
		$_POST['street']   = '123 Main St';

		$order_tax_request_body_factory = new Admin_Order_Tax_Request_Body_Builder( $this->order );
		$request_body                   = $order_tax_request_body_factory->create();

		$this->assertEquals( $_POST['country'], $request_body->get_to_country() );
		$this->assertEquals( $_POST['state'], $request_body->get_to_state() );
		$this->assertEquals( strtoupper( $_POST['city'] ), $request_body->get_to_city() );
		$this->assertEquals( $_POST['postcode'], $request_body->get_to_zip() );
		$this->assertEquals( strtoupper( $_POST['street'] ), $request_body->get_to_street() );
	}

	public function test_empty_shipping_post_parameters() {
		$order_tax_request_body_factory = new Admin_Order_Tax_Request_Body_Builder( $this->order );
		$request_body                   = $order_tax_request_body_factory->create();

		$this->assertFalse( $request_body->get_to_country() );
		$this->assertFalse( $request_body->get_to_state() );
		$this->assertFalse( $request_body->get_to_city() );
		$this->assertFalse( $request_body->get_to_zip() );
		$this->assertFalse( $request_body->get_to_street() );
	}

	public function test_get_customer_id() {
		$_POST['customer_user']         = '3';
		$order_tax_request_body_factory = new Admin_Order_Tax_Request_Body_Builder( $this->order );
		$request_body                   = $order_tax_request_body_factory->create();

		$this->assertEquals( $_POST['customer_user'], $request_body->get_customer_id() );
	}

	public function test_no_customer_id_post_parameter() {
		$order_tax_request_body_factory = new Admin_Order_Tax_Request_Body_Builder( $this->order );
		$request_body                   = $order_tax_request_body_factory->create();

		$this->assertEquals( 0, $request_body->get_customer_id() );
	}

	public function test_prepare_fields() {
		$_POST['city']                  = 'New+York+City';
		$order_tax_request_body_factory = new Admin_Order_Tax_Request_Body_Builder( $this->order );
		$request_body                   = $order_tax_request_body_factory->create();

		$this->assertEquals( 'NEW YORK CITY', $request_body->get_to_city() );
	}

}
