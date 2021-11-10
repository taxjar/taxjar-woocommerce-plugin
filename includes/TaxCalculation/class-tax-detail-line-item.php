<?php
/**
 * Tax Details for Single Line Item
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Detail_Line_Item
 */
class Tax_Detail_Line_Item {

	/**
	 * Line item ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Tax rate for line item.
	 *
	 * @var float
	 */
	private $combined_tax_rate;

	/**
	 * Amount of tax to be collected on line item.
	 *
	 * @var float
	 */
	private $tax_collectable;

	/**
	 * Total taxable amount of line item.
	 *
	 * @var float
	 */
	private $taxable_amount;

	/**
	 * Tax_Detail_Line_Item constructor.
	 *
	 * @param mixed $response_line_item Line item from TaxJar API response.
	 */
	public function __construct( $response_line_item ) {
		$this->id                = $response_line_item->id;
		$this->combined_tax_rate = $response_line_item->combined_tax_rate;
		$this->tax_collectable   = $response_line_item->tax_collectable;
		$this->taxable_amount    = $response_line_item->taxable_amount;
	}

	/**
	 * Get line item ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get line item tax rate or zero if no tax should be collected.
	 *
	 * @return float|int
	 */
	public function get_tax_rate() {
		if ( 0.0 === floatval( $this->tax_collectable ) ) {
			return 0;
		}

		return $this->combined_tax_rate;
	}

	/**
	 * Get line item taxable amount.
	 *
	 * @return float
	 */
	public function get_taxable_amount() {
		return $this->taxable_amount;
	}

	/**
	 * Get line item tax collectable.
	 *
	 * @return float
	 */
	public function get_tax_collectable() {
		return $this->tax_collectable;
	}
}

