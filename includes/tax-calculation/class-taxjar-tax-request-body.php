<?php

namespace TaxJar;

use TaxJar_Tax_Calculation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Request_Body {

	private $to_country = null;
	private $to_state = null;
	private $to_zip = null;
	private $to_city = null;
	private $to_street = null;
	private $from_country = null;
	private $from_state = null;
	private $from_zip = null;
	private $from_city = null;
	private $from_street = null;
	private $shipping_amount = null;
	private $customer_id = 0;
	private $exemption_type = '';
	private $line_items = array();

	public function __construct() {

	}

	public function validate() {
		$this->validate_country_is_present();
		$this->validate_zip_code_is_present();
		$this->validate_line_items_or_shipping_amount_are_present();
		$this->validate_zip_code_format();
	}

	private function validate_country_is_present() {
		if ( empty( $this->get_to_country() ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'missing_required_field_country',
				__( 'Country field is required to perform tax calculation.', 'taxjar' )
			);
		}
	}

	private function validate_zip_code_is_present() {
		if ( empty( $this->get_to_zip() ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'missing_required_field_zip',
				__( 'Zip code is required to perform tax calculation.', 'taxjar' )
			);
		}
	}

	private function validate_line_items_or_shipping_amount_are_present() {
		if ( empty( $this->get_line_items() ) && ( 0 === $this->get_shipping_amount() ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'missing_required_field_line_item_or_shipping',
				__( 'Either a line item or shipping amount is required to calculate shipping.', 'taxjar' )
			);
		}
	}

	private function validate_zip_code_format() {
		if ( ! TaxJar_Tax_Calculation::is_postal_code_valid( $this->get_to_country(), $this->get_to_state(), $this->get_to_zip() ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'invalid_field_zip',
				__( 'Invalid zip code. The to address zip code does not match the format required for the country.', 'taxjar' )
			);
		}
	}

	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	public function to_array() {
		$request_body = array(
			'from_country' => $this->get_from_country(),
			'from_state'   => $this->get_from_state(),
			'from_zip'     => $this->get_from_zip(),
			'from_city'    => $this->get_from_city(),
			'from_street'  => $this->get_from_street(),
			'to_country'   => $this->get_to_country(),
			'to_state'     => $this->get_to_state(),
			'to_zip'       => $this->get_to_zip(),
			'to_city'      => $this->get_to_city(),
			'to_street'    => $this->get_to_street(),
			'shipping'     => $this->get_shipping_amount(),
			'plugin'       => 'woo',
		);


		if ( $this->get_customer_id() !== 0 ) {
			$request_body[ 'customer_id' ] = $this->get_customer_id();
		}

		if ( ! empty( $this->get_exemption_type() ) && TaxJar_Tax_Calculation::is_valid_exemption_type( $this->get_exemption_type() ) ) {
			$request_body[ 'exemption_type' ] = $this->get_exemption_type();
		}

		if ( empty( $this->get_line_items() ) ) {
			$request_body[ 'amount' ] = 0.0;
		} else {
			$request_body[ 'line_items' ] = $this->get_line_items();
		}

		return apply_filters( 'taxjar_tax_request_body', $request_body, $this );
	}

	public function set_to_country( $to_country ) {
		$this->to_country = $to_country;
	}

	public function get_to_country() {
		return $this->to_country;
	}

	public function set_to_state( $to_state ) {
		$this->to_state = $to_state;
	}

	public function get_to_state() {
		return $this->to_state;
	}

	public function set_to_zip( $to_zip ) {
		$this->to_zip = $to_zip;
	}

	public function get_to_zip() {
		return $this->to_zip;
	}

	public function set_to_city( $to_city ) {
		$this->to_city = $to_city;
	}

	public function get_to_city() {
		return $this->to_city;
	}

	public function set_to_street( $to_street ) {
		$this->to_street = $to_street;
	}

	public function get_to_street() {
		return $this->to_street;
	}

	public function set_from_country( $from_country ) {
		$this->from_country = $from_country;
	}

	public function get_from_country() {
		return $this->from_country;
	}

	public function set_from_state( $from_state ) {
		$this->from_state = $from_state;
	}

	public function get_from_state() {
		return $this->from_state;
	}

	public function set_from_zip( $from_zip ) {
		$this->from_zip = $from_zip;
	}

	public function get_from_zip() {
		return $this->from_zip;
	}

	public function set_from_city( $from_city ) {
		$this->from_city = $from_city;
	}

	public function get_from_city() {
		return $this->from_city;
	}

	public function set_from_street( $from_street ) {
		$this->from_street = $from_street;
	}

	public function get_from_street() {
		return $this->from_street;
	}

	public function set_shipping_amount( $shipping_amount ) {
		$this->shipping_amount = $shipping_amount;
	}

	public function get_shipping_amount() {
		return $this->shipping_amount;
	}

	public function set_customer_id( $customer_id ) {
		$this->customer_id = $customer_id;
	}

	public function get_customer_id() {
		return $this->customer_id;
	}

	public function set_exemption_type( $exemption_type ) {
		$this->exemption_type = $exemption_type;
	}

	public function get_exemption_type() {
		return $this->exemption_type;
	}

	public function add_line_item( $line_item ) {
		$this->line_items[] = $line_item;
	}

	public function get_line_items() {
		return $this->line_items;
	}
}
