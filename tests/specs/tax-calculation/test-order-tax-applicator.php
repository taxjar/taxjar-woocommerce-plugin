<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Order_Tax_Applicator extends WP_UnitTestCase {

	public function setUp() {
		TaxJar_Woocommerce_Helper::delete_existing_tax_rates();
	}

	public function test_applying_zero_rate_tax_to_item() {
		$tax_rate = 0.0;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( 0, $item->get_total_tax() );
		}

		$this->assertEquals( 0, $order->get_total_tax() );
	}

	public function test_discount_totals_after_tax_application() {
		$tax_rate = .10;
		$shipping_tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$coupon = TaxJar_Coupon_Helper::create_coupon();
		$order->apply_coupon( $coupon );
		$order->calculate_totals();

		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( true );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $shipping_tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$this->assertEquals( 10.0, $order->get_discount_total() );
		$this->assertEquals( 1.0, $order->get_discount_tax() );
	}

	public function test_order_total_after_tax_application() {
		$tax_rate = .10;
		$shipping_tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$order->calculate_totals();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( true );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $shipping_tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$this->assertEquals( 121.00, $order->get_total() );
		$this->assertEquals( 100.00, $order->get_subtotal() );
	}

	public function test_calculate_totals_method() {
		$tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$order->calculate_totals();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $order->get_total_tax() );
	}

	public function test_apply_different_rate_to_same_tax_class_items() {
		$item_price = 100;
		$order_options_override = array(
			'products'         => array(
				1 => array(
					'type'         => 'simple',
					'price'        => $item_price,
					'quantity'     => 1,
					'name'         => 'Dummy Product 2',
					'sku'          => 'SIMPLE2',
					'manage_stock' => false,
					'tax_status'   => 'taxable',
					'downloadable' => false,
					'virtual'      => false,
					'stock_status' => 'instock',
					'weight'       => '1.1',
					'tax_class'    => '',
					'tax_total'    => array( 0 ),
					'tax_subtotal' => array( 0 ),
				)
			)
		);
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order( $order_options_override );

		$first_line_item_rate = 0.1;
		$second_line_item_rate = 0.2;

		$tax_detail_mock = $this->createMock( TaxJar_Tax_Details::class );
		$mock_line_item_map = array();

		$item_index = 0;
		foreach( $order->get_items() as $item_key => $item ) {
			$product_id    = $item->get_product_id();
			$line_item_key = $product_id . '-' . $item_key;
			$tax_detail_line_item_mock = $this->createMock( TaxJar_Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );

			if ( $item_index === 0 ) {
				$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $first_line_item_rate );
			} else {
				$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $second_line_item_rate );
			}

			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
			$item_index++;
		}
		$tax_detail_mock->method( 'get_line_item' )->willReturnMap( $mock_line_item_map );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		foreach( $order->get_items() as $item ) {
			if ( $item->get_product()->get_sku() === 'SIMPLE2' ) {
				$this->assertEquals( 20, $item->get_total_tax() );
			} else {
				$this->assertEquals( 10, $item->get_total_tax() );
			}
		}
	}

	public function test_applying_different_line_item_and_shipping_rates() {
		$line_item_tax_rate = 0.1;
		$shipping_tax_rate = 0.2;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $line_item_tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( true );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $shipping_tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_line_tax = $line_item_tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( $expected_line_tax, $item->get_total_tax() );
		}

		$expected_shipping_tax = $shipping_tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $order->get_shipping_tax() );
	}

	public function test_apply_tax_zero_line_items_and_shipping() {
		$tax_rate = .10;
		$factory = new TaxJar_Test_Order_Factory();
		$factory->set_customer_id( TaxJar_Test_Order_Factory::$default_options['customer_id'] );
		$factory->set_shipping_address( TaxJar_Test_Order_Factory::$default_options['shipping_address'] );
		$factory->set_billing_address( TaxJar_Test_Order_Factory::$default_options['billing_address'] );
		$factory->add_shipping_item( TaxJar_Test_Order_Factory::$default_options['shipping_method'] );
		$factory->set_payment_method();
		$order = $factory->get_order();
		$order->calculate_totals();

		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( true );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_shipping_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $order->get_shipping_tax() );
	}

	public function test_apply_tax_to_fee_item() {
		$tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_fee_only_order();
		$order->calculate_totals();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_fee_details['amount'];

		foreach( $order->get_items( 'fee' ) as $fee ) {
			$this->assertEquals( $expected_tax, $fee->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $order->get_total_tax() );
	}

	public function test_apply_tax_to_fee_with_tax_class() {
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		$tax_rate = .10;
		$fee_details_override = array( 'tax_class' => 'clothing-rate-20010' );
		$order = TaxJar_Test_Order_Factory::create_fee_only_order( $fee_details_override );
		$order->calculate_totals();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_fee_details['amount'];

		foreach( $order->get_items( 'fee' ) as $fee ) {
			$this->assertEquals( $expected_tax, $fee->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $order->get_total_tax() );
	}

	public function test_apply_tax_to_line_item() {
		$tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $order->get_total_tax() );
	}

	public function test_apply_tax_to_line_item_with_tax_class() {
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		$tax_rate = .10;
		$order_options_override = array(
			'products' => array(
				0 => array( 'tax_class' => 'clothing-rate-20010' )
			)
		);
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order( $order_options_override );
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach( $order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $order->get_total_tax() );
	}

	public function test_apply_shipping_tax() {
		$tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( true );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$expected_shipping_tax = $tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $order->get_shipping_tax() );
	}

	public function test_apply_shipping_tax_with_non_taxable_shipping() {
		$tax_rate = .10;
		$order = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$tax_detail_mock = $this->build_tax_detail_mock( $order, $tax_rate );
		$tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $tax_rate );
		$tax_applicator = new TaxJar_Order_Tax_Applicator( $order );
		$tax_applicator->apply_tax( $tax_detail_mock );

		$this->assertEquals( 0, $order->get_shipping_tax() );
	}

	private function build_tax_detail_mock( $order, $tax_rate ) {
		$tax_detail_mock = $this->createMock( TaxJar_Tax_Details::class );
		$mock_line_items = $this->build_mock_line_item_map( $order, $tax_rate );
		$tax_detail_mock->method( 'get_line_item' )->willReturnMap( $mock_line_items );
		return $tax_detail_mock;
	}

	private function build_mock_line_item_map( $order, $tax_rate ) {
		$mock_line_item_map = array();

		foreach( $order->get_items() as $item_key => $item ) {
			$product_id    = $item->get_product_id();
			$line_item_key = $product_id . '-' . $item_key;
			$tax_detail_line_item_mock = $this->createMock( TaxJar_Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );
			$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $tax_rate );
			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
		}

		foreach( $order->get_items( 'fee' ) as $fee_key => $fee ) {
			$line_item_key = 'fee-' . $fee_key;
			$tax_detail_line_item_mock = $this->createMock( TaxJar_Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );
			$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $tax_rate );
			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
		}

		return $mock_line_item_map;
	}

}