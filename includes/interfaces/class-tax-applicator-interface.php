<?php
/**
 * Tax Applicator Interface
 *
 * @package TaxJar\Interface
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Applicator_Interface {

	/**
	 * Applies tax details.
	 *
	 * @param Tax_Details $tax_details Tax details to apply.
	 */
	public function apply_tax( Tax_Details $tax_details );
}
