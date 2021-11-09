<?php

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Subscription_Cart_Tax_Calculation extends Cart_Integration_Test {

	/**
	 * @dataProvider get_subscription_cart_test_data
	 */
	public function test_subscription_cart_tax_calculation( $data ) {
		$this->create_cart_builder_from_provider_data( $data );
		$cart = $this->cart_builder->build();

		$cart->calculate_totals();

		$this->assert_cart_has_correct_tax( $cart, $data );

		foreach ( $cart->recurring_carts as $recurring_cart ) {
			$this->assert_cart_has_correct_recurring_tax( $recurring_cart, $data );
		}
	}

	public function get_subscription_cart_test_data() {
		return [
			'a cart with a simple subscription' => [
				'cart_data' => [
					'expected_cart_totals' => [
						'tax_subtotal' => .73,
						'cart_contents_tax' => .73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => .73,
						'cart_total' => 20.73,
					],
					'expected_recurring_cart_totals' => [
						'tax_subtotal' => .73,
						'cart_contents_tax' => .73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => .73,
						'cart_total' => 20.73,
					],
					'items' => [
						[
							'type' => 'subscription',
							'options' => [
								'price' => 10,
								'sign_up_fee' => 0,
								'trial_length' => 0,
							],
							'quantity' => 1,
							'expected_tax' => [
								'tax_subtotal' => .73,
								'tax_total' => .73,
							],
							'recurring_expected_tax' => [
								'tax_subtotal' => .73,
								'tax_total' => .73,
							],
						]
					],
					'coupons' => [],
					'fees' => []
				]
			],
			'a cart with a subscription with sign up fee' => [
				'cart_data' => [
					'expected_cart_totals' => [
						'tax_subtotal' => 1.45,
						'cart_contents_tax' => 1.45,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => 1.45,
						'cart_total' => 31.45,
					],
					'expected_recurring_cart_totals' => [
						'tax_subtotal' => .73,
						'cart_contents_tax' => .73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => .73,
						'cart_total' => 20.73,
					],
					'items' => [
						[
							'type' => 'subscription',
							'options' => [
								'price' => 10,
								'sign_up_fee' => 10.00,
								'trial_length' => 0,
							],
							'quantity' => 1,
							'expected_tax' => [
								'tax_subtotal' => 1.45,
								'tax_total' => 1.45,
							],
							'recurring_expected_tax' => [
								'tax_subtotal' => .73,
								'tax_total' => .73,
							],
						]
					],
					'coupons' => [],
					'fees' => []
				]
			],
			'a cart with a subscription with a free trial' => [
				'cart_data' => [
					'expected_cart_totals' => [
						'tax_subtotal' => 0.0,
						'cart_contents_tax' => 0.0,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => 0.0,
						'cart_total' => 0.0,
					],
					'expected_recurring_cart_totals' => [
						'tax_subtotal' => .73,
						'cart_contents_tax' => .73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => .73,
						'cart_total' => 20.73,
					],
					'items' => [
						[
							'type' => 'subscription',
							'options' => [
								'price' => 10,
								'sign_up_fee' => 0.0,
								'trial_length' => 1,
							],
							'quantity' => 1,
							'expected_tax' => [
								'tax_subtotal' => 0.0,
								'tax_total' => 0.0,
							],
							'recurring_expected_tax' => [
								'tax_subtotal' => .73,
								'tax_total' => .73,
							],
						]
					],
					'coupons' => [],
					'fees' => []
				]
			],
			'a cart with a subscription with a free trial and sign up fee' => [
				'cart_data' => [
					'expected_cart_totals' => [
						'tax_subtotal' => 0.73,
						'cart_contents_tax' => 0.73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => 0.73,
						'cart_total' => 10.73,
					],
					'expected_recurring_cart_totals' => [
						'tax_subtotal' => 0.73,
						'cart_contents_tax' => 0.73,
						'shipping_tax' => 0.0,
						'fee_tax' => 0.0,
						'total_tax' => 0.73,
						'cart_total' => 20.73,
					],
					'items' => [
						[
							'type' => 'subscription',
							'options' => [
								'price' => 10,
								'sign_up_fee' => 10.0,
								'trial_length' => 1,
							],
							'quantity' => 1,
							'expected_tax' => [
								'tax_subtotal' => 0.73,
								'tax_total' => 0.73,
							],
							'recurring_expected_tax' => [
								'tax_subtotal' => 0.73,
								'tax_total' => 0.73,
							],
						]
					],
					'coupons' => [],
					'fees' => []
				]
			],
		];
	}

	private function assert_cart_has_correct_recurring_tax( $cart, $data  ) {
		$index = 0;
		foreach( $cart->get_cart() as $cart_item ) {
			$this->assert_line_item_has_correct_tax( $data['items'][ $index ]['recurring_expected_tax'], $cart_item );
			$index++;
		}

		foreach( $cart->get_fees() as $fee_key => $fee ) {
			$this->assert_fee_item_has_correct_tax( $data['fees'][ $fee_key ]['recurring_expected_tax'], $fee );
		}

		$this->assert_cart_has_correct_tax_totals( $cart, $data['expected_recurring_cart_totals'] );
	}
}
