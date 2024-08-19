<?php
class TJ_WC_Test_Customer_Sync extends WP_UnitTestCase {

	public $tj;

	function setUp(): void {
		parent::setUp();

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = TaxJar();
	}

	function tearDown(): void {
		parent::tearDown();
		WC_Taxjar_Record_Queue::clear_queue();
	}

	function test_get_exemption_type() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->set_password('password');
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'wholesale' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'wholesale', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'government' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'government', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'other' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'other', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'non_exempt' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );

		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'invalid_type' );
		$exemption_type = $record->get_exemption_type();
		$this->assertEquals( 'non_exempt', $exemption_type );
	}

	function test_get_exempt_regions() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->set_password('password');
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$exempt_regions = $record->get_exempt_regions();
		$this->assertEquals( array(), $exempt_regions );

		$exempt_regions_string = 'AL,AK';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			),
			array(
				'country' => 'US',
				'state' => 'AK'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		// test invalid state string
		$exempt_regions_string = 'AL,XX';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		$exempt_regions_string = 'AL';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );

		$exempt_regions_string = 'AL,,AK';
		update_user_meta( $customer->get_id(), 'tax_exempt_regions', $exempt_regions_string );
		$exempt_regions = $record->get_exempt_regions();
		$expected = array(
			array(
				'country' => 'US',
				'state' => 'AL'
			),
			array(
				'country' => 'US',
				'state' => 'AK'
			)
		);
		$this->assertEquals( $expected, $exempt_regions );
	}

	function test_customer_sync_validation() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->set_password('password');
		$customer->save();

		// test no object loaded
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$should_sync = $record->should_sync();
		$this->assertFalse( $should_sync );

		// test no name, falls back to username and should still sync
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertTrue( $should_sync );

		$customer->set_billing_first_name( 'Test' );
		$customer->set_billing_last_name( 'Test' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertTrue( $should_sync );
	}

	function test_get_customer_data() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$data = $record->get_data();

		$expected_data = array(
			'customer_id' => $customer->get_id(),
			'exemption_type' => 'wholesale',
			'name' => 'First Last',
			'exempt_regions' => array(
				array(
					'country' => 'US',
					'state' => 'CO'
				),
				array(
					'country' => 'US',
					'state' => 'UT'
				)
			),
			'country' => 'US',
			'state' => 'CO',
			'zip' => '80111',
			'city' => 'Greenwood Village',
			'street' => '123 Test St'
		);

		$this->assertEquals( $expected_data[ 'customer_id' ], $data[ 'customer_id' ] );
		$this->assertEquals( $expected_data[ 'exemption_type' ], $data[ 'exemption_type' ] );
		$this->assertEquals( $expected_data[ 'name' ], $data[ 'name' ] );
		$this->assertEquals( $expected_data[ 'exempt_regions' ], $data[ 'exempt_regions' ] );
		$this->assertEquals( $expected_data[ 'country' ], $data[ 'country' ] );
		$this->assertEquals( $expected_data[ 'state' ], $data[ 'state' ] );
		$this->assertEquals( $expected_data[ 'zip' ], $data[ 'zip' ] );
		$this->assertEquals( $expected_data[ 'city' ], $data[ 'city' ] );
		$this->assertEquals( $expected_data[ 'street' ], $data[ 'street' ] );
	}

	function test_get_name_fallback() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'name_fallback@test.com' );
		$customer->set_password('password');
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$name = $record->get_customer_name();
		$this->assertEquals( 'name_fallback' , $name );

		$customer->set_first_name( 'First' );
		$customer->set_last_name( 'Last' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$name = $record->get_customer_name();
		$this->assertEquals( 'First Last' , $name );

		$customer->set_billing_first_name( 'Bfirst' );
		$customer->set_billing_last_name( 'Blast' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$name = $record->get_customer_name();
		$this->assertEquals( 'Bfirst Blast' , $name );

		$customer->set_shipping_first_name( 'Sfirst' );
		$customer->set_shipping_last_name( 'Slast' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$name = $record->get_customer_name();
		$this->assertEquals( 'Sfirst Slast' , $name );

		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );
	}

	function test_customer_api_requests() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		// test create new customer in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->create_in_taxjar();
		$this->assertEquals( 201, $response['response']['code'] );

		// test update existing customer in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->update_in_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );

		// test get customer from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );
		$body = json_decode( $response[ 'body' ] );
		$this->assertEquals( 'wholesale', $body->customer->exemption_type );
		$this->assertEquals( 'First Last', $body->customer->name );
		$this->assertEquals( 'US', $body->customer->country );
		$this->assertEquals( 'CO', $body->customer->state );
		$this->assertEquals( '80111', $body->customer->zip );
		$this->assertEquals( 'Greenwood Village', $body->customer->city );
		$this->assertEquals( '123 Test St', $body->customer->street );

		$valid_states = array( 'UT', 'CO' );
		$this->assertContains( $body->customer->exempt_regions[ 0 ]->state, $valid_states );
		$this->assertContains( $body->customer->exempt_regions[ 1 ]->state, $valid_states );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 0 ]->country );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 1 ]->country );

		// test delete customer from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->delete_in_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );

		// test get customer after deletion from TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 404, $response['response']['code'] );
	}

	function test_sync_customer() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		// test sync new customer
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		// test sync non updated customer already in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertFalse( $result );

		// test sync updated customer already in TaxJar
		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'other' );
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		$record->delete_in_taxjar();

		// test sync updated customer not in TaxJar
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->set_status( 'awaiting' );
		$record->set_force_push( true );
		$result = $record->sync();
		$this->assertTrue( $result );

		$record->delete_in_taxjar();
	}

	function test_sync_on_customer_save() {
		$customer = TaxJar_Customer_Helper::create_non_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		$_POST[ 'user_id' ] = $customer->get_id();
		$_POST[ 'tax_exemption_type' ] = 'wholesale';
		$_POST[ 'tax_exempt_regions' ] = array( 'UT', 'CO' );

		$current_user = wp_get_current_user();
		$current_user->add_cap( 'manage_woocommerce' );

		do_action( 'edit_user_profile_update', $customer->get_id() );

		$this->assertGreaterThan( 0, did_action( 'taxjar_customer_exemption_settings_updated' ) );

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$response = $record->get_from_taxjar();
		$this->assertEquals( 200, $response['response']['code'] );
		$body = json_decode( $response[ 'body' ] );
		$this->assertEquals( 'wholesale', $body->customer->exemption_type );

		$valid_states = array( 'UT', 'CO' );
		$this->assertContains( $body->customer->exempt_regions[ 0 ]->state, $valid_states );
		$this->assertContains( $body->customer->exempt_regions[ 1 ]->state, $valid_states );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 0 ]->country );
		$this->assertEquals( 'US', $body->customer->exempt_regions[ 1 ]->country );

		$record->delete_in_taxjar();
	}

	function test_tax_calculation_with_customer_exemption() {
		// test guest checkout
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
		WC()->customer = TaxJar_Customer_Helper::create_customer();
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;
		WC()->cart->calculate_totals();

		$this->assertEquals( .43, WC()->cart->get_total_tax(), '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_shipping_tax(), '', 0.01 );
		$this->assertEquals( .43, WC()->cart->get_taxes_total(), '', 0.01 );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( .43, $item['line_tax'], '', 0.01 );
		}

		// test tax calculation for exempt customer
		$customer = TaxJar_Customer_Helper::create_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();
		$result = $record->sync();
		$this->assertTrue( $result );

		WC()->customer = $customer;
		WC()->cart->calculate_totals();
		$this->assertEquals( 0, WC()->cart->get_total_tax(), '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_shipping_tax(), '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_taxes_total(), '', 0.01 );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( 0, $item['line_tax'], '', 0.01 );
		}
		$record->delete_in_taxjar();
		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_tax_calculation_with_previously_exempt_customer() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;

		// test tax calculation for exempt customer
		$customer = TaxJar_Customer_Helper::create_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();
		$result = $record->sync();
		$this->assertTrue( $result );

		WC()->customer = $customer;
		WC()->cart->calculate_totals();
		$this->assertEquals( 0, WC()->cart->tax_total, '', 0.01 );
		$this->assertEquals( 0, WC()->cart->shipping_tax_total, '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_taxes_total(), '', 0.01 );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( 0, $item['line_tax'], '', 0.01 );
		}

		// test when customer updated to non exempt
		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'non_exempt' );
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product, 2 );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;
		WC()->customer = $customer;
		WC()->cart->calculate_totals();

		$this->assertEquals( 0.85, WC()->cart->get_total_tax(), '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_shipping_tax(), '', 0.01 );
		$this->assertEquals( 0.85, WC()->cart->get_taxes_total(), '', 0.01 );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( 0.85, $item['line_tax'], '', 0.01 );
		}

		$record->delete_in_taxjar();
		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );
		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_exempt_customer_deleted_from_taxjar() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
		$customer = TaxJar_Customer_Helper::create_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );
		$record->delete_in_taxjar();
		$test = $record->get_from_taxjar();

		WC()->customer = $customer;
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;
		WC()->cart->calculate_totals();

		$this->assertEquals( .43, WC()->cart->get_total_tax(), '', 0.01 );
		$this->assertEquals( 0, WC()->cart->get_shipping_tax(), '', 0.01 );
		$this->assertEquals( .43, WC()->cart->get_taxes_total(), '', 0.01 );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( .43, $item['line_tax'], '', 0.01 );
		}

		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );
		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_maybe_delete_customer() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		$data = $record->get_from_taxjar();
		$this->assertEquals( 200, $data[ 'response' ][ 'code'] );

		wp_delete_user( $customer->get_id() );

		$data = $record->get_from_taxjar();
		$this->assertEquals( 404, $data[ 'response' ][ 'code'] );

		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );
	}

	function test_process_queue_with_customer_record() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->save();

		$this->tj->transaction_sync->process_queue();

		$new_record = TaxJar_Customer_Record::find_active_in_queue( $customer->get_id() );
		$this->assertFalse( $new_record );

		$record->read();
		$record->load_object();

		$this->assertEquals( 'completed', $record->get_status() );
		$last_sync = $record->object->get_meta( '_taxjar_last_sync', true );
		$hash = $record->object->get_meta( '_taxjar_hash', true );
		$this->assertNotEmpty( $last_sync );
		$this->assertNotEmpty( $hash );

		$record->delete_in_taxjar();
		TaxJar_Customer_Helper::delete_customer( $customer->get_id() );
	}

	function test_sync_validation_on_unchanged_customer() {
		$customer = TaxJar_Customer_Helper::create_exempt_customer();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$record->delete_in_taxjar();

		// test sync new customer
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$result = $record->sync();
		$this->assertTrue( $result );

		// test sync validation on already synced unchanged customer record
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$validation_result = $record->should_sync();
		$this->assertFalse( $validation_result );

		// test sync validation after change to exemption settings
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		update_user_meta( $record->get_customer_id(), 'tax_exemption_type', 'government' );
		$validation_result = $record->should_sync();
		$this->assertTrue( $validation_result );

		$record->delete_in_taxjar();
	}
}
