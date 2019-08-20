<?php
class TJ_WC_Actions extends WP_UnitTestCase {

	function setUp() {
		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = TaxJar();

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_postcode' => '80111',
			'store_city' => 'Greenwood Village',
		) );

		// We need this to have the calculate_totals() method calculate totals
		if ( ! defined( 'WOOCOMMERCE_CHECKOUT' ) ) {
			define( 'WOOCOMMERCE_CHECKOUT', true );
		}
	}

	function tearDown() {
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
		TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );

		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 5;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.73, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0.36, '', 0.01 );

		if ( method_exists( WC()->cart, 'get_shipping_taxes' ) ) {
			$this->assertEquals( array_values( WC()->cart->get_shipping_taxes() )[0], 0.36, '', 0.01 );
		} else {
			$this->assertEquals( array_values( WC()->cart->shipping_taxes )[0], 0.36, '', 0.01 );
		}

		$this->assertEquals( WC()->cart->get_taxes_total(), 1.09, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.73, '', 0.01 );
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_with_exempt_shipping() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );

		// CA shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'CA',
			'zip' => '90404',
			'city' => 'Santa Monica',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );

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

	function test_correct_taxes_from_taxable_shipping_to_exempt_shipping() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		// NJ shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NJ',
			'zip' => '07306',
			'city' => 'Jersey City',
		) );

		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '50',
			'sku' => 'EXEMPT',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '100',
			'sku' => 'TAXABLE',
			'tax_class' => '',
		) )->get_id();

		$exempt_product_item_key = WC()->cart->add_to_cart( $exempt_product );
		$taxable_product_item_key = WC()->cart->add_to_cart( $taxable_product );

		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 6.63, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0.66, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 7.29, '', 0.01 );

		// Remove taxable product from cart
		WC()->cart->remove_cart_item( $taxable_product_item_key );

		// Recalculate totals
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0, '', 0.01 );

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_with_local_pickup() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10001',
			'city' => 'New York City',
		) );

		WC()->cart->add_to_cart( $product );

		// Set local pickup shipping method and ship to CO address instead
		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
		WC()->session->set( 'chosen_shipping_methods', array( 'local_pickup' ) );
		update_option( 'woocommerce_tax_based_on', 'base' );

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.73, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.73, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.73, '', 0.01 );
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
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

		$this->assertEquals( WC()->cart->tax_total, 4.36, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 4.36, '', 0.01 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 3.63, '', 0.01 );
			}

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0.73, '', 0.01 );
			}
		}

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 64.36, '', 0.01 );
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

		$this->assertEquals( WC()->cart->tax_total, 84.15, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 84.15, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 1019.15, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 43.65, '', 0.01 );
			}

			if ( 'SIMPLE2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 40.5, '', 0.01 );
			}
		}
	}

	function test_correct_taxes_for_exempt_multiple_products_and_shipping() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		// NJ shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NJ',
			'zip' => '07306',
			'city' => 'Jersey City',
		) );

		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '50',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product2 = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '75',
			'sku' => 'EXEMPT2',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->add_to_cart( $exempt_product2 );


		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 10;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 125 + 10, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'EXEMPT2' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_correct_taxes_for_duplicate_line_items() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );
		WC()->cart->add_to_cart( $product, 1, 0, [], [ 'duplicate' => true ] );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1.46, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.46, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 21.46, '', 0.01 );
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

	function test_correct_taxes_for_categorized_exempt_products() {
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'tax_status' => 'none',
			'tax_class' => 'clothing-rate-20010',
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
			'store_postcode' => '10001',
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
			'store_postcode' => '10001',
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

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 150 + 50 + 13.31, '', 0.01 );
		}

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

	function test_correct_taxes_for_product_exemption_threshold_ma() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'SC',
			'store_postcode' => '29401',
			'store_city' => 'Charleston',
		) );

		// MA shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'MA',
			'zip' => '02127',
			'city' => 'Boston',
		) );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '205', // Over $110 threshold
			'sku' => 'EXEMPTOVER1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '78',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();

		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 1.88, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 1.88, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 283 + 1.88, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 1.88, '', 0.01 );
			}
		}
	}

	function test_correct_taxes_for_product_exemption_threshold_ma_with_discount() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'SC',
			'store_postcode' => '29401',
			'store_city' => 'Charleston',
		) );

		// MA shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'MA',
			'zip' => '02127',
			'city' => 'Boston',
		) );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '205', // Over $110 threshold
			'sku' => 'EXEMPTOVER1',
			'tax_class' => 'clothing-rate-20010',
		) )->get_id();
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '78',
			'sku' => 'EXEMPT1',
			'tax_class' => 'clothing-rate-20010',
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

		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $exempt_product );
		WC()->cart->add_discount( $coupon );
		WC()->cart->calculate_totals();

		// Woo 3.2+ allocates fixed discounts evenly across line items
		// Woo 2.6+ allocates fixed discounts proportionately across line items
		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->tax_total, 1.56, '', 0.01 );
			$this->assertEquals( WC()->cart->get_taxes_total(), 1.56, '', 0.01 );
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 283 - 10 + 1.56, '', 0.01 );
		} else {
			$this->assertEquals( WC()->cart->tax_total, 1.42, '', 0.01 );
			$this->assertEquals( WC()->cart->get_taxes_total(), 1.42, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'EXEMPT1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 0, '', 0.01 );
			}

			if ( 'EXEMPTOVER1' == $sku ) {
				if ( version_compare( WC()->version, '3.2', '>=' ) ) {
					$this->assertEquals( $item['line_tax'], 1.56, '', 0.01 );
				} else {
					$this->assertEquals( $item['line_tax'], 1.42, '', 0.01 );
				}
			}
		}
	}

	function test_correct_taxes_for_product_exemption_threshold_reduced_rates() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_postcode' => '10118',
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

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 150 + 50 + 14.75, '', 0.01 );
		}

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

	function test_correct_taxes_for_product_exemption_threshold_reduced_rates_and_other_products() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NY',
			'store_postcode' => '10118',
			'store_city' => 'New York City',
		) );

		// NY shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NY',
			'zip' => '10541',
			'city' => 'Mahopac',
		) );

		$regular_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '25',
			'sku' => 'SIMPLE1',
			'tax_class' => '',
		) )->get_id();
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

		WC()->cart->add_to_cart( $regular_product, 2 );
		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->add_to_cart( $reduced_product, 2 );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 18.94, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 18.94, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 150 + 50 + 50 + 18.94, '', 0.01 );
		}

		foreach ( WC()->cart->get_cart() as $item_key => $item ) {
			$product = $item['data'];
			$sku = $product->get_sku();

			if ( 'SIMPLE1' == $sku ) {
				$this->assertEquals( $item['line_tax'], 4.19, '', 0.01 );
			}

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

		$this->assertEquals( WC()->cart->tax_total, 4.35, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 4.35, '', 0.01 );

		if ( version_compare( WC()->version, '3.2', '>=' ) ) {
			$this->assertEquals( WC()->cart->get_total( 'amount' ), 64.35, '', 0.01 );
		}
	}

	function test_correct_taxes_for_intrastate_origin_state() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'TX',
			'store_postcode' => '76082',
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
			'store_postcode' => '27545',
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

	function test_correct_taxes_for_rooftop_address() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'NC',
			'store_postcode' => '27601',
			'store_city' => 'Raleigh',
			'store_street' => '11 W Jones St',
		) );

		// NC shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'state' => 'NC',
			'zip' => '28036',
			'city' => 'Davidson',
		) );

		WC()->customer->set_shipping_address( '10876 Tailwater St.' );

		$taxable_product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $taxable_product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.7, '', 0.001 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.7, '', 0.001 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.7, '', 0.001 );
		}

		WC()->customer->set_shipping_address( '123 Test St.' );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.73, '', 0.001 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 0.73, '', 0.001 );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$this->assertEquals( $item['line_tax'], 0.73, '', 0.001 );
		}
	}

	function test_correct_taxes_for_canada() {
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'CA',
			'store_state' => 'BC',
			'store_postcode' => 'V6G 3E2',
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
			'store_postcode' => '2000',
			'store_city' => 'Sydney',
		) );

		// AU shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'AU',
			'state' => 'VIC',
			'zip' => '3002',
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
			'store_postcode' => '75008',
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
			'store_postcode' => 'SW1A 1AA',
			'store_city' => 'London',
		) );

		// UK shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'GB',
			'state' => '',
			'zip' => 'SW1A1AA',
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
			'store_postcode' => '104 47',
			'store_city' => 'Athens',
		) );

		// Greece shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer( array(
			'country' => 'GR',
			'state' => '',
			'zip' => '10431',
			'city' => 'Athens',
		) );

		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );
		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 2.4, '', 0.01 );
		$this->assertEquals( WC()->cart->get_taxes_total(), 2.4, '', 0.01 );
	}

	function test_is_postal_code_valid() {
	    $postal_array = array(
            'US' => array(
              '60515630-968-2144' => false,
              '' => false,
              '1' => false,
              '12' => false,
              '123' => false,
              '1234' => false,
              '12345-' => false,
              '12345-1' => false,
              '23451-123' => false,
              '12345-12345' => false,
              'A1111' => false,
              'ACDES' => false,
              '12345' => true,
              '12345-1234' => true
            ),
            'CA' => array(
                '60515630-968-2144' => false,
                '' => true,
                '1' => false,
                '12' => false,
                '12345-1' => false,
                'A1111' => false,
                'ACDES' => false,
                '12345' => false,
                '12345-1234' => false,
                'P1P 0G0' => true,
                'J0T 0P2' => true
            ),
            'UK' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => false,
                '12345-1234' => false,
                'P1P 0G0' => false,
                'EC1A 1BB' => true,
                'CR2 6XH' => true
            ),
            'FR' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
                '12 345' => true
            ),
            'IT' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'DE' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'NL' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => false,
                '12345-1234' => false,
                'P1P 0G0' => false,
                '1234 AB' => true,
                '1234AB' => true
            ),
            'ES' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'DK' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => false,
                '1234' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'SE' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => true,
                '123 45' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'BE' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => false,
                '1234' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'IN' => array(
                '60515630-968-2144' => false,
                '' => true,
                '123456' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            ),
            'AU' => array(
                '60515630-968-2144' => false,
                '' => true,
                '12345' => false,
                '1234' => true,
                '12345-1234' => false,
                'P1P 0G0' => false,
            )
        );

	    foreach ( $postal_array as $country => $codes ) {
	        foreach ( $codes as $code => $expected ) {
                $this->assertEquals( $this->tj->is_postal_code_valid( $country, null, $code ), $expected );
            }
        }
    }

	function test_vat_exempt_customer_with_shipping() {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 5 );
		WC()->customer->set_is_vat_exempt( true );
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();

		WC()->cart->add_to_cart( $product );

		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate' ) );
		WC()->shipping->shipping_total = 5;

		WC()->cart->calculate_totals();

		$this->assertEquals( WC()->cart->tax_total, 0.00, '', 0.01 );
		$this->assertEquals( WC()->cart->shipping_tax_total, 0.00, '', 0.01 );

		WC()->session->set( 'chosen_shipping_methods', array() );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
	}

	function test_order_level_exemption_on_cart_calculation() {
		$product = TaxJar_Product_Helper::create_product( 'simple' )->get_id();
		WC()->cart->add_to_cart( $product );

		add_filter( 'taxjar_cart_exemption_type', function ( $cart ) {
			return 'wholesale';
		} );

		WC()->cart->calculate_totals();

		remove_all_filters( 'taxjar_cart_exemption_type' );

		$this->assertEquals( 0, WC()->cart->get_taxes_total() );
  }
  
	function test_tax_lookup_state_with_space() {
		$location = array(
			'to_country' => 'GB',
			'to_state' => 'West Sussex',
			'to_zip' => 'BN15 1S2',
			'to_city' => 'London'
		);
		$rate_id = $this->tj->create_or_update_tax_rate( $location, 10 );
		$found_rate_id = $this->tj->create_or_update_tax_rate( $location, 10 );

		$this->assertEquals( $rate_id, $found_rate_id );
	}
}
