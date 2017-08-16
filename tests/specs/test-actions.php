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
	}

	function tearDown() {
		// Prevent duplicate action callbacks
		remove_action( 'woocommerce_calculate_totals', array( $this->tj, 'calculate_totals' ), 20 );
		remove_action( 'woocommerce_before_save_order_items', array( $this->tj, 'calculate_backend_totals' ), 20 );
	}

	function test_taxjar_calculate_totals() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		$this->wc->cart->add_to_cart( $product );
		do_action( 'woocommerce_calculate_totals', $this->wc->cart );
		$this->assertTrue( $this->wc->cart->get_taxes_total() != 0 );
	}

	function test_correct_taxes_with_shipping() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		$this->wc->shipping->shipping_total = 5;
		$this->wc->cart->add_to_cart( $product );

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 0.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->shipping_tax_total, 0.2, '', 0.001 );
		$this->assertEquals( array_values( $this->wc->cart->shipping_taxes )[0], 0.2, '', 0.001 );
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

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

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
	}

	function test_correct_taxes_for_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_status' => 'none',
		) )->get_id();

		$this->wc->cart->add_to_cart( $exempt_product );

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

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
			'tax_class' => 'clothing-20010',
		) )->get_id();

		$this->wc->cart->add_to_cart( $taxable_product );
		$this->wc->cart->add_to_cart( $exempt_product, 2 );

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

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

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 2.4, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 2.4, '', 0.001 );
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

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

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

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

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

		do_action( 'woocommerce_calculate_totals', $this->wc->cart );

		$this->assertEquals( $this->wc->cart->tax_total, 2, '', 0.001 );
		$this->assertEquals( $this->wc->cart->get_taxes_total(), 2, '', 0.001 );
	}

}
