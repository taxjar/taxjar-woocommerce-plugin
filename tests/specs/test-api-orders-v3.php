<?php

/**
 * Class TJ_WC_Tests_API_Orders_V3
 */
class TJ_WC_Tests_API_Orders_V3 extends TJ_WC_REST_Unit_Test_Case {

	protected $order_endpoint = '/wc/v3/';

	/**
	 * Set up fixture before each test
	 */
	function setUp() {
		parent::setUp();

		if ( ! class_exists( 'WC_REST_Orders_V2_Controller' ) ) {
			$this->markTestSkipped( 'WooCommerce REST API V3 is not included in this version of WooCommerce' );
		}

		// WooCommerce REST API V3
		$this->endpoint = new WC_REST_Orders_Controller();
	}

	/**
	 * Clean up after each test
	 */
	function tearDown() {
		parent::tearDown();
	}

}
