<?php
class TJ_WC_Class_Subscriptions extends WP_HTTP_TestCase {

	protected $server;

	protected $factory;

	function setUp() {

		parent::setUp();

		if ( ! class_exists( 'WC_Product_Subscription' ) ) {
			$this->markTestSkipped('WooCommerce Subscriptions plugin is required');
		}

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

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		WC()->cart->recurring_carts = array();
		$this->tj = WC()->integrations->integrations['taxjar-integration'];

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_postcode' => '80111',
			'store_city' => 'Greenwood Village',
		) );

		if ( class_exists( 'WC_Cart_Totals' ) ) { // Woo 3.2+
			$this->action = 'woocommerce_after_calculate_totals';
		} else {
			$this->action = 'woocommerce_calculate_totals';
		}

		// We need this to have the calculate_totals() method calculate totals
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}
	}

	function tearDown() {
		// Empty the cart
		WC()->cart->empty_cart();

		parent::tearDown();
	}

	function test_correct_taxes_for_subscription_products_with_trial() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 0,
			'trial_length' => 1,
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0, '', 0.01 );

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_trial_and_signup_fee() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 50,
			'trial_length' => 1,
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 3.63, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 3.63, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 50 + 3.63, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 3.63, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_no_trial() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 0,
			'trial_length' => 0,
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1.45, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.45, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 1.45, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 1.45, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_no_trial_and_signup_fee() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 50,
			'trial_length' => 0,
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 5.07, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 5.07, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 50 + 5.07, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 5.07, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_other_products() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 0,
			'trial_length' => 0,
		) )->get_id();

		$extra_product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->add_to_cart( $extra_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2.18, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.18, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 10 + 2.18, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 1.45, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.73, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_other_products_and_trial() {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 0,
			'trial_length' => 1,
		) )->get_id();

		$extra_product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->add_to_cart( $extra_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.73, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.73, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 10 + 0.73, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.73, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.45, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.45, '', 0.01 );
		}
	}

	function test_correct_taxes_for_subscription_products_with_other_products_and_trial_and_shipping() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		// NJ shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NJ',
			'zip' => '07001',
			'city' => 'Avenel',
		) );

		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '10',
			'sign_up_fee' => 100,
			'trial_length' => 1,
			'virtual' => 'no',
		) )->get_id();
		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '200',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '100',
			'sku' => 'EXEMPT2',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product );

		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 6.63, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0.66, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 7.29, '', 0.01 );

		if ( method_exists( WC()->cart, 'get_shipping_taxes' ) ) {
			$this->assertEquals( array_values( WC()->cart->get_shipping_taxes() )[0], 0.66, '', 0.01 );
		} else {
			$this->assertEquals( array_values( WC()->cart->shipping_taxes )[0], 0.66, '', 0.01 );
		}

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 400 + 10 + 7.29, '', 0.01 );
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.66, '', 0.01 );
			$this->assertEquals( $recurring_cart->shipping_tax_total, 0.66, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.32, '', 0.01 );
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_for_subscription_products_with_other_products_and_trial_and_thresholds() {
		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10011',
			'city' => 'New York City',
		) );

		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '19.99',
			'sign_up_fee' => 100,
			'trial_length' => 1,
		) )->get_id();
		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '200', // Over $110 threshold
			'sku' => 'EXEMPTOVER1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '10',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $subscription_product );
		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 26.63, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 26.63, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 100 + 200 + 10 + 26.63, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 8.88, '', 0.01 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 17.75, '', 0.01 );
			}

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 1.77, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 1.77, '', 0.01 );
		}
	}

	/**
	 * @expectedDeprecated get_used_coupons
	 */
	function test_correct_taxes_for_subscription_recurring_order() {
		wp_set_current_user( $this->user );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		$request = TaxJar_Subscription_Helper::prepare_subscription_request();
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( $data['total'], 110.00, '', 0.01 );

		$subscription_id = $data['id'];
		$renewal_order = wcs_create_order_from_subscription( $subscription_id, 'renewal_order' );

		$this->assertEquals( $renewal_order->get_shipping_tax(), 0.73, '', 0.01 );
		$this->assertEquals( $renewal_order->get_cart_tax(), 7.25, '', 0.01 );
		$this->assertEquals( $renewal_order->get_total(), 117.98 , '', 0.01 );

		$subscription = wcs_get_subscription( $subscription_id );

		// test to ensure subscription tax has been correctly calculated and updated
		$this->assertEquals( $subscription->get_shipping_tax(), 0.73, '', 0.01 );
		$this->assertEquals( $subscription->get_cart_tax(), 7.25, '', 0.01 );
		$this->assertEquals( $subscription->get_total(), 117.98 , '', 0.01 );

		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	/**
	 * @expectedDeprecated get_used_coupons
	 */
	function test_correct_taxes_for_subscription_recurring_order_with_one_month_trial() {
		wp_set_current_user( $this->user );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		$subscription_product_id = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '100',
			'sign_up_fee' => 0,
			'trial_length' => 1,
		) )->get_id();

		$trial_end_date = date("Y-m-d H:i:s", strtotime("+1 month" ) );

		$parameters = array(
			'line_items' => array(
				array(
					'product_id' => $subscription_product_id,
					'quantity'   => 1
				)
			),
			'trial_end_date' => $trial_end_date
		);

		$request = TaxJar_Subscription_Helper::prepare_subscription_request( $parameters );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( $data['total'], 110.00, '', 0.01 );

		$subscription_id = $data['id'];
		$renewal_order = wcs_create_order_from_subscription( $subscription_id, 'renewal_order' );

		$this->assertEquals( $renewal_order->get_shipping_tax(), 0.73, '', 0.01 );
		$this->assertEquals( $renewal_order->get_cart_tax(), 7.25, '', 0.01 );
		$this->assertEquals( $renewal_order->get_total(), 117.98 , '', 0.01 );

		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	/**
	 * @expectedDeprecated get_used_coupons
	 */
	function test_correct_taxes_for_subscription_recurring_order_with_trial_and_signup_fee() {
		wp_set_current_user( $this->user );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		$subscription_product_id = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '100',
			'sign_up_fee' => 50,
			'trial_length' => 1,
		) )->get_id();

		$trial_end_date = date("Y-m-d H:i:s", strtotime("+1 month" ) );

		$parameters = array(
			'line_items' => array(
				array(
					'product_id' => $subscription_product_id,
					'quantity'   => 1
				)
			),
			'trial_end_date' => $trial_end_date
		);

		$request = TaxJar_Subscription_Helper::prepare_subscription_request( $parameters );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( $data['total'], 110.00, '', 0.01 );

		$subscription_id = $data['id'];
		$renewal_order = wcs_create_order_from_subscription( $subscription_id, 'renewal_order' );

		$this->assertEquals( $renewal_order->get_shipping_tax(), 0.73, '', 0.01 );
		$this->assertEquals( $renewal_order->get_cart_tax(), 7.25, '', 0.01 );
		$this->assertEquals( $renewal_order->get_total(), 117.98 , '', 0.01 );

		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	/**
	 * @expectedDeprecated get_used_coupons
	 */
	function test_correct_taxes_for_subscription_recurring_order_with_multiple_products() {
		wp_set_current_user( $this->user );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		$subscription_product_id = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '100',
			'sign_up_fee' => 50,
			'trial_length' => 1,
		) )->get_id();
		$second_product_id = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '50',
			'sign_up_fee' => 0,
			'trial_length' => 0,
		) )->get_id();

		$trial_end_date = date("Y-m-d H:i:s", strtotime("+1 month" ) );

		$parameters = array(
			'line_items' => array(
				array(
					'product_id' => $subscription_product_id,
					'quantity'   => 1
				),
				array(
					'product_id' => $second_product_id,
					'quantity'   => 1
				),
			),
			'trial_end_date' => $trial_end_date
		);

		$request = TaxJar_Subscription_Helper::prepare_subscription_request( $parameters );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertEquals( $data['total'], 160.00, '', 0.01 );

		$subscription_id = $data['id'];
		$renewal_order = wcs_create_order_from_subscription( $subscription_id, 'renewal_order' );

		$this->assertEquals( $renewal_order->get_shipping_tax(), 0.73, '', 0.01 );
		$this->assertEquals( $renewal_order->get_cart_tax(), 10.88, '', 0.01 );

		// handle rounding difference is woocommerce versions
		if ( version_compare( WC()->version, '3.2.0', '<=' ) ) {
			$this->assertEquals( $renewal_order->get_total(), 171.60, '', 0.01 );
		} else {
			$this->assertEquals( $renewal_order->get_total(), 171.61, '', 0.01 );
		}

		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

}
