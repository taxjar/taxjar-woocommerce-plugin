<?php

namespace TaxJar;

use TaxJar\Tests\Framework\Cart_Builder;
use TaxJar_Coupon_Helper;
use TaxJar_Product_Helper;
use TaxJar_Shipping_Helper;
use WC_Cart_Totals;
use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Cart_Tax_Applicator extends WP_UnitTestCase {

	/**
	 * @dataProvider provide_cart_data
	 *
	 * @param $expected_values
	 * @param $items
	 * @param $coupons
	 *
	 * @throws Tax_Calculation_Exception
	 */
	public function test_correct_tax_values( $cart_data, $items, $coupons = [], $fees = [] ) {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_details_stub->method( 'has_nexus' )->willReturn( true );
		$tax_details_stub->method( 'get_shipping_tax_rate' )->willReturn( $cart_data['shipping_tax_rate'] );
		$tax_details_stub->method( 'get_rate' )->willReturn( $cart_data['average_rate'] ?? 0.0 );
		$cart_builder = Cart_Builder::a_cart();
		$item_stubs = [];

		foreach( $items as $item ) {
			$product = TaxJar_Product_Helper::create_product( $item['type'], $item['options'] );
			$cart_builder = $cart_builder->with_product( $product->get_id(), $item['quantity'] );
			$item_stubs[ $product->get_id() ] = $this->create_tax_detail_item_stub( $item['tax_rate'], $item['expected_tax_total'] );
		}

		foreach( $coupons as $coupon ) {
			$cart_builder = $cart_builder->with_coupon( TaxJar_Coupon_Helper::create_coupon( $coupon )->get_code() );
		}

		foreach( $fees as $fee_id => $fee ) {
			$cart_builder = $cart_builder->with_fee( $fee['data'] );
			$item_stubs[ $fee_id ] = $this->create_tax_detail_item_stub( $fee['tax_rate'], $fee['expected_tax'] );
		}

		$cart = $cart_builder->build();
		new WC_Cart_Totals( $cart );

		$tax_details_stub->method( 'get_line_item' )->willReturnCallback( function( $key ) use ( $item_stubs ) {
			if ( isset( $item_stubs[ $key ] ) ) {
				return $item_stubs[ $key ];
			}
			return $item_stubs[ explode( '-', $key )[0] ];
		} );

		$cart_tax_applicator = new Cart_Tax_Applicator( $cart );

		$cart_tax_applicator->apply_tax( $tax_details_stub );

		$index = 0;
		foreach( $cart->get_cart() as $cart_item ) {
			$this->assertEquals( $items[ $index ]['expected_tax_subtotal'], $cart_item['line_subtotal_tax'] );
			$this->assertEquals( $items[ $index ]['expected_tax_subtotal'], $cart_item['line_tax_data']['subtotal'][0] );
			$this->assertEquals( $items[ $index ]['expected_tax_total'], $cart_item['line_tax'] );
			$this->assertEquals( $items[ $index ]['expected_tax_total'], $cart_item['line_tax_data']['total'][0] );
			$index++;
		}

		foreach( $cart->get_fees() as $fee_key => $fee ) {
			$this->assertEquals( $fees[ $fee_key ]['expected_tax'], $fee->tax );
			$this->assertEquals( $fees[ $fee_key ]['expected_tax'], $fee->tax_data[0] );
		}

		$this->assertEquals( $cart_data['tax_subtotal'], $cart->get_subtotal_tax() );
		$this->assertEquals( $cart_data['cart_contents_tax'], $cart->get_cart_contents_tax() );

		if ( isset( $cart->get_cart_contents_taxes()[0] ) ) {
			$this->assertEquals( $cart_data['cart_contents_tax'], $cart->get_cart_contents_taxes()[0] );
		}

		$this->assertEquals( $cart_data['shipping_tax'], $cart->get_shipping_tax() );
		$this->assertEquals( $cart_data['shipping_tax'], $cart->get_shipping_taxes()[0] );
		$this->assertEquals( $cart_data['fee_tax'], $cart->get_fee_tax() );

		if ( isset( $cart->get_fee_taxes()[0] ) ) {
			$this->assertEquals( $cart_data['fee_tax'], $cart->get_fee_taxes()[0] );
		}

		$this->assertEquals( $cart_data['total_tax'], $cart->get_total_tax() );
		$this->assertEquals( $cart_data['cart_total'], $cart->get_total( 'amount' ) );
	}

	public function provide_cart_data(): array {
		return [
			'cart with a single simple product' => [
				[
					'tax_subtotal' => 1.0,
					'cart_contents_tax' => 1.0,
					'shipping_tax' => 0.0,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 0.0,
					'total_tax' => 1.0,
					'cart_total' => 21.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => 1.0
					],
				],
			],
			'cart with a two items with different rates' => [
				[
					'tax_subtotal' => 3.0,
					'cart_contents_tax' => 3.0,
					'shipping_tax' => 0.0,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 0.0,
					'total_tax' => 3.00,
					'cart_total' => 33.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => 1.0
					],
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .2,
						'expected_tax_subtotal' => 2.0,
						'expected_tax_total' => 2.0
					],
				],
			],
			'cart with a single simple item and coupon' => [
				[
					'tax_subtotal' => 1.0,
					'cart_contents_tax' => .9,
					'shipping_tax' => 0.0,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 0.0,
					'total_tax' => 0.90,
					'cart_total' => 19.90,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => .9
					],
				],
				[
					[
						'amount' => '1'
					]
				]
			],
			'cart with a single simple item and shipping rate' => [
				[
					'tax_subtotal' => 1.0,
					'cart_contents_tax' => 1.0,
					'shipping_tax' => 1.0,
					'shipping_tax_rate' => .1,
					'fee_tax' => 0.0,
					'total_tax' => 2.00,
					'cart_total' => 22.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => 1.0
					],
				],
			],
			'cart with a single simple item and fee' => [
				[
					'tax_subtotal' => 1.0,
					'cart_contents_tax' => 1.0,
					'shipping_tax' => 0.0,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 2.0,
					'total_tax' => 3.00,
					'cart_total' => 33.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => 1.0
					],
				],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .2,
						'expected_tax' => 2.0
					],
				]
			],
			'cart with a single simple item and two fees with different tax rates' => [
				[
					'tax_subtotal' => 1.0,
					'cart_contents_tax' => 1.0,
					'shipping_tax' => 0.0,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 3.0,
					'total_tax' => 4.00,
					'cart_total' => 44.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 1.0,
						'expected_tax_total' => 1.0
					],
				],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .2,
						'expected_tax' => 2.0
					],
					'test-fee-2' => [
						'data' => [
							'name' => 'test fee 2',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .1,
						'expected_tax' => 1.0
					]
				]
			],
			'cart with a single item, coupon, fee and taxable shipping' => [
				[
					'tax_subtotal' => 10.00,
					'cart_contents_tax' => 9.0,
					'shipping_tax' => 2.00,
					'shipping_tax_rate' => 0.2,
					'fee_tax' => 3.00,
					'total_tax' => 14.00,
					'cart_total' => 124.00,
				],
				[
					[
						'type' => 'simple',
						'options' => [
							'price' => 100
						],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 10.00,
						'expected_tax_total' => 9.00
					],
				],
				[
					[
						'amount' => '10'
					]
				],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .3,
						'expected_tax' => 3.0
					],
				]
			],
			'cart with only a fee' => [
				[
					'tax_subtotal' => 0.00,
					'cart_contents_tax' => 0.00,
					'shipping_tax' => 0.00,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => 1.00,
					'total_tax' => 1.00,
					'cart_total' => 11.00,
				],
				[],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => 0.1,
						'expected_tax' => 1.0
					],
				]
			],
			'cart with item and negative taxable fee' => [
				[
					'tax_subtotal' => 10.00,
					'cart_contents_tax' => 10.00,
					'shipping_tax' => 0.00,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => -1.00,
					'total_tax' => 9.00,
					'cart_total' => 109.00,
					'average_rate' => 0.1
				],
				[
					[
						'type' => 'simple',
						'options' => [
							'price' => 100
						],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 10.00,
						'expected_tax_total' => 10.00
					],
				],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => -10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .3,
						'expected_tax' => -1.0
					],
				]
			],
			'cart with item and negative taxable fee that exceeds subtotals' => [
				[
					'tax_subtotal' => 10.00,
					'cart_contents_tax' => 10.00,
					'shipping_tax' => 0.00,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => -10.00,
					'total_tax' => 0.00,
					'cart_total' => 0.00,
					'average_rate' => 0.1
				],
				[
					[
						'type' => 'simple',
						'options' => [
							'price' => 100
						],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 10.00,
						'expected_tax_total' => 10.00
					],
				],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => -200,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .3,
						'expected_tax' => -10.00
					],
				]
			],
			'cart with item and multiple positive and negative fees' => [
				[
					'tax_subtotal' => 10.00,
					'cart_contents_tax' => 10.00,
					'shipping_tax' => 0.00,
					'shipping_tax_rate' => 0.0,
					'fee_tax' => -10.00,
					'total_tax' => 0.00,
					'cart_total' => 0.00,
					'average_rate' => 0.1
				],
				[
					[
						'type' => 'simple',
						'options' => [
							'price' => 100
						],
						'quantity' => 1,
						'tax_rate' => .1,
						'expected_tax_subtotal' => 10.00,
						'expected_tax_total' => 10.00
					],
				],
				[],
				[
					'test-fee-1' => [
						'data' => [
							'name' => 'test fee 1',
							'amount' => 10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .1,
						'expected_tax' => 1.00
					],
					'test-fee-2' => [
						'data' => [
							'name' => 'test fee 2',
							'amount' => -10,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .1,
						'expected_tax' => -1.00
					],
					'test-fee-3' => [
						'data' => [
							'name' => 'test fee 3',
							'amount' => -200,
							'taxable' => true,
							'tax_class' => ''
						],
						'tax_rate' => .3,
						'expected_tax' => -10.00
					],
					'test-fee-4' => [
						'data' => [
							'name' => 'test fee 4',
							'amount' => 10,
							'taxable' => false,
							'tax_class' => ''
						],
						'tax_rate' => 0.00,
						'expected_tax' => 0
					],
				]
			],
		];
	}

	private function create_tax_detail_item_stub( $rate, $tax_collectable ) {
		$tax_detail_item_stub = $this->createMock( Tax_Detail_Line_Item::class );
		$tax_detail_item_stub->method( 'get_tax_rate' )->willReturn( $rate );
		$tax_detail_item_stub->method( 'get_tax_collectable' )->willReturn( floatval( $tax_collectable ) );
		return $tax_detail_item_stub;
	}

}
