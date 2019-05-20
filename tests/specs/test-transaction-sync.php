<?php
class TJ_WC_Test_Sync extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
	}

	function tearDown() {
		parent::tearDown();
	}

	function test_install() {
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

		$scheduled_action = as_next_scheduled_action( WC_Taxjar_Transaction_Sync::PROCESS_QUEUE_HOOK );
		$this->assertNotEmpty( $scheduled_action );

	}

}