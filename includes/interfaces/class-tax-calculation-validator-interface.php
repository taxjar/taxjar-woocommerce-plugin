<?php
/**
 * Tax Calculation Validator Interface
 *
 * @package TaxJar\Interface
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Calculation_Validator_Interface {

	/**
	 * Validates that tax calculation is necessary and possible.
	 * Throws exception when validation does not pass.
	 *
	 * @param Tax_Request_Body $request_body Tax request body.
	 */
	public function validate( Tax_Request_Body $request_body );

}
