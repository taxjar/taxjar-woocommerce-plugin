<?php

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Client_Interface {

	public function get_taxes( $tax_request_body );
}