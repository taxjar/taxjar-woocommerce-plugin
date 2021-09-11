<?php
/**
 * Cart Tax Calculation Validator
 *
 * Validates that tax calculation can and should be performed on a cart.
 *
 * @package TaxJar
 */

namespace TaxJar;

use TaxJar\WooCommerce\TaxCalculation\Tax_Calculation_Validator;
use WC_Cart;
use WC_Taxjar_Nexus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cart_Tax_Calculation_Validator
 */
class Cart_Tax_Calculation_Validator extends Tax_Calculation_Validator {

	/**
	 * WooCommerce cart
	 *
	 * @var WC_Cart
	 */
	private $cart;

	/**
	 * Cart_Tax_Calculation_Validator constructor.
	 *
	 * @param WC_Cart         $cart cart.
	 * @param WC_Taxjar_Nexus $nexus Nexus determiner.
	 */
	public function __construct( WC_Cart $cart, WC_Taxjar_Nexus $nexus ) {
		parent::__construct( $nexus );
		$this->cart = $cart;
	}

	/**
	 * Ensures cart subtotal is greater than zero.
	 * If cart subtotal is less than or equal to zero tax does not need to be calculated.
	 *
	 * @throws Tax_Calculation_Exception When subtotal is less than or equal to zero.
	 */
	protected function validate_total_is_not_zero() {
		if ( $this->get_cart_subtotal() <= 0 ) {
			throw new Tax_Calculation_Exception(
				'cart_subtotal_zero',
				__( 'Tax calculation is not necessary when cart subtotal is zero.', 'taxjar' )
			);
		}
	}

	/**
	 * Calculates cart subtotal (shipping + fees + line item subtotals).
	 *
	 * @return float
	 */
	private function get_cart_subtotal(): float {
		return $this->cart->get_subtotal() + $this->cart->get_fee_total() + $this->cart->get_shipping_total();
	}

	/**
	 * Ensures tax calculation is not performed on carts for vat exempt customers.
	 * The vat exempt setting is a legacy WooCommerce feature that we still maintain support for.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When customer is vat exempt.
	 */
	protected function validate_vat_exemption( Tax_Request_Body $request_body ) {
		if ( $this->is_customer_vat_exempt() ) {
			throw new Tax_Calculation_Exception(
				'is_vat_exempt',
				__( 'Tax calculation is not performed if customer is vat exempt.', 'taxjar' )
			);
		}
	}

	/**
	 * Determines if customer has been set as vat exempt.
	 *
	 * @return bool
	 */
	private function is_customer_vat_exempt(): bool {
		if ( $this->cart->get_customer() && $this->cart->get_customer()->get_is_vat_exempt() ) {
			return true;
		}

		return false;
	}

	/**
	 * Allows external code to prevent tax calculation on the cart.
	 *
	 * @throws Tax_Calculation_Exception When external code prevents tax calculation.
	 */
	protected function filter_interrupt() {
		$should_calculate = apply_filters( 'taxjar_should_calculate_cart_tax', true, $this->cart );
		if ( ! $should_calculate ) {
			throw new Tax_Calculation_Exception(
				'filter_interrupt',
				__( 'Tax calculation has been interrupted through a filter.', 'taxjar' )
			);
		}
	}

}
