<?php

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface TaxJar_Tax_Applicator_Interface {

	public function apply_tax( $tax_details );

}