<?php

namespace TaxJar\Tests\Framework;

use WC_Cart;
use WC_Customer;

class Cart_Builder {

	private $cart;

	private $shipping_address = [
		'street' => '123 main st',
		'city' => 'Payson',
		'state' => 'UT',
		'zip' => '84651',
		'country' => 'US'
	];

	private $billing_address = [
		'street' => '123 state st',
		'city' => 'Denver',
		'state' => 'CO',
		'zip' => '80014',
		'country' => 'US'
	];

	private $shipping_total = '10';

	private $customer_id;

	private $products = [];

	private $coupons = [];

	private $fees = [];

	public static function a_cart(): Cart_Builder {
		return new static();
	}

	public function __construct() {
		$this->cart = WC()->cart;
		$this->cart->empty_cart();
		WC()->customer = new WC_Customer();
	}

	public function with_shipping_address( array $address ): Cart_Builder {
		$this->shipping_address = $address;
		return $this;
	}

	public function with_billing_address( array $address ): Cart_Builder {
		$this->billing_address = $address;
		return $this;
	}

	public function with_shipping_total(string $total): Cart_Builder {
		$this->shipping_total = $total;
		return $this;
	}

	public function with_customer_id( int $customer_id ): Cart_Builder {
		$this->customer_id = $customer_id;
		return $this;
	}

	public function with_product( $product_id, $quantity ): Cart_Builder {
		$this->products[] = [
			'product_id' => $product_id,
			'quantity' => $quantity
		];
		return $this;
	}

	public function with_coupon( string $coupon_code ): Cart_Builder {
		$this->coupons[] = $coupon_code;
		return $this;
	}

	public function with_fee( array $fee ): Cart_Builder {
		$this->fees[] = $fee;
		return $this;
	}

	public function build(): WC_Cart {
		$this->set_shipping_address();
		$this->set_billing_address();
		$this->set_shipping_total();
		$this->set_customer_id();
		$this->add_products();
		$this->add_coupons();
		$this->add_fees();
		return $this->cart;
	}

	private function set_shipping_address() {
		WC()->customer->set_shipping_address( $this->shipping_address['street'] );
		WC()->customer->set_shipping_city( $this->shipping_address['city'] );
		WC()->customer->set_shipping_state( $this->shipping_address['state'] );
		WC()->customer->set_shipping_postcode( $this->shipping_address['zip'] );
		WC()->customer->set_shipping_country( $this->shipping_address['country'] );
	}

	private function set_billing_address() {
		WC()->customer->set_billing_address( $this->billing_address['street'] );
		WC()->customer->set_billing_city( $this->billing_address['city'] );
		WC()->customer->set_billing_state( $this->billing_address['state'] );
		WC()->customer->set_billing_postcode( $this->billing_address['zip'] );
		WC()->customer->set_billing_country( $this->billing_address['country'] );
	}

	private function set_shipping_total() {
		$this->cart->set_shipping_total( $this->shipping_total );
	}

	private function set_customer_id() {
		if ( $this->customer_id ) {
			WC()->customer->set_id( $this->customer_id );
		}
	}

	private function add_products() {
		foreach( $this->products as $item ) {
			$this->cart->add_to_cart( $item['product_id'], $item['quantity'] );
		}
	}

	private function add_coupons() {
		foreach( $this->coupons as $coupon ) {
			$this->cart->apply_coupon( $coupon );
		}
	}

	private function add_fees() {
		foreach( $this->fees as $fee ) {
			$this->cart->add_fee( $fee['name'], $fee['amount'], $fee['taxable'], $fee['tax_class'] );
		}
	}
}
