<?php

/**
 * Class TJ_WC_Tests_API_Orders_V2
 */
class TJ_WC_Tests_API_Orders_V2 extends TJ_WC_REST_Unit_Test_Case {

	protected $order_endpoint = '/wc/v2/';

	/**
	 * Set up fixture before each test
	 */
	function setUp(): void {
		parent::setUp();

		// WooCommerce REST API V2
		if ( ! class_exists( 'WC_REST_Orders_V2_Controller' ) ) {
			$this->endpoint = new WC_REST_Orders_Controller();
		} else {
			$this->endpoint = new WC_REST_Orders_V2_Controller();
		}
	}

	/**
	 * Clean up after each test
	 */
	function tearDown(): void {
		parent::tearDown();
	}

}
