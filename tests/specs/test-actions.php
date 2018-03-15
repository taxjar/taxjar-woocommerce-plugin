<?php
class TJ_WC_Actions extends WP_UnitTestCase {

	function setUp() {
		global $woocommerce;
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = new WC_Taxjar_Integration();

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_zip' => '80111',
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
		// Prevent duplicate action callbacks
		remove_action( $this->action, array( $this->tj, 'calculate_totals' ), 20 );
		remove_action( 'woocommerce_before_save_order_items', array( $this->tj, 'calculate_backend_totals' ), 20 );

		// Empty the cart
		WC()->cart->empty_cart();
	}

	function test_taxjar_calculate_totals() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();
		$this->assertTrue( WC()->cart->get_taxes_total() != 0 );
	}

	function test_correct_taxes_with_shipping() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 5;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.4, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0.2, '', 0.01 );

		if ( method_exists( WC()->cart, 'get_shipping_taxes' ) ) {
			$this->assertEquals( array_values( WC()->cart->get_shipping_taxes() )[0], 0.2, '', 0.01 );
		} else {
			$this->assertEquals( array_values( WC()->cart->shipping_taxes )[0], 0.2, '', 0.01 );
		}

		$this->assertEquals( WC()->cart->get_taxes_total(), 0.6, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.4, '', 0.01 );
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_with_exempt_shipping() {
		// CA shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'CA',
			'zip' => '90404',
			'city' => 'Santa Monica',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );

		TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 5;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1.03, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.03, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 1.03, '', 0.01 );
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_for_multiple_products() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$extra_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'SIMPLE2',
		) )->get_id();

		WC()->cart->add_to_cart( $product );
		WC()->cart->add_to_cart( $extra_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2.4, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.4, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 2, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.4, '', 0.01 );
			}
		}

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 62.4, '', 0.01 );
		}
	}

	function test_correct_taxes_for_multiple_products_with_rounding_difference() {
		$product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '485',
		) )->get_id();
		$extra_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '225',
			'sku' => 'SIMPLE2',
		) )->get_id();

		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'CA',
			'zip' => '93013',
			'city' => 'Carpinteria',
		) );

		WC()->cart->add_to_cart( $product );
		WC()->cart->add_to_cart( $extra_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 72.47, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 72.47, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 1007.47, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 37.59, '', 0.01 );
			}

			if ( 'SIMPLE2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 34.88, '', 0.01 );
			}
		}
	}

	function test_correct_taxes_for_duplicate_line_items() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );
		WC()->cart->add_to_cart( $product, 1, 0, [], [ 'duplicate' => true ] );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.8, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.8, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 20.8, '', 0.01 );
		}
	}

	function test_correct_taxes_for_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_status' => 'none',
		) )->get_id();

		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0, '', 0.01 );
	}

	function test_correct_taxes_for_zero_rate_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_class' => 'zero-rate',
		) )->get_id();

		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0, '', 0.01 );
	}

	function test_correct_taxes_for_product_exemptions() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_zip' => '10001',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10001',
			'city' => 'New York City',
		) );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.89, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.89, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.89, '', 0.01 );
			}
		}

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 60.89, '', 0.01 );
		}
	}

	function test_correct_taxes_for_product_exemption_thresholds() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_zip' => '10001',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10001',
			'city' => 'New York City',
		) );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '150', // Over $110 threshold
			'sku' => 'EXEMPTOVER1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 13.31, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 13.31, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 13.31, '', 0.01 );
			}
		}
	}

	function test_correct_taxes_for_product_exemption_threshold_reduced_rates() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_zip' => '10118',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10541',
			'city' => 'Mahopac',
		) );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '150', // Over $110 threshold
			'sku' => 'EXEMPTOVER1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$reduced_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'REDUCED1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $reduced_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 14.75, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 14.75, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'REDUCED1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 2.19, '', 0.01 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 12.56, '', 0.01 );
			}
		}
	}

	function test_correct_taxes_for_discounts() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$product2 = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '30',
			'sku' => 'SIMPLE2',
		) )->get_id();
		$coupon = TaxJar_Coupon_Helper::create_coupon( array(
			'amount' => '10',
			'discount_type' => 'fixed_cart',
		) );

		if ( version_compare( WC()->version, '3.0', '>=' ) ) {
			$coupon = $coupon->get_code();
		} else {
			$coupon = $coupon->code;
		}

		WC()->cart->add_to_cart( $product );
		WC()->cart->add_to_cart( $product2, 2 );
		WC()->cart->add_discount( $coupon );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2.4, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.4, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 62.4, '', 0.01 );
		}
	}

	function test_correct_taxes_for_intrastate_origin_state() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'TX',
			'store_zip' => '76082',
			'store_city' => 'Springtown',
		) );

		// TX shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'TX',
			'zip' => '73301',
			'city' => 'Austin',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.83, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.83, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 10.83, '', 0.01 );
		}
	}

	function test_correct_taxes_for_interstate_origin_state() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NC',
			'store_zip' => '27545',
			'store_city' => 'Raleigh',
		) );

		// TX shipping address
		// Make sure your test account has nexus in TX
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'TX',
			'zip' => '73301',
			'city' => 'Austin',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.83, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.83, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 10.83, '', 0.01 );
		}
	}

	function test_correct_taxes_for_canada() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'CA',
			'store_state' => 'BC',
			'store_zip' => 'V6G 3E2',
			'store_city' => 'Vancouver',
		) );

		// CA shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'CA',
			'state' => 'ON',
			'zip' => 'M5V 2T6',
			'city' => 'Toronto',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1.3, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.3, '', 0.01 );
	}

	function test_correct_taxes_for_au() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'AU',
			'store_state' => 'NSW',
			'store_zip' => 'NSW 2000',
			'store_city' => 'Sydney',
		) );

		// AU shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'AU',
			'state' => 'VIC',
			'zip' => 'VIC 3002',
			'city' => 'Richmond',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1, '', 0.01 );
	}

	function test_correct_taxes_for_eu() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'FR',
			'store_state' => '',
			'store_zip' => '75008',
			'store_city' => 'Paris',
		) );

		// EU shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'FR',
			'state' => '',
			'zip' => '13281',
			'city' => 'Marseille',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2, '', 0.01 );
	}

	function test_correct_taxes_for_uk_or_gb() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'UK',
			'store_state' => '',
			'store_zip' => 'SW1A 1AA',
			'store_city' => 'London',
		) );

		// UK shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'GB',
			'state' => '',
			'zip' => 'SW1A 1AA',
			'city' => 'London',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2, '', 0.01 );
	}

	function test_correct_taxes_for_el_or_gr() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'EL',
			'store_state' => '',
			'store_zip' => '104 47',
			'store_city' => 'Athens',
		) );

		// Greece shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'GR',
			'state' => '',
			'zip' => '104 31',
			'city' => 'Athens',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2.4, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.4, '', 0.01 );
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
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 2, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 50 + 2, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 2, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 0.8, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.8, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 0.8, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.8, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 2.8, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.8, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 50 + 2.8, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 2.8, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 1.2, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.2, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 19.99 + 10 + 1.2, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.8, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.4, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 0.4, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.4, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 10 + 0.4, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SUBSCRIPTION1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.4, '', 0.01 );
			}
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			$this->assertEquals( $recurring_cart->tax_total, 0.8, '', 0.01 );
			$this->assertEquals( $recurring_cart->get_taxes_total(), 0.8, '', 0.01 );
		}
	}
}
