<?php

namespace TaxJar;

use TaxJar_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Tax_Request_Body_Builder {

	protected $tax_request_body;

	abstract protected function get_ship_to_address();
	abstract protected function get_shipping_amount();
	abstract protected function get_customer_id();
	abstract protected function get_exemption_type();
	abstract protected function get_request_body();
	abstract protected function get_product_line_items();
	abstract protected function get_fee_line_items();

	public function __construct() {
		$this->tax_request_body = new Tax_Request_Body();
	}

	public function create() {
		$this->get_ship_to_address();
		$this->get_from_address();
		$this->get_line_items();
		$this->get_shipping_amount();
		$this->get_customer_id();
		$this->get_exemption_type();

		return $this->get_request_body();
	}

	protected function get_line_items() {
		$this->get_product_line_items();
		$this->get_fee_line_items();
	}

	protected function get_from_address() {
		$store_settings = TaxJar_Settings::get_store_settings();
		$this->tax_request_body->set_from_country( $store_settings[ 'country' ] );
		$this->tax_request_body->set_from_state( $store_settings[ 'state' ] );
		$this->tax_request_body->set_from_zip( $store_settings[ 'postcode' ] );
		$this->tax_request_body->set_from_city( $store_settings[ 'city' ] );
		$this->tax_request_body->set_from_street( $store_settings[ 'street' ] );
	}
}