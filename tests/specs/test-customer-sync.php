<?php
class TJ_WC_Test_Customer_Sync extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = TaxJar();
	}

	function tearDown() {
		parent::tearDown();
		WC_Taxjar_Record_Queue::clear_queue();
	}

	function test_get_exemption_type() {
		$customer = TaxJar_Customer_Helper::create_customer();
		$customer->set_email( 'test@test.com' );
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
		$customer->save();

		// test no object loaded
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$should_sync = $record->should_sync();
		$this->assertFalse( $should_sync );

		// test no name
		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertFalse( $should_sync );

		$customer->set_billing_first_name( 'Test' );
		$customer->set_billing_last_name( 'Test' );
		$customer->save();

		$record = new TaxJar_Customer_Record( $customer->get_id(), true );
		$record->load_object();
		$should_sync = $record->should_sync();
		$this->assertTrue( $should_sync );
	}

}