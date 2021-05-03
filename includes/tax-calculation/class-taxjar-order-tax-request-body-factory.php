<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Tax_Request_Body_Factory extends TaxJar_Tax_Request_Body_Factory {

	private $order;

	protected function set_original_object( $order ) {
		$this->order = $order;
	}

	protected function get_ship_to_address() {
		$address = $this->order->get_address( 'shipping' );
		$this->tax_request_body->set_to_country( $address[ 'country' ] );
		$this->tax_request_body->set_to_state( $address[ 'state' ] );
		$this->tax_request_body->set_to_zip( $address[ 'postcode' ] );
		$this->tax_request_body->set_to_city( $address[ 'city' ] );
		$this->tax_request_body->set_to_street( $address[ 'address_1' ] );
	}

	protected function get_product_line_items() {
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$request_line_item = array(
				'id'               => $item->get_product_id() . '-' . $item_key,
				'quantity'         => $item->get_quantity(),
				'product_tax_code' => $this->get_line_item_tax_code( $item ),
				'unit_price'       => $this->get_line_item_unit_price( $item ),
				'discount'         => $this->get_line_item_discount_amount( $item )
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	protected function get_fee_line_items() {
		foreach ( $this->order->get_items( 'fee' ) as $fee_key => $fee ) {
			$request_line_item = array(
				'id'               => 'fee-' . $fee_key,
				'quantity'         => 1,
				'product_tax_code' => $this->get_line_item_tax_code( $fee ),
				'unit_price'       => $fee->get_amount(),
				'discount'         => 0
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	private function get_line_item_tax_code( $item ) {
		$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $item->get_tax_class() );

		if ( 'taxable' !== $item->get_tax_status() ) {
			$tax_code = '99999';
		}

		return $tax_code;
	}

	private function get_line_item_unit_price( $item ) {
		return wc_format_decimal( $item->get_subtotal() / $item->get_quantity() );
	}

	private function get_line_item_discount_amount( $item ) {
		return wc_format_decimal( $item->get_subtotal() - $item->get_total() );
	}

	protected function get_shipping_amount() {
		$this->tax_request_body->set_shipping_amount( $this->order->get_shipping_total() );
	}

	protected function get_customer_id() {
		$customer_id = apply_filters( 'taxjar_get_customer_id', $this->order->get_customer_id() );
		$this->tax_request_body->set_customer_id( $customer_id );
	}

	protected function get_exemption_type() {
		$exemption_type = apply_filters( 'taxjar_order_calculation_exemption_type', '', $this->order );
		$this->tax_request_body->set_exemption_type( $exemption_type );
	}

	protected function get_request_body() {
		return $this->tax_request_body;
	}
}

