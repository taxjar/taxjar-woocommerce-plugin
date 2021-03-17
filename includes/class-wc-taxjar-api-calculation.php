<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Taxjar_API_Calculation {

	/**
	 * @var WC_Taxjar_Integration
	 */
	public $taxjar_integration;

	/**
	 * WC_Taxjar_API_Calculation constructor.
	 */
	public function __construct( $integration ) {
		$this->taxjar_integration = $integration;

		if ( $this->is_api_calculation_enabled() ) {
			// Calculate tax during creation and update of order through WooCommerce REST API
			add_filter( 'woocommerce_rest_pre_insert_shop_order_object', array( $this, 'calculate_api_order_tax' ), 20, 3 );
		}
	}

	/**
	 * Determines if tax calculation on API orders is enabled
	 *
	 * @return bool
	 */
	public function is_api_calculation_enabled() {
		return isset( $this->taxjar_integration->settings['api_calcs_enabled'] ) && 'yes' === $this->taxjar_integration->settings['api_calcs_enabled'];
	}

	/**
	 * Calculates tax on order created through the API
	 *
	 * @param WC_Order $order Object object.
	 * @param WP_REST_Request $request Request object.
	 * @param bool $creating If is creating a new object.
	 *
	 * @return WC_Order
	 */
	public function calculate_api_order_tax( $order, $request, $creating ) {

		if ( ! $this->api_order_needs_tax_calculated( $order, $request, $creating ) ) {
			return $order;
		}

		$this->taxjar_integration->tax_calculations->calculate_order_tax( $order );

		return $order;
	}

	/**
	 * Determines whether or not to calculate tax on and API order
	 *
	 * @param WC_Order $order Object object.
	 * @param WP_REST_Request $request Request object.
	 * @param bool $creating If is creating a new object.
	 *
	 * @return bool
	 */
	public function api_order_needs_tax_calculated( $order, $request, $creating ) {
		$needs_tax_calculated = true;

		if ( ! $creating ) {
			if ( ! isset( $request['billing'] ) && ! isset( $request['shipping'] ) && ! isset( $request['line_items'] ) && ! isset( $request['shipping_lines'] ) && ! isset( $request['fee_lines'] ) && ! isset( $request['coupon_lines'] ) ) {
				$needs_tax_calculated = false;
			}
		}

		$total = 0;

		foreach( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
			$total += floatval( $item->get_total() );
		}

		if ( $total <= 0 ) {
			$needs_tax_calculated = false;
		}

		return apply_filters( 'taxjar_api_order_needs_tax_calculated', $needs_tax_calculated, $order, $request, $creating );
	}

}

