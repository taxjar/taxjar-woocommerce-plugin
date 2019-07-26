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