<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Details {

	private $line_items;
	private $freight_taxable;
	private $has_nexus;
	private $shipping_tax_rate;
	private $rate;
	private $country;
	private $state;
	private $city;
	private $zip;
	private $raw_response;

	public function __construct( $tax_response ) {
		$this->raw_response = $tax_response;
		$this->has_nexus = $tax_response['tax']['has_nexus'];
		$this->freight_taxable = $tax_response['tax']['freight_taxable'];
		$this->add_line_items( $tax_response );
		$this->set_shipping_rate( $tax_response );
		$this->set_rate( $tax_response );
	}

	private function add_line_items( $tax_response ) {
		$this->line_items = array();

		if ( !empty( $tax_response['tax']['breakdown']['line_items'] ) ) {
			foreach( $tax_response['tax']['breakdown']['line_items'] as $response_line_item ) {
				$line_item = new TaxJar_Tax_Detail_Line_Item( $response_line_item );
				$this->line_items[ $line_item->get_id() ] = $line_item;
			}
		}
	}

	private function set_shipping_rate( $tax_response ) {
		if ( $this->is_shipping_taxable() ) {
			$this->set_shipping_rate_from_response( $tax_response );
		} else {
			$this->shipping_tax_rate = 0.0;
		}
	}

	private function set_shipping_rate_from_response( $tax_response ) {
		if ( $this->response_contains_shipping_rate( $tax_response ) ) {
			$this->shipping_tax_rate = $tax_response['tax']['breakdown']['shipping']['combined_tax_rate'];
		} else {
			$this->shipping_tax_rate = 0.0;
		}
	}

	private function response_contains_shipping_rate( $tax_response ) {
		return ! empty( $tax_response['tax']['breakdown']['shipping']['combined_tax_rate'] );
	}

	private function set_rate( $tax_response ) {
		if ( ! empty( $tax_response['tax']['rate'] ) ) {
			$this->rate = $tax_response['tax']['rate'];
		} else {
			$this->rate = 0.0;
		}
	}

	public function get_line_item( $id ) {
		if ( !empty ( $this->line_items[ $id ] ) ) {
			return $this->line_items[ $id ];
		}

		return false;
	}

	public function get_location() {
		return array(
			'country' => $this->get_country(),
			'state' => $this->get_state(),
			'zip' => $this->get_zip(),
			'city' => $this->get_city()
		);
	}

	public function has_nexus() {
		return true === $this->has_nexus;
	}

	public function is_shipping_taxable() {
		return true === $this->freight_taxable;
	}

	public function set_country( $country ) {
		$this->country = $country;
	}

	public function get_country() {
		return $this->country;
	}

	public function set_state( $state ) {
		$this->state = $state;
	}

	public function get_state() {
		return $this->state;
	}

	public function set_city( $city ) {
		$this->city = $city;
	}

	public function get_city() {
		return $this->city;
	}

	public function set_zip( $zip ) {
		$this->zip = $zip;
	}

	public function get_zip() {
		return $this->zip;
	}

	public function get_shipping_tax_rate() {
		return $this->shipping_tax_rate;
	}

	public function get_rate() {
		return $this->rate;
	}

	public function get_raw_response() {
		return $this->raw_response;
	}
}
