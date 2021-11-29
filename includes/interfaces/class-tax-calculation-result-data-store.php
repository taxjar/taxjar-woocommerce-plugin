<?php
/**
 * Tax Calculation Result Data Store Interface
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Calculation_Result_Data_Store {

	/**
	 * Persists calculation result on object.
	 *
	 * @param Tax_Calculation_Result $calculation_result Calculation result.
	 *
	 * @return void
	 */
	public function update( Tax_Calculation_Result $calculation_result );

}
