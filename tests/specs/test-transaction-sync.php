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

	function test_get_order_data() {
		$order = TaxJar_Order_Helper::create_order();
		$order_data = WC_Taxjar_Record_Queue::get_order_data( $order );

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

	function test_add_to_queue() {
		$order = TaxJar_Order_Helper::create_order();

		$data = WC_Taxjar_Record_Queue::get_order_data( $order );
		$result = WC_Taxjar_Record_Queue::add_to_queue( $order->get_id(), 'order', $data );

		$this->assertNotFalse( $result );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

	function test_find_active_in_queue() {
		$order = TaxJar_Order_Helper::create_order();
		$data = WC_Taxjar_Record_Queue::get_order_data( $order );
		WC_Taxjar_Record_Queue::add_to_queue( $order->get_id(), 'order', $data );

		$result = WC_Taxjar_Record_Queue::find_active_in_queue( $order->get_id() );
		$this->assertNotFalse( $result );

		TaxJar_Order_Helper::delete_order( $order->get_id() );
	}

}