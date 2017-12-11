<?php
class TJ_WC_Actions extends WP_UnitTestCase {

	function setUp() {
		global $woocommerce;
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = new WC_Taxjar_Integration();
		$this->wc = $woocommerce;

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
	}

	function tearDown() {
		// Prevent duplicate action callbacks
		remove_action( $this->action, array( $this->tj, 'calculate_totals' ), 20 );
		remove_action( 'woocommerce_before_save_order_items', array( $this->tj, 'calculate_backend_totals' ), 20 );

		// Empty the cart
		$this->wc->cart->empty_cart();
	}

	function test_taxjar_calculate_totals() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );
		do_action( $this->action, $this->wc->cart );
		$this->assertTrue( $this->wc->cart->get_taxes_total() != 0 );
	}

	function test_correct_taxes_with_shipping() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$this->wc->shipping->shipping_total = 5;
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->shipping_tax_total, 0.2, '', 0.001 );

		if ( method_exists( $this->wc->cart, 'get_shipping_taxes' ) ) {
			$this->assertEquals( array_values( $this->wc->cart->get_shipping_taxes() )[0], 0.2, '', 0.001 );
		} else {
			$this->assertEquals( array_values( $this->wc->cart->shipping_taxes )[0], 0.2, '', 0.001 );
		}

		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0.6, '', 0.001 );

		foreach ( $this->wc->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.4, '', 0.001 );
		}
	}

	function test_correct_taxes_for_multiple_products() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$extra_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'SIMPLE2',
		) )->get_id();

		$this->wc->cart->add_to_cart( $product );
		$this->wc->cart->add_to_cart( $extra_product, 2 );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 2.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 2.4, '', 0.001 );

		foreach ( $this->wc->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 2, '', 0.001 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.4, '', 0.001 );
			}
		}

		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 62.4, '', 0.001 );
	}

	function test_correct_taxes_for_duplicate_line_items() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$this->wc->cart->add_to_cart( $product );
		$this->wc->cart->add_to_cart( $product, 1, 0, [], [ 'duplicate' => true ] );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.8, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0.8, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 20.8, '', 0.001 );
	}

	function test_correct_taxes_for_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_status' => 'none',
		) )->get_id();

		$this->wc->cart->add_to_cart( $exempt_product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0, '', 0.001 );
	}

	function test_correct_taxes_for_zero_rate_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_class' => 'zero-rate',
		) )->get_id();

		$this->wc->cart->add_to_cart( $exempt_product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0, '', 0.001 );
	}

	function test_correct_taxes_for_product_exemptions() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_zip' => '10001',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
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

		$this->wc->cart->add_to_cart( $taxable_product );
		$this->wc->cart->add_to_cart( $exempt_product, 2 );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.89, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0.89, '', 0.001 );

		foreach ( $this->wc->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.001 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.89, '', 0.001 );
			}
		}

		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 60.89, '', 0.001 );
	}

	function test_correct_taxes_for_product_exemption_thresholds() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_zip' => '10001',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
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

		$this->wc->cart->add_to_cart( $taxable_product );
		$this->wc->cart->add_to_cart( $exempt_product, 2 );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 13.31, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 13.31, '', 0.001 );

		foreach ( $this->wc->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.001 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 13.31, '', 0.001 );
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
		) )->get_code();

		$this->wc->cart->add_to_cart( $product );
		$this->wc->cart->add_to_cart( $product2, 2 );
		$this->wc->cart->add_discount( $coupon );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 2.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 2.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 62.4, '', 0.001 );
	}

	function test_correct_taxes_for_intrastate_origin_state() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'TX',
			'store_zip' => '76082',
			'store_city' => 'Agnes',
		) );

		// TX shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'TX',
			'zip' => '73301',
			'city' => 'Austin',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.68, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0.68, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 10.68, '', 0.001 );
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
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'TX',
			'zip' => '73301',
			'city' => 'Austin',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.83, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 0.83, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_total( 'amount' ), 10.83, '', 0.001 );
	}

	function test_correct_taxes_for_canada() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'CA',
			'store_state' => 'BC',
			'store_zip' => 'V6G 3E2',
			'store_city' => 'Vancouver',
		) );

		// CA shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'CA',
			'state' => 'ON',
			'zip' => 'M5V 2T6',
			'city' => 'Toronto',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 1.3, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 1.3, '', 0.001 );
	}

	function test_correct_taxes_for_au() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'AU',
			'store_state' => 'NSW',
			'store_zip' => 'NSW 2000',
			'store_city' => 'Sydney',
		) );

		// AU shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'AU',
			'state' => 'VIC',
			'zip' => 'VIC 3002',
			'city' => 'Richmond',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 1, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 1, '', 0.001 );
	}

	function test_correct_taxes_for_eu() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'FR',
			'store_state' => '',
			'store_zip' => '75008',
			'store_city' => 'Paris',
		) );

		// EU shipping address
		$this->wc->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'FR',
			'state' => '',
			'zip' => '13281',
			'city' => 'Marseille',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );

		do_action( $this->action, $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 2, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 2, '', 0.001 );
	}

}
