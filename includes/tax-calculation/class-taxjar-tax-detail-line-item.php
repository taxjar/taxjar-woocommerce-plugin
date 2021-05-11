<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Detail_Line_Item {

	private $id;
	private $combined_tax_rate;
	private $tax_collectable;
	private $taxable_amount;

	public function __construct( $response_line_item ) {
		$this->id = $response_line_item->id;
		$this->combined_tax_rate = $response_line_item->combined_tax_rate;
		$this->tax_collectable = $response_line_item->tax_collectable;
		$this->taxable_amount = $response_line_item->taxable_amount;
	}

	public function get_id() {
		return $this->id;
	}

	public function get_tax_rate() {
		if ( 0.0 === floatval( $this->tax_collectable ) ) {
			return 0;
		}

		return $this->combined_tax_rate;
	}

	public function get_taxable_amount() {
		return $this->taxable_amount;
	}
}
