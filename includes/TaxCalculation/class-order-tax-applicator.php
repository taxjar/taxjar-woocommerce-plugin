<?php
/**
 * Order Tax Applicator
 *
 * Applies tax details to order.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use Automattic\WooCommerce\Utilities\NumberUtil;
use WC_Order;
use WC_Order_Item_Product;
use WC_Tax, WC_Abstract_Order;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order_Tax_Applicator
 */
class Order_Tax_Applicator extends Tax_Applicator {

	/**
	 * Order to apply tax to.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Order_Tax_Applicator constructor.
	 *
	 * @param WC_Order $order Order to apply tax to.
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Remove existing tax on order.
	 */
	protected function remove_existing_tax() {
		$this->order->remove_order_items( 'tax' );
	}

	/**
	 * Apply new tax to order.
	 *
	 * @throws Exception If line item tax data not present in details.
	 */
	protected function apply_new_tax() {
		$this->remove_existing_tax();
		$this->apply_tax_to_line_items();
		$this->apply_tax_to_fees();
		$this->apply_tax_to_shipping_items();
		$this->order->update_taxes();
		$this->update_totals();
		$this->order->save();
	}

	/**
	 * Apply tax to order line items.
	 *
	 * @throws Exception If line item tax data not present in details.
	 */
	private function apply_tax_to_line_items() {
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$this->create_rate_and_apply_to_product_line_item( $item_key, $item );
		}
	}

	/**
	 * Create WooCommerce tax rate and apply it to product line item.
	 *
	 * @param integer               $item_key Index of line item.
	 * @param WC_Order_Item_Product $item Item to create rate for.
	 *
	 * @throws Exception If line item tax data not present in details.
	 */
	private function create_rate_and_apply_to_product_line_item( $item_key, $item ) {
		$line_item_key  = $item->get_product_id() . '-' . $item_key;
		$tax_details_line_item = $this->tax_details->get_line_item( $line_item_key );

		$rate_id = $this->tax_builder->build_woocommerce_tax_rate(
			$tax_details_line_item->get_tax_rate() * 100,
			$item->get_tax_class()
		);

		$total_taxes    = wc_remove_number_precision_deep( $this->tax_builder->get_line_tax( $line_item_key, $rate_id ) );
		$total_tax      = array_sum( $total_taxes );
		$applied_rate   = empty( $item->get_total() ) ? 0.0 : $total_tax / $item->get_total();
		$subtotal_taxes = $this->tax_builder->build_line_tax_from_rate( $applied_rate, $item->get_subtotal(), $rate_id );
		$taxes          = array(
			'total'    => $total_taxes,
			'subtotal' => $subtotal_taxes,
		);
		$item->set_taxes( $taxes );
	}

	/**
	 * Apply tax to order fees.
	 */
	private function apply_tax_to_fees() {
		foreach ( $this->order->get_items( 'fee' ) as $fee_key => $fee ) {
			$this->apply_fee_tax( $fee_key, $fee );
		}
	}

	/**
	 * Create WooCommerce tax rate and apply it to fee item.
	 *
	 * @param integer           $fee_key Index of fee item.
	 * @param WC_Order_Item_Fee $fee Fee to apply tax to.
	 */
	private function apply_fee_tax( $fee_key, $fee ) {
		$fee_details_id = 'fee-' . $fee_key;

		$tax_details_line_item = $this->tax_details->get_line_item( $fee_details_id );
		$rate_id = $this->tax_builder->build_woocommerce_tax_rate(
			$tax_details_line_item->get_tax_rate() * 100,
			$fee->get_tax_class()
		);

		$fee_taxes = wc_remove_number_precision_deep( $this->tax_builder->get_line_tax( $fee_details_id, $rate_id ) );
		$fee->set_taxes(
			array(
				'total' => $fee_taxes,
			)
		);
	}

	/**
	 * Apply tax to shipping items.
	 */
	private function apply_tax_to_shipping_items() {
		foreach ( $this->order->get_shipping_methods() as $item ) {
			$this->apply_tax_to_shipping_item( $item );
		}
	}

	/**
	 * Create WooCommerce tax rate for shipping and apply tax to a shipping item.
	 * If shipping is not taxable remove taxes from shipping item.
	 *
	 * @param WC_Order_Item_Shipping $item Shipping item to apply tax to.
	 */
	private function apply_tax_to_shipping_item( $item ) {
		if ( $this->tax_details->is_shipping_taxable() ) {
			$shipping_taxes = $this->tax_builder->build_shipping_tax( $this->tax_details->get_shipping_tax_rate(), $item->get_total() );
			$item->set_taxes( array( 'total' => $shipping_taxes ) );
		} else {
			$this->apply_zero_tax_to_item( $item );
		}
	}

	/**
	 * Removes tax from shipping item.
	 *
	 * @param WC_Order_Item_Shipping $item Shipping item to remove tax from.
	 */
	private function apply_zero_tax_to_item( $item ) {
		$item->set_taxes( false );
	}

	/**
	 * Update order totals after applying tax.
	 */
	private function update_totals() {
		$tax_sums = $this->sum_taxes();
		$this->order->set_discount_tax( wc_round_tax_total( $tax_sums['cart_subtotal_tax'] - $tax_sums['cart_total_tax'] ) );
		$this->order->set_total( NumberUtil::round( $this->get_order_total(), wc_get_price_decimals() ) );
	}

	/**
	 * Aggregate taxes applied to order.
	 *
	 * @return array
	 */
	private function sum_taxes() {
		$tax_sums = array(
			'cart_subtotal_tax' => 0,
			'cart_total_tax'    => 0,
		);

		foreach ( $this->order->get_items() as $item ) {
			$taxes = $item->get_taxes();

			foreach ( $taxes['total'] as $tax_rate_id => $tax ) {
				$tax_sums['cart_total_tax'] += (float) $tax;
			}

			foreach ( $taxes['subtotal'] as $tax_rate_id => $tax ) {
				$tax_sums['cart_subtotal_tax'] += (float) $tax;
			}
		}

		return $tax_sums;
	}

	/**
	 * Get order total.
	 *
	 * @return float
	 */
	private function get_order_total() {
		$cart_total     = $this->get_cart_total_for_order();
		$tax_total      = $this->order->get_cart_tax() + $this->order->get_shipping_tax();
		$fees_total     = $this->order->get_total_fees();
		$shipping_total = $this->order->get_shipping_total();
		return $cart_total + $tax_total + $fees_total + $shipping_total;
	}

	/**
	 * Get cart total (sum of item subtotals) of order.
	 *
	 * @return float
	 */
	private function get_cart_total_for_order() {
		$field = 'total';
		$items = array_map(
			function ( $item ) use ( $field ) {
				return wc_add_number_precision( $item[ $field ], false );
			},
			array_values( $this->order->get_items() )
		);

		return wc_remove_number_precision( WC_Abstract_Order::get_rounded_items_total( $items ) );
	}
}
