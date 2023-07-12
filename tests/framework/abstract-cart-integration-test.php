<?php

namespace TaxJar;

use Automattic\Jetpack\Constants;
use TaxJar\Tests\Framework\Cart_Builder;
use TaxJar_Coupon_Helper;
use TaxJar_Product_Helper;
use TaxJar_Shipping_Helper;
use WP_UnitTestCase;

abstract class Cart_Integration_Test extends WP_UnitTestCase {

	protected $cart_builder;

	public function setUp(): void {
		Constants::set_constant( 'WOOCOMMERCE_CART', true );
		parent::setUp();
	}

	public function tearDown(): void {
		Constants::clear_single_constant( 'WOOCOMMERCE_CART' );
		parent::tearDown();
	}

	protected function create_cart_builder_from_provider_data( $data ) {
		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );
		$this->cart_builder = Cart_Builder::a_cart();
		$this->add_items_to_cart_builder( $data['items'] );
		$this->add_coupons_to_cart_builder( $data['coupons'] );
		$this->add_fees_to_cart_builder( $data['fees'] );
	}

	protected function add_items_to_cart_builder( $items ) {
		foreach( $items as $item ) {
			$product = TaxJar_Product_Helper::create_product( $item['type'], $item['options'] );
			$this->cart_builder = $this->cart_builder->with_product( $product->get_id(), $item['quantity'] );
		}
	}

	protected function add_coupons_to_cart_builder( $coupons ) {
		foreach( $coupons as $coupon ) {
			$this->cart_builder = $this->cart_builder->with_coupon( TaxJar_Coupon_Helper::create_coupon( $coupon )->get_code() );
		}
	}

	protected function add_fees_to_cart_builder( $fees ) {
		foreach( $fees as $fee ) {
			$this->cart_builder = $this->cart_builder->with_fee( $fee['data'] );
		}
	}

	protected function assert_cart_has_correct_tax( $cart, $data  ) {
		$index = 0;
		foreach( $cart->get_cart() as $cart_item ) {
			$this->assert_line_item_has_correct_tax( $data['items'][ $index ]['expected_tax'], $cart_item );
			$index++;
		}

		foreach( $cart->get_fees() as $fee_key => $fee ) {
			$this->assert_fee_item_has_correct_tax( $data['fees'][ $fee_key ]['expected_tax'], $fee );
		}

		$this->assert_cart_has_correct_tax_totals( $cart, $data['expected_cart_totals'] );
	}

	protected function assert_cart_has_correct_tax_totals( $cart, $expected_totals ) {
		$this->assertEquals( $expected_totals['tax_subtotal'], $cart->get_subtotal_tax() );
		$this->assertEquals( $expected_totals['cart_contents_tax'], $cart->get_cart_contents_tax() );

		if ( isset( $cart->get_cart_contents_taxes()[0] ) ) {
			$this->assertEquals( $expected_totals['cart_contents_tax'], $cart->get_cart_contents_taxes()[0] );
		}

		$this->assertEquals( $expected_totals['shipping_tax'], $cart->get_shipping_tax() );
		$shipping_taxes = $cart->get_shipping_taxes();
		$this->assertEquals( $expected_totals['shipping_tax'], reset( $shipping_taxes ) );
		$this->assertEquals( $expected_totals['fee_tax'], $cart->get_fee_tax() );

		if ( isset( $cart->get_fee_taxes()[0] ) ) {
			$fee_taxes = $cart->get_fee_taxes();
			$this->assertEquals( $expected_totals['fee_tax'], reset( $fee_taxes ) );
		}

		$this->assertEquals( $expected_totals['total_tax'], $cart->get_total_tax() );
		$this->assertEquals( $expected_totals['cart_total'], $cart->get_total( 'amount' ) );
	}

	protected function assert_line_item_has_correct_tax( $given_item_data, $cart_item ) {
		$this->assertEquals( $given_item_data['tax_subtotal'], $cart_item['line_subtotal_tax'] );
		$this->assertEquals( $given_item_data['tax_subtotal'], reset( $cart_item['line_tax_data']['subtotal'] ) );
		$this->assertEquals( $given_item_data['tax_total'], $cart_item['line_tax'] );
		$this->assertEquals( $given_item_data['tax_total'], reset( $cart_item['line_tax_data']['total'] ) );
	}

	protected function assert_fee_item_has_correct_tax( $expected_tax, $fee_item ) {
		$this->assertEquals( $expected_tax, $fee_item->tax );
		$this->assertEquals( $expected_tax, reset($fee_item->tax_data ) );
	}
}
