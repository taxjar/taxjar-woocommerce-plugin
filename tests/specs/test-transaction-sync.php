<?php
class TJ_WC_Test_Sync extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = WC()->integrations->integrations['taxjar-integration'];

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_postcode' => '80111',
			'store_city' => 'Greenwood Village',
		) );
	}

	function tearDown() {
		parent::tearDown();

		WC_Taxjar_Record_Queue::clear_queue();
	}

	function test_install_and_uninstall() {
		// clean existing install first.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		if ( ! defined( 'TAXJAR_REMOVE_ALL_DATA' ) ) {
			define( 'TAXJAR_REMOVE_ALL_DATA', true );
		}

		include dirname( dirname( dirname( __FILE__ ) ) ) . '/uninstall.php';
		delete_transient( 'taxjar_installing' );

		WC_Taxjar_Install::install();

		$this->assertEquals( WC_Taxjar::$version, get_option( 'taxjar_version' ) );

		global $wpdb;
		$table_name = WC_Taxjar_Record_Queue::get_queue_table_name();
		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		$this->assertEquals( $result, $table_name );

		include dirname( dirname( dirname( __FILE__ ) ) ) . '/uninstall.php';
		delete_transient( 'taxjar_installing' );

		$this->assertFalse( get_option( 'taxjar_version' ) );

		WC_Taxjar_Install::install();
	}

	function test_create_new_order_record() {
		$order = TaxJar_Order_Helper::create_order();
		$record = TaxJar_Order_Record::load( $order->get_id() );

		$this->assertEquals( $order->get_id(), $record->get_record_id() );
		$this->assertTrue( $record->object instanceof WC_Order );
		$this->assertEquals( 0, $record->get_batch_id() );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_get_order_record_by_queue_id() {
		$order = TaxJar_Order_Helper::create_order();
		$record = TaxJar_Order_Record::load( $order->get_id() );
		$record->save();

		$queue_id = $record->get_queue_id();
		$this->assertNotNull( $queue_id );

		$retrieved_record = TaxJar_Order_Record::load( null, $queue_id );
		$this->assertEquals( $order->get_id(), $retrieved_record->get_record_id() );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_get_order_data() {
		$order = TaxJar_Order_Helper::create_order();
		$record = TaxJar_Order_Record::load( $order->get_id() );
		$order_data = $record->get_order_data();

		$expected_order_data = array(
			'from_country' => 'US',
			'from_zip' => '80111',
			'from_state' => 'CO',
			'from_city' => 'Greenwood Village',
			'from_street' => '6060 S Quebec St',
			'to_country' => 'US',
			'to_zip' => '80111',
			'to_state' => 'CO',
			'to_city' => 'Greenwood Village',
			'to_street' => '6060 S Quebec St',
			'amount' => 110,
			'shipping' => '10',
			'sales_tax' => '7.98',
			'customer_id' => 1
		);

		foreach( $expected_order_data as $key => $expected ) {
			$this->assertEquals( $expected, $order_data[ $key ] );
		}

		$expected_line_item_data = array(
			'quantity' => 1,
			'product_identifier' => 'SIMPLE1',
			'description' => 'Dummy Product',
			'product_tax_code' => '',
			'unit_price' => 100,
			'discount' => 0,
			'sales_tax' => '7.25'
		);

		foreach( $expected_line_item_data as $key => $expected ) {
			$this->assertEquals( $expected, $order_data[ 'line_items' ][ 0 ][ $key ] );
		}

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_get_active_order_record_in_queue() {
		$order = TaxJar_Order_Helper::create_order();
		$record = TaxJar_Order_Record::load( $order->get_id() );
		$record->save();

		$retrieved_record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );
		$this->assertEquals( $order->get_id(), $retrieved_record->get_record_id() );
		$this->assertEquals( $record->get_created_datetime(), $retrieved_record->get_created_datetime() );
		$this->assertEquals( 0, $retrieved_record->get_retry_count() );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_new_completed_order_add_to_queue() {
		$order = TaxJar_Order_Helper::create_order( 1 );
		$order->update_status( 'completed' );

		$record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );
		$this->assertNotFalse( $record );
		$this->assertEquals( $order->get_id(), $record->get_record_id() );
		$this->assertEquals( 0, $record->get_retry_count() );
		$this->assertEquals( 'new', $record->get_status() );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_process_queue() {
		$order = TaxJar_Order_Helper::create_order( 1 );
		$order->update_status( 'completed' );
		$record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );

		$second_order = TaxJar_Order_Helper::create_order( 1 );
		$second_order->update_status( 'completed' );
		$second_record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );

		$batches = $this->tj->transaction_sync->process_queue();

		$batch_timestamp = as_next_scheduled_action( WC_Taxjar_Transaction_Sync::PROCESS_BATCH_HOOK );

		$this->assertNotFalse( $batch_timestamp );

		foreach( $batches as $batch_id ) {
			// scheduled actions are stored as posts
			$batch = get_post( $batch_id );
			// args for the scheduled action are stored in post_content field
			$args = json_decode( $batch->post_content, true );

			$this->assertContains( $record->get_queue_id(), $args[ 'queue_ids' ] );
			$this->assertContains( $second_record->get_queue_id(), $args[ 'queue_ids' ] );
		}

		TaxJar_Order_Helper::delete_order( $order->get_id() );
		TaxJar_Order_Helper::delete_order( $second_order->get_id() );
	}

	function test_create_order_in_taxjar() {
		$order = TaxJar_Order_Helper::create_order( 1 );
		$record = TaxJar_Order_Record::load( $order->get_id() );
		$result = $record->create_in_taxjar();

		$this->assertEquals( 201, $result[ 'response' ][ 'code' ] );
		$result = $record->delete_in_taxjar();
	}

	function test_update_order_in_taxjar() {
		$order = TaxJar_Order_Helper::create_order( 1 );
		$record = TaxJar_Order_Record::load( $order->get_id() );
		$result = $record->create_in_taxjar();

		$order->set_shipping_city( 'test' );
		$order->save();

		$new_record = TaxJar_Order_Record::load( $order->get_id() );
		$result = $new_record->update_in_taxjar();

		$this->assertEquals( 200, $result[ 'response' ][ 'code' ] );

		$record->delete_in_taxjar();
	}

}