<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Details {

	private $amount_to_collect;
	private $combined_tax_rate;
	private $line_items;
	private $freight_taxable;
	private $has_nexus;

	public function __construct( $tax_response ) {
		$this->amount_to_collect = $tax_response['tax']['amount_to_collect'];
		$this->combined_tax_rate = $tax_response['tax']['breakdown']['combined_tax_rate'];
		$this->freight_taxable = $tax_response['tax']['freight_taxable'];
		$this->has_nexus = $tax_response['tax']['has_nexus'];
		$this->add_line_items( $tax_response );
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

	public function get_line_item( $id ) {
		if ( !empty ( $this->line_items[ $id ] ) ) {
			return $this->line_items[ $id ];
		}

		return false;
	}

	public function has_nexus() {
		return true === $this->has_nexus;
	}

	public function is_shipping_taxable() {
		return true === $this->freight_taxable;
	}
}
