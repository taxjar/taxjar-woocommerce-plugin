<?php

namespace TaxJar;

use WP_UnitTestCase;
use TaxJar_Woocommerce_Helper;
use TaxJar_Test_Order_Factory;
use WC_Tax;
use TaxJar_Coupon_Helper;
use \Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Order_Tax_Applicator extends WP_UnitTestCase {

	private $order;
	private $tax_detail_mock;
	private $tax_rate;
	private $shipping_tax_rate;
	private $is_shipping_taxable;

	public function setUp() {
		TaxJar_Woocommerce_Helper::delete_existing_tax_rates();

		$this->order               = TaxJar_Test_Order_Factory::create_zero_tax_order();
		$this->tax_rate            = .10;
		$this->shipping_tax_rate   = .10;
		$this->is_shipping_taxable = false;
	}

	public function test_applying_zero_rate_tax_to_item() {
		$this->tax_rate = 0.0;
		$this->build_tax_detail_mock();
		$this->apply_tax();

		foreach ( $this->order->get_items() as $item ) {
			$this->assertEquals( 0, $item->get_total_tax() );
		}

		$this->assertEquals( 0, $this->order->get_total_tax() );
	}

	public function test_discount_totals_after_tax_application() {
		$this->is_shipping_taxable = true;
		$coupon                    = TaxJar_Coupon_Helper::create_coupon();
		$this->order->apply_coupon( $coupon );
		$this->order->calculate_totals( false );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$this->assertEquals( 10.0, $this->order->get_discount_total() );
		$this->assertEquals( 1.0, $this->order->get_discount_tax() );
	}

	public function test_order_total_after_tax_application() {
		$this->is_shipping_taxable = true;
		$this->order->calculate_totals( false );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$this->assertEquals( 121.00, $this->order->get_total() );
		$this->assertEquals( 100.00, $this->order->get_subtotal() );
	}

	public function test_calculate_totals_method() {
		$this->order->calculate_totals( false );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach ( $this->order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $this->order->get_total_tax() );
	}

	public function test_apply_different_rate_to_same_tax_class_items() {
		$order_options_override = array(
			'products' => array(
				1 => array(
					'type'         => 'simple',
					'price'        => 100,
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
				),
			),
		);

		$this->order           = TaxJar_Test_Order_Factory::create_zero_tax_order( $order_options_override );
		$first_line_item_rate  = 0.1;
		$second_line_item_rate = 0.2;
		$this->tax_detail_mock = $this->createMock( Tax_Details::class );
		$mock_line_item_map    = array();

		$item_index = 0;
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$product_id                = $item->get_product_id();
			$line_item_key             = $product_id . '-' . $item_key;
			$tax_detail_line_item_mock = $this->createMock( Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );

			if ( 0 === $item_index ) {
				$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $first_line_item_rate );
			} else {
				$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $second_line_item_rate );
			}

			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
			$item_index++;
		}
		$this->tax_detail_mock->method( 'get_line_item' )->willReturnMap( $mock_line_item_map );
		$this->tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( false );
		$this->tax_detail_mock->method( 'has_nexus' )->willReturn( true );
		$this->tax_detail_mock->method( 'get_location' )->willReturn(
			[
				'country' => 'US',
				'state'   => 'UT',
				'zip'     => '84651',
				'city'    => 'Payson',
			]
		);

		$this->apply_tax();

		foreach ( $this->order->get_items() as $item ) {
			if ( $item->get_product()->get_sku() === 'SIMPLE2' ) {
				$this->assertEquals( 20, $item->get_total_tax() );
			} else {
				$this->assertEquals( 10, $item->get_total_tax() );
			}
		}
	}

	public function test_applying_different_line_item_and_shipping_rates() {
		$this->is_shipping_taxable = true;
		$this->shipping_tax_rate   = 0.2;
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_line_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach ( $this->order->get_items() as $item ) {
			$this->assertEquals( $expected_line_tax, $item->get_total_tax() );
		}

		$expected_shipping_tax = $this->shipping_tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $this->order->get_shipping_tax() );
	}

	public function test_apply_tax_zero_line_items_and_shipping() {
		$this->is_shipping_taxable = true;
		$factory                   = new TaxJar_Test_Order_Factory();
		$factory->set_customer_id( TaxJar_Test_Order_Factory::$default_options['customer_id'] );
		$factory->set_shipping_address( TaxJar_Test_Order_Factory::$default_options['shipping_address'] );
		$factory->set_billing_address( TaxJar_Test_Order_Factory::$default_options['billing_address'] );
		$factory->add_shipping_item( TaxJar_Test_Order_Factory::$default_options['shipping_method'] );
		$factory->set_payment_method();
		$this->order = $factory->get_order();
		$this->order->calculate_totals( false );

		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_shipping_tax = $this->shipping_tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $this->order->get_shipping_tax() );
	}

	public function test_apply_tax_to_fee_item() {
		$this->order = TaxJar_Test_Order_Factory::create_fee_only_order();
		$this->order->calculate_totals( false );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_fee_details['amount'];

		foreach ( $this->order->get_items( 'fee' ) as $fee ) {
			$this->assertEquals( $expected_tax, $fee->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $this->order->get_total_tax() );
	}

	public function test_apply_tax_to_fee_with_tax_class() {
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		$fee_details_override = array( 'tax_class' => 'clothing-rate-20010' );
		$this->order          = TaxJar_Test_Order_Factory::create_fee_only_order( $fee_details_override );
		$this->order->calculate_totals( false );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_fee_details['amount'];

		foreach ( $this->order->get_items( 'fee' ) as $fee ) {
			$this->assertEquals( $expected_tax, $fee->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $this->order->get_total_tax() );
	}

	public function test_apply_tax_to_line_item() {
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach ( $this->order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $this->order->get_total_tax() );
	}

	public function test_apply_tax_to_line_item_with_tax_class() {
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		$order_options_override = array(
			'products' => array(
				0 => array( 'tax_class' => 'clothing-rate-20010' ),
			),
		);
		$this->order            = TaxJar_Test_Order_Factory::create_zero_tax_order( $order_options_override );
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_options['products'][0]['price'];

		foreach ( $this->order->get_items() as $item ) {
			$this->assertEquals( $expected_tax, $item->get_total_tax() );
		}

		$this->assertEquals( $expected_tax, $this->order->get_total_tax() );
	}

	public function test_apply_shipping_tax() {
		$this->is_shipping_taxable = true;
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$expected_shipping_tax = $this->tax_rate * TaxJar_Test_Order_Factory::$default_options['shipping_method']['cost'];
		$this->assertEquals( $expected_shipping_tax, $this->order->get_shipping_tax() );
	}

	public function test_apply_shipping_tax_with_non_taxable_shipping() {
		$this->build_tax_detail_mock();
		$this->apply_tax();

		$this->assertEquals( 0, $this->order->get_shipping_tax() );
	}

	public function test_apply_tax_with_no_nexus() {
		$this->tax_detail_mock = $this->createMock( Tax_Details::class );
		$this->tax_detail_mock->method( 'has_nexus' )->willReturn( false );

		$this->expectException( Tax_Calculation_Exception::class );
		$this->apply_tax();
	}

	public function test_apply_tax_with_no_line_items() {
		$this->tax_detail_mock = $this->createMock( Tax_Details::class );
		$this->tax_detail_mock->method( 'get_line_item' )->willReturn( false );
		$this->tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( $this->is_shipping_taxable );
		$this->tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $this->shipping_tax_rate );

		$this->expectException( Exception::class );
		$this->apply_tax();
	}

	private function build_tax_detail_mock() {
		$this->tax_detail_mock = $this->createMock( Tax_Details::class );
		$mock_line_items       = $this->build_mock_line_item_map( $this->order, $this->tax_rate );
		$this->tax_detail_mock->method( 'get_line_item' )->willReturnMap( $mock_line_items );
		$this->tax_detail_mock->method( 'is_shipping_taxable' )->willReturn( $this->is_shipping_taxable );
		$this->tax_detail_mock->method( 'get_shipping_tax_rate' )->willReturn( $this->shipping_tax_rate );
		$this->tax_detail_mock->method( 'has_nexus' )->willReturn( true );
		$this->tax_detail_mock->method( 'get_location' )->willReturn(
			[
				'country' => 'US',
				'state'   => 'UT',
				'zip'     => '84651',
				'city'    => 'Payson',
			]
		);
	}

	private function build_mock_line_item_map( $order, $tax_rate ) {
		$mock_line_item_map = array();

		foreach ( $order->get_items() as $item_key => $item ) {
			$product_id                = $item->get_product_id();
			$line_item_key             = $product_id . '-' . $item_key;
			$tax_detail_line_item_mock = $this->createMock( Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );
			$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $tax_rate );
			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
		}

		foreach ( $order->get_items( 'fee' ) as $fee_key => $fee ) {
			$line_item_key             = 'fee-' . $fee_key;
			$tax_detail_line_item_mock = $this->createMock( Tax_Detail_Line_Item::class );
			$tax_detail_line_item_mock->method( 'get_id' )->willReturn( $line_item_key );
			$tax_detail_line_item_mock->method( 'get_tax_rate' )->willReturn( $tax_rate );
			$mock_line_item_map[] = array( $line_item_key, $tax_detail_line_item_mock );
		}

		return $mock_line_item_map;
	}

	private function apply_tax() {
		$tax_applicator = new Order_Tax_Applicator( $this->order );
		$tax_applicator->apply_tax( $this->tax_detail_mock );
	}

}
