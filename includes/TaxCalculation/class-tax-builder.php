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
	 * Build line tax from tax details.
	 *
	 * @param string $tax_details_line_key Key of tax detail line item.
	 * @param string $tax_class Tax class.
	 *
	 * @return array
	 * @throws Exception When line item not found in tax details.
	 */
	public function get_line_tax( $tax_details_line_key, $tax_class = '' ): array {
		$tax_details_line_item = $this->get_line_item_with_key( $tax_details_line_key );

		if ( $this->save_rates_enabled ) {
			$rate_percent = $tax_details_line_item->get_tax_rate() * 100;
			$woo_rate     = $this->create_woocommerce_rate( $rate_percent, $tax_class );
			return $this->build_line_tax( $tax_details_line_item, $woo_rate['id'] );
		} else {
			return $this->build_line_tax( $tax_details_line_item );
		}
	}

	/**
	 * Builds tax for a line item.
	 *
	 * @param Tax_Detail_Line_Item $tax_details_line_item Tax details line item.
	 * @param int                  $rate_id Rate ID of WooCommerce tax rate.
	 *
	 * @return array
	 */
	private function build_line_tax( Tax_Detail_Line_Item $tax_details_line_item, $rate_id = self::TAX_RATE_ID ) {
		$amount_collectable = $tax_details_line_item->get_tax_collectable();
		return $this->build_applied_tax_array( $rate_id, $amount_collectable );
	}

	/**
	 * Creates a WooCommerce tax rate in the database.
	 *
	 * @param mixed  $rate_percent Tax rate.
	 * @param string $tax_class Tax class.
	 *
	 * @return array
	 */
	private function create_woocommerce_rate( $rate_percent, $tax_class = '' ) {
		return Rate_Manager::add_rate(
			$rate_percent,
			$tax_class,
			$this->tax_details->is_shipping_taxable(),
			$this->tax_details->get_location()
		);
	}

	/**
	 * Gets the tax detail line item using the given key.
	 *
	 * @param string $tax_details_line_key Key of tax detail line item.
	 *
	 * @return Tax_Detail_Line_Item
	 * @throws Exception When line item not found in tax details.
	 */
	private function get_line_item_with_key( $tax_details_line_key ): Tax_Detail_Line_Item {
		$tax_detail_line_item = $this->tax_details->get_line_item( $tax_details_line_key );

		if ( false === $tax_detail_line_item ) {
			throw new Exception( 'Line item not present in tax details.' );
		}

		return $tax_detail_line_item;
	}

	/**
	 * Build the applied tax array.
	 *
	 * @param mixed $rate_id WooCommerce tax rate ID.
	 * @param float $tax_amount Amount of tax to apply.
	 *
	 * @return array
	 */
	private function build_applied_tax_array( $rate_id, $tax_amount ): array {
		return array(
			$rate_id => wc_add_number_precision( $tax_amount ),
		);
	}

	/**
	 * Build the applied tax array using a tax rate.
	 *
	 * @param float  $applied_rate Tax rate to apply.
	 * @param float  $taxable_amount Taxable amount.
	 * @param string $tax_class Tax class.
	 * @param bool   $prevent_save Prevent saving rate to WooCommerce
	 *
	 * @return array
	 */
	public function build_line_tax_from_rate( $applied_rate, $taxable_amount, $tax_class = '', $prevent_save = false ): array {
		if ( $this->save_rates_enabled && $prevent_save !== true ) {
			$woo_rate = $this->create_woocommerce_rate( $applied_rate * 100, $tax_class );
			$wc_rate  = $this->build_woocommerce_rate( $applied_rate * 100, $woo_rate['id'] );
		} else {
			$wc_rate = $this->build_woocommerce_rate( $applied_rate * 100 );
		}

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
		if ( $this->save_rates_enabled ) {
			if ( ! empty( $applied_rate ) ) {
				$woo_rate = $this->create_woocommerce_rate( $applied_rate * 100 );
				$wc_rate  = $this->build_woocommerce_rate( $applied_rate * 100, $woo_rate['id'] );
			} else {
				$wc_rate = [];
			}
		} else {
			$wc_rate = $this->build_woocommerce_rate( $applied_rate * 100 );
		}

		return WC_Tax::calc_exclusive_tax( $taxable_amount, $wc_rate );
	}

}
