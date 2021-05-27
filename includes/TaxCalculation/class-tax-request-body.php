<?php
/**
 * Tax Request Body
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_Tax_Calculation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Request_Body
 */
class Tax_Request_Body {

	/**
	 * Shi to country code.
	 *
	 * @var null|string
	 */
	private $to_country = null;

	/**
	 * Ship to state code.
	 *
	 * @var null|string
	 */
	private $to_state = null;

	/**
	 * Ship to zip code.
	 *
	 * @var null|string
	 */
	private $to_zip = null;

	/**
	 * Ship to city.
	 *
	 * @var null|string
	 */
	private $to_city = null;

	/**
	 * Ship to street.
	 *
	 * @var null|string
	 */
	private $to_street = null;

	/**
	 * Ship from country code.
	 *
	 * @var null|string
	 */
	private $from_country = null;

	/**
	 * Ship from state code.
	 *
	 * @var null|string
	 */
	private $from_state = null;

	/**
	 * Ship from zip code.
	 *
	 * @var null|string
	 */
	private $from_zip = null;

	/**
	 * Ship from city.
	 *
	 * @var null|string
	 */
	private $from_city = null;

	/**
	 * Ship from street.
	 *
	 * @var null|string
	 */
	private $from_street = null;

	/**
	 * Total shipping.
	 *
	 * @var null|float
	 */
	private $shipping_amount = null;

	/**
	 * Customer ID.
	 *
	 * @var int
	 */
	private $customer_id = 0;

	/**
	 * Exemption type.
	 *
	 * @var string
	 */
	private $exemption_type = '';

	/**
	 * Line items.
	 *
	 * @var array
	 */
	private $line_items = array();

	/**
	 * Validate request body contains enough information to for tax calculation.
	 *
	 * @throws Tax_Calculation_Exception When missing fields or containing invalid data.
	 */
	public function validate() {
		$this->validate_country_is_present();
		$this->validate_zip_code_is_present();
		$this->validate_line_items_or_shipping_amount_are_present();
		$this->validate_zip_code_format();
	}

	/**
	 * Ensures ship to country is set.
	 *
	 * @throws Tax_Calculation_Exception When ship to country is not set.
	 */
	private function validate_country_is_present() {
		if ( empty( $this->get_to_country() ) ) {
			throw new Tax_Calculation_Exception(
				'missing_required_field_country',
				__( 'Country field is required to perform tax calculation.', 'taxjar' )
			);
		}
	}

	/**
	 * Ensures ship to zip code is set.
	 *
	 * @throws Tax_Calculation_Exception When ship to zip code is not set.
	 */
	private function validate_zip_code_is_present() {
		if ( empty( $this->get_to_zip() ) ) {
			throw new Tax_Calculation_Exception(
				'missing_required_field_zip',
				__( 'Zip code is required to perform tax calculation.', 'taxjar' )
			);
		}
	}

	/**
	 * Ensures either line items or a shipping amount is set.
	 *
	 * @throws Tax_Calculation_Exception When no line items or shipping amount has been set.
	 */
	private function validate_line_items_or_shipping_amount_are_present() {
		if ( empty( $this->get_line_items() ) && ( 0 === $this->get_shipping_amount() ) ) {
			throw new Tax_Calculation_Exception(
				'missing_required_field_line_item_or_shipping',
				__( 'Either a line item or shipping amount is required to calculate shipping.', 'taxjar' )
			);
		}
	}

	/**
	 * Ensures to zip code is a valid format for the country.
	 *
	 * @throws Tax_Calculation_Exception When zip code is not in a valid format.
	 */
	private function validate_zip_code_format() {
		if ( ! TaxJar_Tax_Calculation::is_postal_code_valid( $this->get_to_country(), $this->get_to_state(), $this->get_to_zip() ) ) {
			throw new Tax_Calculation_Exception(
				'invalid_field_zip',
				__( 'Invalid zip code. The to address zip code does not match the format required for the country.', 'taxjar' )
			);
		}
	}

	/**
	 * Gets json representation of request body.
	 *
	 * @return false|string
	 */
	public function to_json() {
		return wp_json_encode( $this->to_array() );
	}

	/**
	 * Converts request body to an array.
	 *
	 * @return mixed|void
	 */
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
			$request_body['customer_id'] = $this->get_customer_id();
		}

		if ( ! empty( $this->get_exemption_type() ) && TaxJar_Tax_Calculation::is_valid_exemption_type( $this->get_exemption_type() ) ) {
			$request_body['exemption_type'] = $this->get_exemption_type();
		}

		if ( empty( $this->get_line_items() ) ) {
			$request_body['amount'] = 0.0;
		} else {
			$request_body['line_items'] = $this->get_line_items();
		}

		return apply_filters( 'taxjar_tax_request_body', $request_body, $this );
	}

	/**
	 * Set ship to country code.
	 *
	 * @param string $to_country Ship to country code.
	 */
	public function set_to_country( $to_country ) {
		$this->to_country = $to_country;
	}

	/**
	 * Get ship to country code.
	 *
	 * @return string|null
	 */
	public function get_to_country() {
		return $this->to_country;
	}

	/**
	 * Set ship to state code.
	 *
	 * @param string $to_state Ship to state code.
	 */
	public function set_to_state( $to_state ) {
		$this->to_state = $to_state;
	}

	/**
	 * Get ship to state code.
	 *
	 * @return string|null
	 */
	public function get_to_state() {
		return $this->to_state;
	}

	/**
	 * Set ship to zip code.
	 *
	 * @param string $to_zip Ship to zip code.
	 */
	public function set_to_zip( $to_zip ) {
		$this->to_zip = $to_zip;
	}

	/**
	 * Get ship to zip code.
	 *
	 * @return string|null
	 */
	public function get_to_zip() {
		return $this->to_zip;
	}

	/**
	 * Set ship to city.
	 *
	 * @param string $to_city Ship to city.
	 */
	public function set_to_city( $to_city ) {
		$this->to_city = $to_city;
	}

	/**
	 * Get ship to city.
	 *
	 * @return string|null
	 */
	public function get_to_city() {
		return $this->to_city;
	}

	/**
	 * Set ship to street.
	 *
	 * @param string $to_street Ship to street.
	 */
	public function set_to_street( $to_street ) {
		$this->to_street = $to_street;
	}

	/**
	 * Get ship to street.
	 *
	 * @return string|null
	 */
	public function get_to_street() {
		return $this->to_street;
	}

	/**
	 * Set ship from country code.
	 *
	 * @param string $from_country Ship from country code.
	 */
	public function set_from_country( $from_country ) {
		$this->from_country = $from_country;
	}

	/**
	 * Get ship from country code.
	 *
	 * @return string|null
	 */
	public function get_from_country() {
		return $this->from_country;
	}

	/**
	 * Set ship from state code.
	 *
	 * @param string $from_state Ship from state code.
	 */
	public function set_from_state( $from_state ) {
		$this->from_state = $from_state;
	}

	/**
	 * Get ship from state code.
	 *
	 * @return string|null
	 */
	public function get_from_state() {
		return $this->from_state;
	}

	/**
	 * Set ship from zip code.
	 *
	 * @param string $from_zip Ship from zip code.
	 */
	public function set_from_zip( $from_zip ) {
		$this->from_zip = $from_zip;
	}

	/**
	 * Get ship from zip code.
	 *
	 * @return string|null
	 */
	public function get_from_zip() {
		return $this->from_zip;
	}

	/**
	 * Set ship from city.
	 *
	 * @param string $from_city Ship from city.
	 */
	public function set_from_city( $from_city ) {
		$this->from_city = $from_city;
	}

	/**
	 * Get ship from city.
	 *
	 * @return string|null
	 */
	public function get_from_city() {
		return $this->from_city;
	}

	/**
	 * Set ship from street.
	 *
	 * @param string $from_street Ship from street.
	 */
	public function set_from_street( $from_street ) {
		$this->from_street = $from_street;
	}

	/**
	 * Get ship from street.
	 *
	 * @return string|null
	 */
	public function get_from_street() {
		return $this->from_street;
	}

	/**
	 * Set total shipping amount.
	 *
	 * @param float $shipping_amount Total shipping amount.
	 */
	public function set_shipping_amount( $shipping_amount ) {
		$this->shipping_amount = $shipping_amount;
	}

	/**
	 * Get total shipping amount.
	 *
	 * @return float|null
	 */
	public function get_shipping_amount() {
		return $this->shipping_amount;
	}

	/**
	 * Set customer ID.
	 *
	 * @param integer $customer_id Customer ID.
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = $customer_id;
	}

	/**
	 * Get customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Set exemption type.
	 *
	 * @param string $exemption_type Exemption type.
	 */
	public function set_exemption_type( $exemption_type ) {
		$this->exemption_type = $exemption_type;
	}

	/**
	 * Get exemption type.
	 *
	 * @return string
	 */
	public function get_exemption_type() {
		return $this->exemption_type;
	}

	/**
	 * Add line item.
	 *
	 * @param array $line_item Line item.
	 */
	public function add_line_item( $line_item ) {
		$this->line_items[] = $line_item;
	}

	/**
	 * Get all line items.
	 *
	 * @return array
	 */
	public function get_line_items() {
		return $this->line_items;
	}
}

