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

}