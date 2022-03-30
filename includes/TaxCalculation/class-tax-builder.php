<?php
/**
 * Tax Builder
 *
 * Builds tax arrays
 *
 * @package TaxJar
 */

namespace TaxJar;

use Exception;
use TaxJar_Settings;
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Builder
 */
class Tax_Builder {

	const TAX_RATE_ID = 999999999;

	/**
	 * Determines whether to create a WooCommerce tax rate during tax calculation.
	 *
	 * @var bool
	 */
	private $save_rates_enabled;

	/**
	 * Tax details.
	 *
	 * @var Tax_Details
	 */
	private $tax_details;

	/**
	 * Tax Builder constructor.
	 *
	 * @param Tax_Details $tax_details Tax details used to build line taxes.
	 */
	public function __construct( Tax_Details $tax_details ) {
		$this->tax_details        = $tax_details;
		$this->save_rates_enabled = TaxJar_Settings::is_save_rates_enabled();
	}

	/**
	 * Build WooCommerce tax rate
	 *
	 * @param float $rate_percent Rate percent of rate.
	 * @param string $tax_class Tax class.
	 *
	 * @return int|mixed
	 */
	public function build_woocommerce_tax_rate( $rate_percent, $tax_class = '' ) {
		if ( $this->save_rates_enabled ) {
			$woo_rate     = $this->persist_woocommerce_tax_rate( $rate_percent, $tax_class );
			return $woo_rate['id'];
		} else {
			return self::TAX_RATE_ID;
		}
	}

	/**
	 * Build line tax from tax details.
	 *
	 * @param string $tax_details_line_key Key of tax detail line item.
	 * @param integer $rate_id ID of WooCommerce tax rate.
	 *
	 * @return array
	 * @throws Exception When line item not found in tax details.
	 */
	public function get_line_tax( $tax_details_line_key, $rate_id ): array {
		$tax_details_line_item = $this->tax_details->get_line_item( $tax_details_line_key );
		$amount_collectable = $tax_details_line_item->get_tax_collectable();
		return array(
			$rate_id => wc_add_number_precision( $amount_collectable ),
		);
	}

	/**
	 * Creates a WooCommerce tax rate in the database.
	 *
	 * @param mixed  $rate_percent Tax rate.
	 * @param string $tax_class Tax class.
	 *
	 * @return array
	 */
	private function persist_woocommerce_tax_rate( $rate_percent, $tax_class = '' ) {
		return Rate_Manager::add_rate(
			$rate_percent,
			$tax_class,
			$this->tax_details->is_shipping_taxable(),
			$this->tax_details->get_location()
		);
	}

	/**
	 * Build the applied tax array using a tax rate.
	 *
	 * @param float  $applied_rate Tax rate to apply.
	 * @param float  $taxable_amount Taxable amount.
	 * @param integer $rate_id WooCommerce tax rate id.
	 *
	 * @return array
	 */
	public function build_line_tax_from_rate( $applied_rate, $taxable_amount, $rate_id ): array {
		$wc_rate  = $this->build_woocommerce_rate( $applied_rate * 100, $rate_id );
		return WC_Tax::calc_exclusive_tax( $taxable_amount, $wc_rate );
	}

	/**
	 * Builds a WooCommerce structured tax rate.
	 *
	 * @param float  $rate_percent Tax rate percent.
	 * @param mixed  $rate_id WooCommerce tax rate ID.
	 * @param string $shipping_taxable yes if shipping is taxable, no if not.
	 *
	 * @return array[]
	 */
	private function build_woocommerce_rate( $rate_percent, $rate_id = self::TAX_RATE_ID, $shipping_taxable = 'no' ): array {
		return array(
			$rate_id => array(
				'rate'     => $rate_percent,
				'label'    => '',
				'shipping' => $shipping_taxable,
				'compound' => 'no',
			),
		);
	}

	/**
	 * Builds tax for a shipping line.
	 *
	 * @param float $applied_rate Tax rate to apply.
	 * @param float $taxable_amount Taxable amount.
	 *
	 * @return array
	 */
	public function build_shipping_tax( $applied_rate, $taxable_amount ) {
		if ( ! empty( $applied_rate ) ) {
			$tax_rate_id = $this->build_woocommerce_tax_rate(
				$applied_rate * 100
			);
			$wc_rate  = $this->build_woocommerce_rate( $applied_rate * 100, $tax_rate_id );
		} else {
			$wc_rate = [];
		}

		return WC_Tax::calc_exclusive_tax( $taxable_amount, $wc_rate );
	}

}
