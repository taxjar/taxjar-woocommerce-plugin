<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface TaxJar_Tax_Client_Interface {

	public function get_taxes( $tax_request_body );
}