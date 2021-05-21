<?php
/**
 * Tax Request Body Builder
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract Class Tax_Request_Body_Builder
 */
abstract class Tax_Request_Body_Builder {

	/**
	 * Request body being built.
	 *
	 * @var Tax_Request_Body
	 */
	protected $tax_request_body;

	/**
	 * Get ship to address and add it to request body.
	 */
	abstract protected function get_ship_to_address();

	/**
	 * Get shipping amount and adds it to request body.
	 */
	abstract protected function get_shipping_amount();

	/**
	 * Get customer ID and add it to request body.
	 */
	abstract protected function get_customer_id();

	/**
	 * Get exemption type and add it to request body.
	 */
	abstract protected function get_exemption_type();

	/**
	 * Get product line items and add them to request body.
	 */
	abstract protected function get_product_line_items();

	/**
	 * Get fee line items and add them to request body.
	 */
	abstract protected function get_fee_line_items();

	/**
	 * Tax_Request_Body_Builder constructor.
	 */
	public function __construct() {
		$this->tax_request_body = new Tax_Request_Body();
	}

	/**
	 * Creates tax request body.
	 *
	 * @return Tax_Request_Body
	 */
	public function create() {
		$this->get_ship_to_address();
		$this->get_from_address();
		$this->get_line_items();
		$this->get_shipping_amount();
		$this->get_customer_id();
		$this->get_exemption_type();

		return $this->get_request_body();
	}

	/**
	 * Get line items and add them to request body.
	 */
	protected function get_line_items() {
		$this->get_product_line_items();
		$this->get_fee_line_items();
	}

	/**
	 * Get from address and add it to request body.
	 */
	protected function get_from_address() {
		$store_settings = TaxJar_Settings::get_store_settings();
		$this->tax_request_body->set_from_country( $store_settings['country'] );
		$this->tax_request_body->set_from_state( $store_settings['state'] );
		$this->tax_request_body->set_from_zip( $store_settings['postcode'] );
		$this->tax_request_body->set_from_city( $store_settings['city'] );
		$this->tax_request_body->set_from_street( $store_settings['street'] );
	}

	/**
	 * Gets created tax request body.
	 *
	 * @return Tax_Request_Body
	 */
	protected function get_request_body() {
		return $this->tax_request_body;
	}
}
