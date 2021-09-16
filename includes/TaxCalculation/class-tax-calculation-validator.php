<?php
/**
 * Abstract Tax Calculation Validator
 *
 * @package TaxJar\WooCommerce\TaxCalculation
 */

namespace TaxJar\WooCommerce\TaxCalculation;

use TaxJar\Tax_Calculation_Exception;
use TaxJar\Tax_Calculation_Validator_Interface;
use TaxJar\Tax_Request_Body;
use WC_Taxjar_Nexus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Calculation_Validator
 */
abstract class Tax_Calculation_Validator implements Tax_Calculation_Validator_Interface {

	/**
	 * TaxJar Nexus
	 *
	 * @var WC_Taxjar_Nexus
	 */
	protected $nexus;

	/**
	 * Tax_Calculation_Validator constructor
	 *
	 * @param WC_Taxjar_Nexus $nexus TaxJar Nexus.
	 */
	public function __construct( WC_Taxjar_Nexus $nexus ) {
		$this->nexus = $nexus;
	}

	/**
	 * Validates that an API request should be performed to calculate tax
	 *
	 * @param Tax_Request_Body $request_body tax request body.
	 * @throws Tax_Calculation_Exception When API request should not be performed.
	 */
	public function validate( Tax_Request_Body $request_body ) {
		$request_body->validate();
		$this->validate_total_is_not_zero();
		$this->validate_vat_exemption( $request_body );
		$this->validate_nexus( $request_body );
		$this->filter_interrupt();
	}

	/**
	 * Validates that the cart total is not zero
	 *
	 * @return void
	 */
	abstract protected function validate_total_is_not_zero();

	/**
	 * Validates that the cart is not vat exempt
	 *
	 * @param Tax_Request_Body $request_body tax request body.
	 *
	 * @return void
	 */
	abstract protected function validate_vat_exemption( Tax_Request_Body $request_body );

	/**
	 * Ensures order has nexus.
	 *
	 * @param Tax_Request_Body $request_body Request body containing information necessary to tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When order does not have nexus.
	 */
	protected function validate_nexus( Tax_Request_Body $request_body ) {
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
	protected function is_out_of_nexus_areas( Tax_Request_Body $request_body ): bool {
		return ! $this->nexus->has_nexus_check( $request_body->get_to_country(), $request_body->get_to_state() );
	}

	/**
	 * Allows other plugins to interrupt tax calculation process and prevent tax calculation API request.
	 *
	 * @return void
	 */
	abstract protected function filter_interrupt();

}
