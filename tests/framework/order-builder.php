<?php

namespace TaxJar\Tests\Framework;

use TaxJar_Product_Helper;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Shipping_Rate;

class Order_Builder {

	const SIMPLE_PRODUCT = [
		'type'         => 'simple',
		'price'        => 100,
		'quantity'     => 1,
		'name'         => 'Dummy Product',
		'sku'          => 'SIMPLE1',
		'manage_stock' => false,
		'tax_status'   => 'taxable',
		'downloadable' => false,
		'virtual'      => false,
		'stock_status' => 'instock',
		'weight'       => '1.1',
		'tax_class'    => '',
		'tax_total'    => array( 0 ),
		'tax_subtotal' => array( 0 ),
	];

	private $order;
	private $customer_id = 1;
	private $products = [];
	private $fees = [];
	private $shipping;

	private $shipping_address = [
		'first_name' => 'Fname',
		'last_name'  => 'Lname',
		'address_1'  => 'Shipping Address',
		'address_2'  => '',
		'city'       => 'Greenwood Village',
		'state'      => 'CO',
		'postcode'   => '80111',
		'country'    => 'US',
	];

	private $billing_address = [
		'first_name' => 'Fname',
		'last_name'  => 'Lname',
		'address_1'  => 'Billing Address',
		'address_2'  => '',
		'city'       => 'Greenwood Village',
		'state'      => 'CO',
		'postcode'   => '80111',
		'country'    => 'US',
		'email'      => 'admin@example.org',
		'phone'      => '111-111-1111',
	];

	private $totals = [
		'shipping_total' => 0,
		'discount_total' => 0,
		'discount_tax'   => 0,
		'cart_tax'       => 0,
		'shipping_tax'   => 0,
		'total'          => 0,
	];

	public static function an_order(): Order_Builder {
		return new static();
	}

	public function __construct() {
		$this->order = wc_create_order();
	}

	public function build(): WC_Order {
		$this->set_customer_id();
		$this->set_shipping_address();
		$this->set_billing_address();
		$this->add_products();
		$this->add_fees();
		$this->add_shipping();
		$this->set_payment_method();
		$this->set_totals();
		$this->order->save();
		return $this->order;
	}

	private function set_customer_id() {
		$this->order->set_customer_id( $this->customer_id );
	}

	private function set_shipping_address() {
		$this->order->set_shipping_first_name( $this->shipping_address['first_name'] );
		$this->order->set_shipping_last_name( $this->shipping_address['last_name'] );
		$this->order->set_shipping_address_1( $this->shipping_address['address_1'] );
		$this->order->set_shipping_address_2( $this->shipping_address['address_2'] );
		$this->order->set_shipping_city( $this->shipping_address['city'] );
		$this->order->set_shipping_state( $this->shipping_address['state'] );
		$this->order->set_shipping_postcode( $this->shipping_address['postcode'] );
		$this->order->set_shipping_country( $this->shipping_address['country'] );
	}

	private function set_billing_address() {
		$this->order->set_billing_first_name( $this->billing_address['first_name'] );
		$this->order->set_billing_last_name(  $this->billing_address['last_name'] );
		$this->order->set_billing_address_1(  $this->billing_address['address_1'] );
		$this->order->set_billing_address_2(  $this->billing_address['address_2'] );
		$this->order->set_billing_city(  $this->billing_address['city'] );
		$this->order->set_billing_state(  $this->billing_address['state'] );
		$this->order->set_billing_postcode(  $this->billing_address['postcode'] );
		$this->order->set_billing_country(  $this->billing_address['country'] );
		$this->order->set_billing_email(  $this->billing_address['email'] );
		$this->order->set_billing_phone(  $this->billing_address['phone'] );
	}

	private function add_products() {
		foreach( $this->products as $product_details ) {
			$product = TaxJar_Product_Helper::create_product( $product_details['type'], $product_details );
			$item    = new WC_Order_Item_Product();
			$item->set_props(
				array(
					'product'  => $product,
					'quantity' => $product_details['quantity'],
					'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => $product_details['quantity'] ) ),
					'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => $product_details['quantity'] ) ),
				)
			);
			$item->set_taxes(
				array(
					'total'    => $product_details['tax_total'],
					'subtotal' => $product_details['tax_subtotal'],
				)
			);
			$item->set_order_id( $this->order->get_id() );
			$item->save();
			$this->order->add_item( $item );
		}
	}

	public function add_fees() {
		foreach( $this->fees as $fee_details ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( $fee_details['name'] );
			$fee->set_amount( $fee_details['amount'] );
			$fee->set_tax_class( $fee_details['tax_class'] );
			$fee->set_tax_status( $fee_details['tax_status'] );
			$fee->set_total( $fee_details['amount'] );
			$this->order->add_item( $fee );
		}
	}

	private function add_shipping() {
		$rate = new WC_Shipping_Rate(
			$this->shipping['id'],
			$this->shipping['label'],
			$this->shipping['cost'],
			$this->shipping['taxes'],
			$this->shipping['method_id']
		);

		$item = new WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $rate->label,
				'method_id'    => $rate->id,
				'total'        => wc_format_decimal( $rate->cost ),
				'taxes'        => $rate->taxes,
			)
		);

		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}

		$item->save();
		$this->order->add_item( $item );
	}

	private function set_payment_method() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->order->set_payment_method( $payment_gateways['bacs'] );
	}

	public function set_totals() {
		$this->order->set_shipping_total( $this->totals['shipping_total'] );
		$this->order->set_discount_total( $this->totals['discount_total'] );
		$this->order->set_discount_tax( $this->totals['discount_tax'] );
		$this->order->set_cart_tax( $this->totals['cart_tax'] );
		$this->order->set_shipping_tax( $this->totals['shipping_tax'] );
		$this->order->set_total( $this->totals['total'] );
	}

	public function with_customer_id( $customer_id ) {
		$this->customer_id = $customer_id;
	}

	public function with_shipping_address( $shipping_address ) {
		$this->shipping_address = $shipping_address;
	}

	public function with_billing_address( $billing_address ) {
		$this->billing_address = $billing_address;
	}

	public function with_product( $product ) {
		$this->products[] = $product;
	}

	public function with_shipping( $shipping ) {
		$this->shipping = $shipping;
	}

	public function with_totals( $totals ) {
		$this->totals = $totals;
	}

	public function with_fee( $fee ) {
		$this->fees[] = $fee;
	}
}
