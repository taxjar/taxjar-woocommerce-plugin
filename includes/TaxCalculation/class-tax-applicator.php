<?php
/**
 * Abstract Tax Applicator
 *
 * Applies tax details to object.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Tax_Applicator
 */
abstract class Tax_Applicator implements Tax_Applicator_Interface {

	/**
	 * Tax details to apply to object
	 *
	 * @var Tax_Details
	 */
	protected $tax_details;

	/**
	 * Tax builder to format tax for items.
	 *
	 * @var Tax_Builder
	 */
	protected $tax_builder;

	/**
	 * Apply tax to object.
	 *
	 * @param Tax_Details $tax_details Tax details to apply.
	 *
	 * @throws Tax_Calculation_Exception When tax details indicate object does not have nexus.
	 */
	public function apply_tax( Tax_Details $tax_details ) {
		$this->tax_details = $tax_details;
		$this->tax_builder = new Tax_Builder( $tax_details );
		$this->check_tax_details_for_nexus();
		$this->apply_new_tax();
	}

	/**
	 * Check that response from TaxJar API indicates transaction has nexus.
	 *
	 * @throws Tax_Calculation_Exception If tax detail does not indicate nexus for the transaction.
	 */
	protected function check_tax_details_for_nexus() {
		if ( ! $this->tax_details->has_nexus() ) {
			throw new Tax_Calculation_Exception(
				'no_nexus',
				__( 'Tax response for order does not have nexus.', 'taxjar' )
			);
		}
	}

	/**
	 * Apply TaxJar tax to object.
	 */
	abstract protected function apply_new_tax();
}
