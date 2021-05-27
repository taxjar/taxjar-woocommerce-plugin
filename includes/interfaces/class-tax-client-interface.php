<?php
/**
 * Tax Client Interface
 *
 * @package TaxJar\Interface
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Tax_Client_Interface {

	/**
	 * Gets tax request details using tax request body.
	 *
	 * @param Tax_Request_Body $tax_request_body Tax request body.
	 *
	 * @return Tax_Details
	 */
	public function get_taxes( $tax_request_body );
}
