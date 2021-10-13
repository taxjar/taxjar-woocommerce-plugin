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
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Builder
 */
class Tax_Builder {

	/**
	 * Build line tax from tax details.
	 *
	 * @param string      $tax_details_line_key Key of tax detail line item.
	 * @param Tax_Details $tax_details Tax details.
	 *
	 * @return array
	 * @throws Exception When line item not found in tax details.
	 */
	public static function build_line_tax( $tax_details_line_key, Tax_Details $tax_details ): array {
		$tax_details_line_item = self::get_line_item_with_key( $tax_details_line_key, $tax_details );
		$amount_collectable    = $tax_details_line_item->get_tax_collectable();
		return self::build_applied_tax_array( 0, $amount_collectable );
	}

	/**
	 * Gets the tax detail line item using the given key.
	 *
	 * @param string      $tax_details_line_key Key of tax detail line item.
	 * @param Tax_Details $tax_details Tax details.
	 *
	 * @return Tax_Detail_Line_Item
	 * @throws Exception When line item not found in tax details.
	 */
	private static function get_line_item_with_key( $tax_details_line_key, $tax_details ): Tax_Detail_Line_Item {
		$tax_detail_line_item = $tax_details->get_line_item( $tax_details_line_key );

		if ( false === $tax_detail_line_item ) {
			throw new Exception( 'Line item not present in tax details.' );
		}

		return $tax_detail_line_item;
	}

	/**
	 * Build the applied tax array.
	 *
	 * @param int   $rate_id WooCommerce tax rate ID, 0 when not creating WooCommerce rates.
	 * @param float $tax_amount Amount of tax to apply.
	 *
	 * @return array
	 */
	private static function build_applied_tax_array( int $rate_id, $tax_amount ): array {
		return array(
			$rate_id => wc_add_number_precision( $tax_amount ),
		);
	}

	/**
	 * Build the applied tax array using a tax rate.
	 *
	 * @param float $applied_rate Tax rate to apply.
	 * @param float $taxable_amount Taxable amount.
	 *
	 * @return array
	 */
	public static function build_line_tax_from_rate( $applied_rate, $taxable_amount ): array {
		$wc_rate = self::build_woocommerce_rate( $applied_rate * 100 );
		return WC_Tax::calc_exclusive_tax( $taxable_amount, $wc_rate );
	}

	/**
	 * Builds a WooCommerce structured tax rate.
	 *
	 * @param float  $rate Tax rate.
	 * @param string $shipping_taxable yes if shipping is taxable, no if not.
	 *
	 * @return array[]
	 */
	private static function build_woocommerce_rate( $rate, $shipping_taxable = 'no' ): array {
		return array(
			0 => array(
				'rate'     => $rate,
				'label'    => '',
				'shipping' => $shipping_taxable,
				'compound' => 'no',
			),
		);
	}

}
