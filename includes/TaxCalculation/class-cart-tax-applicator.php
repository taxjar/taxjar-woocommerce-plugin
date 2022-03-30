<?php
/**
 * Cart Tax Applicator
 *
 * Applies tax details to cart.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Cart;
use WC_Tax;
use Exception;

/**
 * Class Cart_Tax_Applicator
 */
class Cart_Tax_Applicator extends Tax_Applicator {

	/**
	 * Cart to apply tax to.
	 *
	 * @var WC_Cart
	 */
	private $cart;

	/**
	 * Tax applied to items.
	 *
	 * @var array
	 */
	private $items_taxes = array();

	/**
	 * Tax applied to shipping.
	 *
	 * @var array
	 */
	private $shipping_taxes = array();

	/**
	 * Tax applied to fees.
	 *
	 * @var array
	 */
	private $fee_taxes = array();

	/**
	 * Cart_Tax_Applicator constructor.
	 *
	 * @param WC_Cart $cart Cart to apply tax to.
	 */
	public function __construct( WC_Cart $cart ) {
		$this->cart = $cart;
	}

	/**
	 * Applies TaxJar tax rates to cart.
	 */
	protected function apply_new_tax() {
		$this->apply_tax_to_cart_contents();
		$this->apply_shipping_tax();
		$this->apply_fee_taxes();
		$this->calculate_totals();
	}

	/**
	 * Applies TaxJar tax rates to cart contents (line items).
	 */
	private function apply_tax_to_cart_contents() {
		$merged_subtotal_taxes = array();
		$merged_total_taxes    = array();

		foreach ( $this->cart->get_cart() as $item_key => $item ) {
			$tax_detail_line_key = $item['data']->get_id() . '-' . $item_key;
			$tax_details_line_item = $this->tax_details->get_line_item( $tax_detail_line_key );

			$rate_id = $this->tax_builder->build_woocommerce_tax_rate(
				$tax_details_line_item->get_tax_rate() * 100,
				$item['data']->get_tax_class()
			);

			$total_taxes           = $this->apply_line_total_tax( $item_key, $tax_detail_line_key, $rate_id );
			$applied_rate          = empty( $item['line_total'] ) ? 0.0 : reset( $total_taxes ) / wc_add_number_precision( $item['line_total'] );
			$subtotal_taxes        = $this->apply_line_subtotal_tax( $item_key, $item, $applied_rate, $rate_id );

			$merged_subtotal_taxes = $this->merge_tax_arrays( $merged_subtotal_taxes, $subtotal_taxes );
			$merged_total_taxes    = $this->merge_tax_arrays( $merged_total_taxes, $total_taxes );
		}

		$this->items_taxes = $merged_total_taxes;
		$this->cart->set_subtotal_tax( wc_remove_number_precision( array_sum( $merged_subtotal_taxes ) ) );
		$this->cart->set_cart_contents_tax( array_sum( wc_remove_number_precision_deep( $merged_total_taxes ) ) );
		$this->cart->set_cart_contents_taxes( wc_remove_number_precision_deep( $merged_total_taxes ) );
	}

	/**
	 * Applies WooCommerce structured tax rate to line subtotals.
	 *
	 * @param string $item_key key of item in cart.
	 * @param array  $item item in cart.
	 * @param float  $applied_rate Tax rate applied to line total.
	 * @param integer $rate_id WooCommerce tax rate id.
	 *
	 * @return array
	 */
	private function apply_line_subtotal_tax( $item_key, $item, $applied_rate, $rate_id ): array {
		$item_subtotal  = wc_add_number_precision( $item['line_subtotal'], false );
		$subtotal_taxes = $this->tax_builder->build_line_tax_from_rate( $applied_rate, $item_subtotal, $rate_id );
		$subtotal_tax   = array_sum( array_map( array( $this, 'round_line_tax' ), $subtotal_taxes ) );
		$this->cart->cart_contents[ $item_key ]['line_tax_data']['subtotal'] = wc_remove_number_precision_deep( $subtotal_taxes );
		$this->cart->cart_contents[ $item_key ]['line_subtotal_tax']         = wc_remove_number_precision( $subtotal_tax );
		return $subtotal_taxes;
	}

	/**
	 * Applies WooCommerce structure tax rate to line totals.
	 *
	 * @param string $item_key key of item in cart.
	 * @param string $tax_detail_line_key Key of tax detail line item.
	 * @param integer $rate_id WooCommerce tax rate id.
	 *
	 * @return array
	 * @throws Exception
	 */
	private function apply_line_total_tax( $item_key, $tax_detail_line_key, $rate_id ): array {
		$total_taxes         = $this->tax_builder->get_line_tax( $tax_detail_line_key, $rate_id );
		$total_tax           = array_sum( array_map( array( $this, 'round_line_tax' ), $total_taxes ) );
		$this->cart->cart_contents[ $item_key ]['line_tax_data']['total'] = wc_remove_number_precision_deep( $total_taxes );
		$this->cart->cart_contents[ $item_key ]['line_tax']               = wc_remove_number_precision( $total_tax );
		return $total_taxes;
	}

	/**
	 * Rounds value
	 *
	 * @param double $value value to round.
	 *
	 * @return float
	 */
	private function round_line_tax( $value ): float {
		return wc_round_tax_total( $value, 0 );
	}

	/**
	 * Merge applied tax amounts based on rate_id.
	 *
	 * @param array ...$tax_arrays Applied taxes to be merged.
	 *
	 * @return array
	 */
	private function merge_tax_arrays( ...$tax_arrays ): array {
		$merged_taxes = array();
		foreach ( $tax_arrays as $taxes ) {
			foreach ( $taxes as $rate_id => $single_rate ) {
				if ( ! isset( $merged_taxes[ $rate_id ] ) ) {
					$merged_taxes[ $rate_id ] = 0;
				}
				$merged_taxes[ $rate_id ] += $this->round_line_tax( $single_rate );
			}
		}
		return $merged_taxes;
	}

	/**
	 * Applies TaxJar rates to shipping taxes.
	 */
	private function apply_shipping_tax() {
		$packages              = WC()->shipping()->get_packages();
		$chosen_methods        = WC()->session->get( 'chosen_shipping_methods', array() );
		$merged_shipping_taxes = array();

		foreach ( $packages as $package_key => $package ) {
			$chosen_method = $chosen_methods[$package_key] ?? null;
			if ( $chosen_method && isset($package['rates'][ $chosen_method ]) ) {
				$package_rate  = $package['rates'][ $chosen_method ];

				$taxes = $this->tax_builder->build_shipping_tax(
					$this->tax_details->get_shipping_tax_rate(),
					$package_rate->get_cost()
				);

				$package_rate->set_taxes( $taxes );
				$merged_shipping_taxes = $this->merge_tax_arrays( $merged_shipping_taxes, wc_add_number_precision_deep( $taxes, false ) );
			}
		}

		$this->shipping_taxes = $merged_shipping_taxes;
		$this->cart->set_shipping_tax( wc_remove_number_precision( array_sum( $merged_shipping_taxes ) ) );
		$this->cart->set_shipping_taxes( wc_remove_number_precision_deep( $merged_shipping_taxes ) );
	}

	/**
	 * Applies TaxJar rates to fee taxes.
	 */
	private function apply_fee_taxes() {
		$merged_fee_taxes = array();
		$negative_fees    = array();

		foreach ( $this->cart->get_fees() as $fee_key => $fee ) {
			if ( 0 > $fee->total ) {
				$negative_fees[ $fee_key ] = $fee;
				continue;
			}

			$tax_details_line_item = $this->tax_details->get_line_item( $fee->id );
			$tax_rate_id = $this->tax_builder->build_woocommerce_tax_rate(
				$tax_details_line_item->get_tax_rate() * 100,
				$fee->tax_class
			);

			$fee_taxes        = $this->apply_tax_to_fee( $fee, $tax_rate_id );
			$merged_fee_taxes = $this->merge_tax_arrays( $merged_fee_taxes, $fee_taxes );
		}

		foreach ( $negative_fees as $fee ) {
			$fee_taxes        = $this->apply_tax_to_negative_fee( $fee, $merged_fee_taxes );
			$merged_fee_taxes = $this->merge_tax_arrays( $merged_fee_taxes, $fee_taxes );
		}

		$this->fee_taxes = $merged_fee_taxes;
		$this->cart->set_fee_tax( wc_remove_number_precision( array_sum( $merged_fee_taxes ) ) );
		$this->cart->set_fee_taxes( wc_remove_number_precision_deep( $merged_fee_taxes ) );
	}

	/**
	 * Applies taxes to non-negative fees.
	 *
	 * @param object $fee Fee to apply tax to.
	 * @param integer $rate_id WooCommerce tax rate id.
	 *
	 * @return array
	 * @throws Exception When no line item is found on the tax details for the given key.
	 */
	private function apply_tax_to_fee( $fee, $rate_id ): array {
		$fee_taxes     = $this->tax_builder->get_line_tax( $fee->id, $rate_id );
		$total_tax     = array_sum( array_map( array( $this, 'round_line_tax' ), $fee_taxes ) );
		$fee->tax_data = wc_remove_number_precision_deep( $fee_taxes );
		$fee->tax      = wc_remove_number_precision( $total_tax );
		return $fee_taxes;
	}

	/**
	 * Applies taxes to negative fees.
	 * To maintain parity with WooCommerce native calculations, tax on negative fees must be distributed evenly
	 * according to the rates that were applied to the items, fees and shipping on the cart.
	 *
	 * @param object $fee Fee to apply rates to.
	 * @param array  $merged_fee_taxes All tax applied to positive fees.
	 *
	 * @return array
	 */
	private function apply_tax_to_negative_fee( $fee, $merged_fee_taxes ): array {
		if ( $fee->taxable ) {
			// When fee is negative and taxable, tax rate is the average rate of the cart
			// This ensures the tax "discount" created by the negative fee is evenly distributed.
			$rate = $this->tax_details->get_rate();
		} else {
			$rate = 0.0;
		}

		$tax_rate_id = $this->tax_builder->build_woocommerce_tax_rate(
			$rate * 100,
			$fee->tax_class
		);

		$total_taxes = $this->merge_tax_arrays( $this->items_taxes, $this->shipping_taxes, $merged_fee_taxes );
		$fee_taxes   = $this->tax_builder->build_line_tax_from_rate(
			$rate,
			wc_add_number_precision_deep( $fee->total, false ),
			$tax_rate_id
		);

		// Negative tax distribution must be prevented from being greater than the total amount of applied tax.
		if ( array_sum( $fee_taxes ) * -1 > array_sum( $total_taxes ) ) {
			foreach ( $fee_taxes as $fee_key => $fee_tax ) {
				$fee_taxes[ $fee_key ] = array_sum( $total_taxes ) * -1;
			}
		}

		$total_tax     = array_sum( array_map( array( $this, 'round_line_tax' ), $fee_taxes ) );
		$fee->tax_data = wc_remove_number_precision_deep( $fee_taxes );
		$fee->tax      = wc_remove_number_precision( $total_tax );
		return $fee_taxes;
	}

	/**
	 * Recalculates some totals on the cart.
	 */
	private function calculate_totals() {
		$items_total    = $this->cart->get_cart_contents_total();
		$shipping_total = $this->cart->get_shipping_total();
		$fee_total      = $this->cart->get_fee_total();
		$taxes          = $this->merge_tax_arrays( $this->items_taxes, $this->fee_taxes, $this->shipping_taxes );
		$tax_total      = array_sum( wc_remove_number_precision_deep( $taxes ) );
		$total          = $items_total + $shipping_total + $fee_total + $tax_total;

		$this->cart->set_total_tax( $tax_total );
		$this->cart->set_total( max( 0, $total ) );

		/**
		 * Fires once the cart totals calculation are done.
		 *
		 * @since 4.1.1
		 *
		 * @param WC_Cart $this->cart Cart.
		 */
		do_action( 'taxjar_after_calculate_cart_totals', $this->cart );
	}

}
