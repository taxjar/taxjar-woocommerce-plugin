<?php

use TaxJar\Constants_Manager;

/**
 * Class TJ_WC_REST_Unit_Test_Case
 */
class TJ_WC_REST_Unit_Test_Case extends WP_HTTP_TestCase {

	protected $server;
	protected $endpoint;
	protected $user;
	protected $factory;
	protected $order_endpoint;

	/**
	 * @var WC_Taxjar_Integration
	 */
	public $tj;

	/**
	 * Sets up the fixture before each test
	 */
	function setUp(): void {

		parent::setUp();

		Constants_Manager::set_constant( 'REST_REQUEST', true );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$this->factory = new WP_UnitTest_Factory();
		$this->user    = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->tj = TaxJar();

		TaxJar_Woocommerce_Helper::set_shipping_origin(
			$this->tj,
			array(
				'store_country'  => 'US',
				'store_state'    => 'CO',
				'store_street'   => '6060 S Quebec St',
				'store_postcode' => '80111',
				'store_city'     => 'Greenwood Village',
			)
		);

		update_option( 'woocommerce_currency', 'USD' );

		wp_set_current_user( $this->user );
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		update_option( 'woocommerce_tax_classes', "Reduced rate\nZero Rate\nGift Card - 14111803A0001" );

		if ( version_compare( WC()->version, '3.7.0', '>=' ) ) {
			WC_Tax::create_tax_class( 'Gift Card - 14111803A0001' );
		}
	}

	/**
	 * Cleans up after each test
	 */
	function tearDown(): void {
		parent::tearDown();
		global $wp_rest_server;
		unset( $this->server );
		$wp_rest_server = null;
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
		Constants_Manager::clear_constants();
	}

	static function tearDownAfterClass(): void {
		global $wpdb;

		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta;" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_order_items;" );
		$wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_type = 'order_note';" );
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ( SELECT ID FROM wp_posts WHERE post_type = 'shop_order' );" );
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'shop_order';" );

		parent::tearDownAfterClass();
	}

	/**
	 * Tests tax calculation on a simple order created through the WooCommerce REST API
	 */
	function test_simple_product_tax_on_api_order() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items' => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1,
					),
				),
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 0.75, $order->get_total_tax(), '', 0.01 );

		foreach ( $order->get_items() as $item ) {
			$this->assertEquals( 0.75, $item->get_total_tax(), '', 0.01 );
		}
	}

	/**
	 * Test api order tax calculation on shipping
	 */
	function test_shipping_tax_on_api_order() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'shipping'   => array(
					'first_name' => 'Test',
					'last_name'  => 'Customer',
					'address_1'  => '123 Main St.',
					'address_2'  => '',
					'city'       => 'New York City',
					'state'      => 'NY',
					'postcode'   => '10001',
					'country'    => 'US',
				),
				'line_items' => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1,
					),
				),
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 1.78, $order->get_total_tax(), '', 0.02 );
		$this->assertEquals( 0.89, $order->get_shipping_tax(), '', 0.01 );

		foreach ( $order->get_items() as $item ) {
			$this->assertEquals( 0.89, $item->get_total_tax(), '', 0.01 );
		}
	}

	/**
	 * Test tax calculation on api order containing an exempt product
	 */
	function test_product_exemption_on_api_order() {
		$product           = TaxJar_Product_Helper::create_product(
			'simple',
			array(
				'price'     => '10',
				'sku'       => 'EXEMPT-GIFT-CARD',
				'tax_class' => 'Gift Card - 14111803A0001',
			)
		);
		$exempt_product_id = $product->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items' => array(
					array(
						'product_id' => $exempt_product_id,
						'quantity'   => 1,
					),
				),
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 0.00, $order->get_total_tax(), '', 0.01 );

		foreach ( $order->get_items() as $item ) {
			$this->assertEquals( 0.00, $item->get_total_tax() );
		}
	}

	/**
	 * Test tax calculation on api order containing a fee
	 */
	function test_fee_tax_on_api_order() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items' => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1,
					),
				),
				'fee_lines'  => array(
					array(
						'name'  => 'test fee',
						'total' => '100.00',
					),
				),
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 8.2, $order->get_total_tax(), '', 0.01 );

		foreach ( $order->get_items() as $item ) {
			$this->assertEquals( 0.75, $item->get_total_tax(), '', 0.01 );
		}

		foreach ( $order->get_fees() as $fee ) {
			$this->assertEquals( 7.45, $fee->get_total_tax() );
		}
	}

	/**
	 * Test tax calculation on api order containing an exempt fee
	 */
	function test_exempt_fee_on_api_order() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders' );
		$request_body = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items' => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1,
					),
				),
				'fee_lines'  => array(
					array(
						'name'      => 'test fee',
						'total'     => '100.00',
						'tax_class' => 'gift-card-14111803a0001',
					),
				),
			)
		);
		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();
		$order    = wc_get_order( $data['id'] );

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( 0.75, $order->get_total_tax(), '', 0.01 );

		foreach ( $order->get_items() as $item ) {
			$this->assertEquals( 0.75, $item->get_total_tax(), '', 0.01 );
		}

		foreach ( $order->get_fees() as $fee ) {
			$this->assertEquals( 0.00, $fee->get_total_tax() );
		}
	}

	/**
	 * Test tax calculation on orders created in a batch request
	 */
	function test_batch_order_creation_through_api() {
		$product_id = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$request      = new WP_REST_Request( 'POST', $this->order_endpoint . 'orders/batch' );
		$order_params = TaxJar_API_Order_Helper::create_order_request_body(
			array(
				'line_items' => array(
					array(
						'product_id' => $product_id,
						'quantity'   => 1,
					),
				),
			)
		);
		$request_body = array(
			'create' => array(
				$order_params,
				$order_params,
			),
		);

		$request->set_body_params( $request_body );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		foreach ( $data['create'] as $order_response ) {
			$order = wc_get_order( $order_response['id'] );

			$this->assertEquals( 0.75, $order->get_total_tax(), '', 0.01 );

			foreach ( $order->get_items() as $item ) {
				$this->assertEquals( 0.75, $item->get_total_tax(), '', 0.01 );
			}
		}
	}
}
