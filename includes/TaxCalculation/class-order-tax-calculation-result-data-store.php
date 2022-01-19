<?php
/**
 * Order Tax Calculation Result Data Store
 *
 * Persists tax calculation result to order.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Order_Tax_Calculation_Result_Data_Store
 */
class Order_Tax_Calculation_Result_Data_Store implements Tax_Calculation_Result_Data_Store {

	/**
	 * Order to persist result on
	 *
	 * @var \WC_Order
	 */
	private $order;

	/**
	 * Order_Tax_Calculation_Result_Data_Store Constructor
	 *
	 * @param \WC_Order $order Order to persist results on.
	 */
	public function __construct( \WC_Order $order ) {
		$this->order = $order;
	}

	/**
	 * Persist results on the order
	 *
	 * @param Tax_Calculation_Result $calculation_result Result of tax calculation.
	 */
	public function update( Tax_Calculation_Result $calculation_result ) {
		$calculation_result->set_raw_request('');
		$calculation_result->set_raw_response('');
		$this->order->update_meta_data( '_taxjar_tax_result', $calculation_result->to_json() );
	}

}
