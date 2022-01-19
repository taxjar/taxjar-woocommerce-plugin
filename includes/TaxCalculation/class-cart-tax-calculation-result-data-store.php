<?php
/**
 * Cart Tax Calculation Result Data Store
 *
 * Persists tax calculation result to cart.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Cart_Tax_Calculation_Result_Data_Store
 */
class Cart_Tax_Calculation_Result_Data_Store implements Tax_Calculation_Result_Data_Store {

	/**
	 * Cart to persist result on
	 *
	 * @var \WC_Cart
	 */
	private $cart;

	/**
	 * Cart_Tax_Calculation_Result_Data_Store Constructor
	 *
	 * @param \WC_Cart $cart Cart to persist results on.
	 */
	public function __construct( \WC_Cart $cart ) {
		$this->cart = $cart;
	}

	/**
	 * Persist results on the cart
	 *
	 * @param Tax_Calculation_Result $calculation_result Result of tax calculation.
	 */
	public function update( Tax_Calculation_Result $calculation_result ) {
		$calculation_result->set_raw_request('');
		$calculation_result->set_raw_response('');
		$this->cart->tax_calculation_results = $calculation_result->to_json();
	}

}
