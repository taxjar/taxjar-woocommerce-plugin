<?php
class TJ_WC_Test_API_Orders extends WP_HTTP_TestCase {

	function setUp() {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->factory = new WP_UnitTest_Factory();
		$this->endpoint = new WC_REST_Orders_Controller();
		$this->user     = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->tj = TaxJar();

		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_street' => '6060 S Quebec St',
			'store_postcode' => '80111',
			'store_city' => 'Greenwood Village',
		) );

		update_option( 'woocommerce_currency', 'USD' );

		wp_set_current_user( $this->user );
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
	}

	function tearDown() {
		parent::tearDown();

		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_simple_product_api_order_calculation() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request = new WP_REST_Request( 'POST', '/wc/v3/orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items'           => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1
					)
				)
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 0.73, $order->get_total_tax() );

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( 0.73, $item->get_total_tax() );
		}
	}


}