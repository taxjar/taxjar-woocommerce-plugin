<?php

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Calculation_Validator_Interface {

	public function validate( $request_body );

}