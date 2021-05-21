<?php
/**
 * Tax Calculation Validator
 *
 * Validates that tax calculation can and should be performed on an order.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use WC_Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Tax_Calculation_Validator
 */
class Order_Tax_Calculation_Validator implements Tax_Calculation_Validator_Interface {

	/**
	 * Order having tax calculated.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Determines if order has nexus.
	 *
	 * @var WC_Taxjar_Nexus
	 */
	private $nexus;

	/**
	 * Order_Tax_Calculation_Validator constructor.
	 *
	 * @param WC_Order        $order Order having tax calculated.
	 * @param WC_Taxjar_Nexus $nexus Nexus determiner.
	 */
	public function __construct( $order, $nexus ) {
		$this->order = $order;
		$this->nexus = $nexus;
	}

	/**
	 * Validates that order can and should have tax calculated.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When order does not pass validation.
	 */
	public function validate( $request_body ) {
		$request_body->validate();
		$this->validate_order_total_is_not_zero();
		$this->validate_vat_exemption( $request_body );
		$this->validate_order_has_nexus( $request_body );
		$this->filter_interrupt();
	}

	/**
	 * Ensures order subtotal is greater than zero.
	 * If order subtotal is less than or equal to zero tax does not need to be calculated.
	 *
	 * @throws Tax_Calculation_Exception When subtotal is less than or equal to zero.
	 */
	private function validate_order_total_is_not_zero() {
		if ( $this->get_order_subtotal() <= 0 ) {
			throw new Tax_Calculation_Exception(
				'order_subtotal_zero',
				__( 'Tax calculation is not necessary when order subtotal is zero.', 'taxjar' )
			);
		}
	}

	/**
	 * Calculates order subtotal (shipping + fees + line item subtotals).
	 *
	 * @return float
	 */
	private function get_order_subtotal() {
		return $this->order->get_subtotal() + $this->order->get_total_fees() + floatval( $this->order->get_shipping_total() );
	}

	/**
	 * Ensures tax calculation is not performed on vat exempt customers and orders.
	 * The vat exempt setting is a legacy WooCommerce feature that we still maintain support for.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When customer or order is vat exempt.
	 */
	private function validate_vat_exemption( $request_body ) {
		if ( $this->is_order_vat_exempt() ) {
			throw new Tax_Calculation_Exception(
				'is_vat_exempt',
				__( 'Tax calculation is not performed if order is vat exempt.', 'taxjar' )
			);
		}

		if ( $this->is_customer_vat_exempt( $request_body ) ) {
			throw new Tax_Calculation_Exception(
				'is_vat_exempt',
				__( 'Tax calculation is not performed if customer is vat exempt.', 'taxjar' )
			);
		}
	}

	/**
	 * Determines if order has vat exempt meta data.
	 *
	 * @return bool
	 */
	private function is_order_vat_exempt() {
		$vat_exemption = 'yes' === $this->order->get_meta( 'is_vat_exempt' );
		return apply_filters( 'woocommerce_order_is_vat_exempt', $vat_exemption, $this->order );
	}

	/**
	 * Determines if customer has been set as vat exempt.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @return bool
	 * @throws \Exception If customer cannot be read/found.
	 */
	private function is_customer_vat_exempt( $request_body ) {
		$customer_id = intval( $request_body->get_customer_id() );
		if ( $customer_id > 0 ) {
			$customer = new WC_Customer( $customer_id );
			return $customer->is_vat_exempt();
		}

		return false;
	}

	/**
	 * Ensures order has nexus.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When order does not have nexus.
	 */
	private function validate_order_has_nexus( $request_body ) {
		if ( $this->is_out_of_nexus_areas( $request_body ) ) {
			throw new Tax_Calculation_Exception(
				'no_nexus',
				__( 'Order does not have nexus.', 'taxjar' )
			);
		}
	}

	/**
	 * Checks if order has nexus.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @return bool
	 */
	private function is_out_of_nexus_areas( $request_body ) {
		return ! $this->nexus->has_nexus_check( $request_body->get_to_country(), $request_body->get_to_state() );
	}

	/**
	 * Allows external code to prevent tax calculation on order.
	 *
	 * @throws Tax_Calculation_Exception When external code prevents tax calculation.
	 */
	private function filter_interrupt() {
		$should_calculate = apply_filters( 'taxjar_should_calculate_order_tax', true, $this->order );
		if ( ! $should_calculate ) {
			throw new Tax_Calculation_Exception(
				'filter_interrupt',
				__( 'Tax calculation has been interrupted through a filter.', 'taxjar' )
			);
		}
	}

}
