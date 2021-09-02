<?php
/**
 * TaxJar Cart Tax Request Body Builder
 *
 * Builds tax request body from WC_Cart.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_Settings;
use TaxJar_Tax_Calculation;
use WC_Cart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Tax_Request_Body_Builder
 */
class Cart_Tax_Request_Body_Builder extends Tax_Request_Body_Builder {

	/**
	 * Cart object use to get details for tax request body.
	 *
	 * @var WC_Cart
	 */
	protected $cart;

	/**
	 * Cart_Tax_Request_Body_Builder constructor.
	 *
	 * @param WC_Cart $cart cart to get tax request body details from.
	 */
	public function __construct( WC_Cart $cart ) {
		$this->cart = $cart;
		parent::__construct();
	}

	/**
	 * Get ship to address from cart and set on tax request body.
	 * Method based on WC_Customer::get_taxable_address but can't use it directly due to lack of street field
	 */
	protected function get_ship_to_address() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && count( array_intersect( wc_get_chosen_shipping_method_ids(), apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 ) {
			$tax_based_on = 'base';
		}

		if ( 'base' === $tax_based_on ) {
			$store_settings = TaxJar_Settings::get_store_settings();
			$country        = $store_settings['country'];
			$state          = $store_settings['state'];
			$postcode       = $store_settings['postcode'];
			$city           = $store_settings['city'];
			$street         = $store_settings['street'];
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = WC()->customer->get_billing_country();
			$state    = WC()->customer->get_billing_state();
			$postcode = WC()->customer->get_billing_postcode();
			$city     = WC()->customer->get_billing_city();
			$street   = WC()->customer->get_billing_address();
		} else {
			$country  = WC()->customer->get_shipping_country();
			$state    = WC()->customer->get_shipping_state();
			$postcode = WC()->customer->get_shipping_postcode();
			$city     = WC()->customer->get_shipping_city();
			$street   = WC()->customer->get_shipping_address();
		}

		$taxable_address = apply_filters( 'woocommerce_customer_taxable_address', array( $country, $state, $postcode, $city, $street ) );
		$this->tax_request_body->set_to_country( $taxable_address[0] );
		$this->tax_request_body->set_to_state( $taxable_address[1] );
		$this->tax_request_body->set_to_zip( $taxable_address[2] );
		$this->tax_request_body->set_to_city( $taxable_address[3] );
		$this->tax_request_body->set_to_street( $taxable_address[4] );
	}

	/**
	 * Get shipping amount and adds it to request body.
	 */
	protected function get_shipping_amount() {
		$this->tax_request_body->set_shipping_amount( $this->cart->get_shipping_total() );
	}

	/**
	 * Get customer ID and add it to request body.
	 */
	protected function get_customer_id() {
		$customer_id = apply_filters( 'taxjar_get_customer_id', WC()->customer->get_id(), WC()->customer );
		$this->tax_request_body->set_customer_id( $customer_id );
	}

	/**
	 * Get exemption type and add it to request body.
	 */
	protected function get_exemption_type() {
		$exemption_type = apply_filters( 'taxjar_cart_exemption_type', '', $this->cart );
		$this->tax_request_body->set_exemption_type( $exemption_type );
	}

	/**
	 * Get product line items and add them to request body.
	 */
	protected function get_product_line_items() {
		foreach ( $this->cart->get_cart() as $item_key => $item ) {
			$request_line_item = array(
				'id'               => $item['data']->get_id() . '-' . $item_key,
				'quantity'         => $item['quantity'],
				'product_tax_code' => $this->get_line_item_tax_code( $item['data'] ),
				'unit_price'       => $this->get_line_item_unit_price( $item ),
				'discount'         => $this->get_line_item_discount_amount( $item ),
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	/**
	 * Get product tax code for line item
	 *
	 * @param WC_Product $product Product in cart.
	 *
	 * @return string
	 */
	private function get_line_item_tax_code( $product ): string {
		if ( ! $product->is_taxable() || 'zero-rate' === sanitize_title( $product->get_tax_class() ) ) {
			return '99999';
		}

		return TaxJar_Tax_Calculation::get_tax_code_from_class( $product->get_tax_class() );
	}

	/**
	 * Get line item unit price
	 *
	 * @param array $item Item in cart.
	 *
	 * @return float|string
	 */
	private function get_line_item_unit_price( $item ) {
		return wc_format_decimal( $item['line_subtotal'] / $item['quantity'] );
	}

	/**
	 * Get line item discount amount
	 *
	 * @param array $item Item in cart.
	 *
	 * @return float|string
	 */
	private function get_line_item_discount_amount( $item ) {
		return wc_format_decimal( $item['line_subtotal'] - $item['line_total'] );
	}

	/**
	 * Get fee line items and add them to request body.
	 */
	protected function get_fee_line_items() {
		foreach ( $this->cart->get_fees() as $fee_key => $fee ) {
			$request_line_item = array(
				'id'               => $fee->id,
				'quantity'         => 1,
				'product_tax_code' => $this->get_fee_item_tax_code( $fee ),
				'unit_price'       => $fee->total,
				'discount'         => 0,
			);

			$this->tax_request_body->add_line_item( $request_line_item );
		}
	}

	/**
	 * Get product tax code from fee
	 *
	 * @param object $fee Fee in cart.
	 *
	 * @return string
	 */
	private function get_fee_item_tax_code( $fee ): string {
		if ( ! $fee->taxable || 'zero-rate' === sanitize_title( $fee->tax_class ) ) {
			return '99999';
		}

		return TaxJar_Tax_Calculation::get_tax_code_from_class( $fee->tax_class );
	}
}

