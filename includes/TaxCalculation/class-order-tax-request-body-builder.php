<?php
/**
 * TaxJar Order Tax Request Body Builder
 *
 * Builds tax request body from WC_Order.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_Settings;
use TaxJar_Tax_Calculation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Tax_Request_Body_Builder
 */
class Order_Tax_Request_Body_Builder extends Tax_Request_Body_Builder {

	/**
	 * Order object use to get details for tax request body.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Order_Tax_Request_Body_Builder constructor.
	 *
	 * @param WC_Order $order Order to get tax request body details from.
	 */
	public function __construct( $order ) {
		$this->order = $order;
		parent::__construct();
	}

	/**
	 * Get ship to address from order and set on tax request body.
	 */
	protected function get_ship_to_address() {
		if ( $this->has_local_shipping() ) {
			$this->set_to_address_from_store_address();
			return;
		}

		$address = $this->order->get_address( 'shipping' );

		if ( empty( $address['country'] ) ) {
			$address = $this->order->get_address( 'billing' );
		}

		$this->tax_request_body->set_to_country( $address['country'] );
		$this->tax_request_body->set_to_state( $address['state'] );
		$this->tax_request_body->set_to_zip( $address['postcode'] );
		$this->tax_request_body->set_to_city( $address['city'] );
		$this->tax_request_body->set_to_street( $address['address_1'] );
	}

	/**
	 * Determines if order used local shipping shipment method.
	 *
	 * @return bool
	 */
	protected function has_local_shipping() {
		if ( true !== apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) ) {
			return false;
		}

		$local_shipping_methods = apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) );
		foreach( $local_shipping_methods as $method ) {
			if ( $this->order->has_shipping_method( $method ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Set to address from store address.
	 */
	protected function set_to_address_from_store_address() {
		$store_settings = TaxJar_Settings::get_store_settings();
		$this->tax_request_body->set_to_country( $store_settings['country'] );
		$this->tax_request_body->set_to_state( $store_settings['state'] );
		$this->tax_request_body->set_to_zip( $store_settings['postcode'] );
		$this->tax_request_body->set_to_city( $store_settings['city'] );
		$this->tax_request_body->set_to_street( $store_settings['street'] );
	}

	/**
	 * Get product line item details from order and set on tax request body.
	 */
	protected function get_product_line_items() {
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$request_line_item = array(
				'id'               => $item->get_product_id() . '-' . $item_key,
				'quantity'         => $item->get_quantity(),
				'product_tax_code' => $this->get_product_tax_code( $item ),
				'unit_price'       => $this->get_line_item_unit_price( $item ),
				'discount'         => $this->get_line_item_discount_amount( $item ),
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	/**
	 * Get fee line item details from order and set on tax request body.
	 */
	protected function get_fee_line_items() {
		foreach ( $this->order->get_items( 'fee' ) as $fee_key => $fee ) {
			$request_line_item = array(
				'id'               => 'fee-' . $fee_key,
				'quantity'         => 1,
				'product_tax_code' => $this->get_line_item_tax_code( $fee ),
				'unit_price'       => $fee->get_total(),
				'discount'         => 0,
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	/**
	 * @param WC_Order_Item_Product $item product line item.
	 *
	 * @return string
	 */
	protected function get_product_tax_code( $item ) {
		$product = $item->get_product();

		if ( ! $product ) {
			return $this->get_line_item_tax_code( $item );
		}

		$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $product->get_tax_class() );

		if ( 'taxable' !== $product->get_tax_status() ) {
			$tax_code = '99999';
		}

		return $tax_code;
	}

	/**
	 * Get tax code from item.
	 *
	 * @param WC_Order_Item $item Item to get tax class of.
	 *
	 * @return string
	 */
	protected function get_line_item_tax_code( $item ) {
		$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $item->get_tax_class() );

		if ( 'taxable' !== $item->get_tax_status() ) {
			$tax_code = '99999';
		}

		return $tax_code;
	}

	/**
	 * Calculate unit price for product line item.
	 *
	 * @param WC_Order_Item_Product $item Item to calculate unit price of.
	 *
	 * @return string
	 */
	protected function get_line_item_unit_price( $item ) {
		return wc_format_decimal( $item->get_subtotal() / $item->get_quantity() );
	}

	/**
	 * Calculate discount for product line item.
	 *
	 * @param WC_Order_Item_Product $item Item to calculate discount for.
	 *
	 * @return string
	 */
	protected function get_line_item_discount_amount( $item ) {
		return wc_format_decimal( $item->get_subtotal() - $item->get_total() );
	}

	/**
	 * Get shipping amount from order and set on tax request body.
	 */
	protected function get_shipping_amount() {
		$this->tax_request_body->set_shipping_amount( $this->order->get_shipping_total() );
	}

	/**
	 * Get customer ID from order and set on tax request body.
	 */
	protected function get_customer_id() {
		$customer_id = apply_filters( 'taxjar_get_customer_id', $this->order->get_customer_id() );
		$this->tax_request_body->set_customer_id( $customer_id );
	}

	/**
	 * Get exemption type from order and set on tax request body.
	 */
	protected function get_exemption_type() {
		$exemption_type = apply_filters( 'taxjar_order_calculation_exemption_type', '', $this->order );
		$this->tax_request_body->set_exemption_type( $exemption_type );
	}
}

