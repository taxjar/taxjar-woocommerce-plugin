<?php

namespace TaxJar\Tests;

use Exception;
use TaxJar\Cart_Tax_Request_Body_Builder;
use TaxJar\Tests\Framework\Cart_Builder;
use TaxJar_Coupon_Helper;
use TaxJar_Product_Helper;
use WC_Cart_Totals;
use WC_Tax;
use WP_UnitTestCase;

class Test_Cart_Tax_Request_Body_Builder extends WP_UnitTestCase {

	private $test_exemption_type;

	public function tearDown() {
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		remove_filter( 'taxjar_cart_exemption_type', array( $this, 'add_test_exemption_type' ) );
	}

	/**
	 * @dataProvider provide_tax_basis_address
	 */
	public function test_correct_to_address_by_tax_basis( string $tax_basis, array $expected_address ) {
		update_option( 'woocommerce_tax_based_on', $tax_basis );
		$cart = Cart_Builder::a_cart()->build();
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		$this->assertEquals( $expected_address['street'], $tax_request_body->get_to_street() );
		$this->assertEquals( $expected_address['city'], $tax_request_body->get_to_city() );
		$this->assertEquals( $expected_address['state'], $tax_request_body->get_to_state() );
		$this->assertEquals( $expected_address['zip'], $tax_request_body->get_to_zip() );
		$this->assertEquals( $expected_address['country'], $tax_request_body->get_to_country() );
	}

	public function provide_tax_basis_address(): array {
		return [
			'shipping tax basis' => [
				'shipping',
				[
					'street' => '123 main st',
					'city' => 'Payson',
					'state' => 'UT',
					'zip' => '84651',
					'country' => 'US'
				]
			],
			'billing tax basis' => [
				'billing',
				[
					'street' => '123 state st',
					'city' => 'Denver',
					'state' => 'CO',
					'zip' => '80014',
					'country' => 'US'
				]
			],
			'base tax basis' => [
				'base',
				[
					'street' => '6060 S Quebec St',
					'city' => 'Greenwood Village',
					'state' => 'CO',
					'zip' => '80111',
					'country' => 'US'
				]
			],
		];
	}

	public function test_shipping_amount() {
		$cart = Cart_Builder::a_cart()->with_shipping_total( '20' )->build();
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		$this->assertEquals( '20', $tax_request_body->get_shipping_amount() );
	}

	/**
	 * @dataProvider provide_customer_id
	 * @param $customer_id
	 */
	public function test_customer_id( $customer_id ) {
		$cart = Cart_Builder::a_cart()->with_customer_id( $customer_id )->build();
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		$this->assertEquals( $customer_id, $tax_request_body->get_customer_id() );
	}

	public function provide_customer_id(): array {
		return [
			'guest checkout' => [0],
			'logged in user' => [5]
		];
	}

	/**
	 * @dataProvider provide_exemption_type
	 * @param $exemption_type
	 */
	public function test_exemption_type( $exemption_type ) {
		$this->set_test_exemption_type( $exemption_type );
		$cart = Cart_Builder::a_cart()->build();
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		$this->assertEquals( $exemption_type, $tax_request_body->get_exemption_type() );
	}

	private function set_test_exemption_type( $exemption_type ) {
		if ( $exemption_type ) {
			$this->test_exemption_type = $exemption_type;
			add_filter( 'taxjar_cart_exemption_type', array( $this, 'add_test_exemption_type' ) );
		}
	}

	public function provide_exemption_type(): array {
		return [
			'no filter applied' => [''],
			'exemption type filter applied' => ['test_type']
		];
	}

	public function add_test_exemption_type( $exemption_type ) {
		return $this->test_exemption_type;
	}

	/**
	 * @dataProvider provide_cart_data
	 *
	 * @param array $products
	 * @param array $coupons
	 */
	public function test_line_item( array $products, array $coupons = []) {
		$cart = Cart_Builder::a_cart();
		foreach( $products as $product ) {
			$cart = $cart->with_product( $product['product']->get_id(), $product['quantity'] );
		}
		foreach( $coupons as $coupon ) {
			$cart->with_coupon( $coupon->get_code() );
		}
		$cart = $cart->build();
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		foreach( $products as $product ) {
			$item = $this->get_item_from_tax_request_body( $tax_request_body, $product['product']->get_id() );
			$this->assertEquals( $product['quantity'], $item['quantity'] );
			$this->assertEquals( $product['expected_values']['product_tax_code'], $item['product_tax_code'] );
			$this->assertEquals( $product['expected_values']['unit_price'], $item['unit_price'] );
			$this->assertEquals( $product['expected_values']['discount'], $item['discount'] );
		}
	}

	private function get_item_from_tax_request_body( $tax_request_body, $product_id ) {
		foreach( $tax_request_body->get_line_items() as $item ) {
			if ( strpos( $item['id'], $product_id . '-' ) !== false ) {
				return $item;
			}
		}
	}

	public function provide_cart_data(): array {
		WC_Tax::create_tax_class( 'clothing-rate-20010' );
		return [
			'a single simple product' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(),
						'quantity' => 1,
						'expected_values' => [
							'product_tax_code' => '',
							'unit_price' => 10,
							'discount' => 0
						]
					],
				],
			],
			'a simple product with quantity of 2' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(),
						'quantity' => 2,
						'expected_values' => [
							'product_tax_code' => '',
							'unit_price' => 10,
							'discount' => 0
						]
					],
				],
			],
			'a simple product with discount' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(),
						'quantity' => 2,
						'expected_values' => [
							'product_tax_code' => '',
							'unit_price' => 10,
							'discount' => 10
						]
					]
				],
				[
					TaxJar_Coupon_Helper::create_coupon()
				]
			],
			'a simple product with non taxable status' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(
							'simple',
							[ 'tax_status' => 'none']
						),
						'quantity' => 1,
						'expected_values' => [
							'product_tax_code' => '99999',
							'unit_price' => 10,
							'discount' => 0
						]
					],
				],
			],
			'a simple product with tax class' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(
							'simple',
							[ 'tax_class' => 'clothing-rate-20010']
						),
						'quantity' => 1,
						'expected_values' => [
							'product_tax_code' => '20010',
							'unit_price' => 10,
							'discount' => 0
						]
					],
				],
			],
			'a subscription product' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product(
							'subscription',
							[ 'trial_length' => 0 ]
						),
						'quantity' => 1,
						'expected_values' => [
							'product_tax_code' => '',
							'unit_price' => 19.99,
							'discount' => 0
						]
					],
				],
			],
			'a subscription product with free trial' => [
				[
					[
						'product' => TaxJar_Product_Helper::create_product( 'subscription' ),
						'quantity' => 1,
						'expected_values' => [
							'product_tax_code' => '',
							'unit_price' => 0,
							'discount' => 0
						]
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provide_cart_data_with_fees
	 *
	 * @param array $fees
	 * @param array $coupons
	 *
	 * @throws Exception
	 */
	public function test_fee_item( array $fees, array $coupons = []) {
		$cart_builder = Cart_Builder::a_cart();
		$cart_builder->with_product( TaxJar_Product_Helper::create_product()->get_id(), 1 );
		foreach( $fees as $fee ) {
			$cart_builder->with_fee( $fee );
		}
		foreach( $coupons as $coupon ) {
			$cart_builder->with_coupon( $coupon->get_code() );
		}
		$cart = $cart_builder->build();
		$totals = new WC_Cart_Totals( $cart );
		$cart_tax_request_body_builder = new Cart_Tax_Request_Body_Builder( $cart );

		$tax_request_body = $cart_tax_request_body_builder->create();

		foreach( $fees as $fee ) {
			$item = $this->get_fee_item_from_tax_request_body( $tax_request_body, $fee );

			$this->assertEquals( $fee['expected_values']['quantity'], $item['quantity'] );
			$this->assertEquals( $fee['expected_values']['product_tax_code'], $item['product_tax_code'] );
			$this->assertEquals( $fee['expected_values']['unit_price'], $item['unit_price'] );
			$this->assertEquals( $fee['expected_values']['discount'], $item['discount'] );
		}
	}

	public function provide_cart_data_with_fees(): array {
		WC_Tax::create_tax_class( 'clothing-rate-20010' );
		return [
			'a taxable fee' => [
				[
					[
						'name' => 'test-fee',
						'amount' => 10,
						'taxable' => true,
						'tax_class' => '',
						'expected_values' => [
							'quantity' => 1,
							'product_tax_code' => '',
							'unit_price' => 10,
							'discount' => 0
						]
					]
				]
			],
			'a non taxable fee ' => [
				[
					[
						'name' => 'test-fee',
						'amount' => 10,
						'taxable' => false,
						'tax_class' => '',
						'expected_values' => [
							'quantity' => 1,
							'product_tax_code' => '99999',
							'unit_price' => 10,
							'discount' => 0
						]
					]
				]
			],
			'a fee with ptc ' => [
				[
					[
						'name' => 'test-fee',
						'amount' => 10,
						'taxable' => true,
						'tax_class' => 'clothing-rate-20010',
						'expected_values' => [
							'quantity' => 1,
							'product_tax_code' => '20010',
							'unit_price' => 10,
							'discount' => 0
						]
					]
				]
			],
			'a fee with coupon applied' => [
				[
					[
						'name' => 'test-fee',
						'amount' => 10,
						'taxable' => true,
						'tax_class' => '',
						'expected_values' => [
							'quantity' => 1,
							'product_tax_code' => '',
							'unit_price' => 10,
							'discount' => 0
						]
					]
				],
				[
					TaxJar_Coupon_Helper::create_coupon()
				]
			]
		];
	}

	private function get_fee_item_from_tax_request_body( $tax_request_body, $fee ) {
		foreach( $tax_request_body->get_line_items() as $item ) {
			if ( $item['id'] === $fee['name'] ) {
				return $item;
			}
		}
	}

}
