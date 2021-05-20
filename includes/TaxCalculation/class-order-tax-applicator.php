<?php

namespace TaxJar;

use Automattic\WooCommerce\Utilities\NumberUtil;
use WC_Tax, WC_Abstract_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Order_Tax_Applicator implements Tax_Applicator_Interface {

	private $order;
	private $tax_details;

	public function __construct( $order ) {
		$this->order = $order;
	}

	public function apply_tax( $tax_details ) {
		$this->tax_details = $tax_details;
		$this->remove_existing_tax();
		$this->apply_new_tax();
	}

	private function remove_existing_tax(){
		$this->order->remove_order_items( 'tax' );
	}

	private function apply_new_tax() {
		$this->apply_tax_to_line_items();
		$this->apply_tax_to_fees();
		$this->apply_tax_to_shipping_items();
		$this->order->update_taxes();
		$this->update_totals();
		$this->order->save();
	}

	private function apply_tax_to_line_items() {
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$this->create_rate_and_apply_to_product_line_item( $item_key, $item );
		}
	}

	private function create_rate_and_apply_to_product_line_item( $item_key, $item ) {
		$line_item_tax_rate = $this->get_product_line_item_tax_rate( $item_key, $item );
		$tax_class = $item->get_tax_class();
		$wc_rate = Rate_Manager::add_rate(
			$line_item_tax_rate,
			$tax_class,
			$this->tax_details->is_shipping_taxable(),
			$this->tax_details->get_location()
		);

		$tax_rates = $this->prepare_tax_rates_for_application( $wc_rate );
		$taxes = array(
			'total' => WC_Tax::calc_tax( $item->get_total(), $tax_rates, false ),
			'subtotal' => WC_Tax::calc_tax( $item->get_subtotal(), $tax_rates, false )
		);
		$item->set_taxes( $taxes );
	}

	private function prepare_tax_rates_for_application( $wc_rate ) {
		return array(
			$wc_rate['id'] => array(
				'rate'     => (float) $wc_rate['tax_rate'],
				'label'    => $wc_rate['tax_rate_name'],
				'shipping' => $wc_rate['tax_rate_shipping'] ? 'yes' : 'no',
				'compound' => 'no',
			)
		);
	}

	private function get_product_line_item_tax_rate( $item_key, $item ) {
		$product_id    = $item->get_product_id();
		$line_item_key = $product_id . '-' . $item_key;
		$tax_detail_line_item = $this->tax_details->get_line_item( $line_item_key );
		return 100 * $tax_detail_line_item->get_tax_rate();
	}

	private function apply_tax_to_fees() {
		foreach ( $this->order->get_items( 'fee' ) as $fee_key => $fee ) {
			$this->create_rate_and_apply_to_fee_line_item( $fee_key, $fee );
		}
	}

	private function create_rate_and_apply_to_fee_line_item( $fee_key, $fee ) {
		$fee_tax_rate = $this->get_tax_rate_for_fee_line_item( $fee_key, $fee );
		$tax_class = $fee->get_tax_class();
		$wc_rate = Rate_Manager::add_rate( $fee_tax_rate,
			$tax_class,
			$this->tax_details->is_shipping_taxable(),
			$this->tax_details->get_location()
		);

		$tax_rates = $this->prepare_tax_rates_for_application( $wc_rate );
		$taxes = array( 'total' => WC_Tax::calc_tax( $fee->get_total(), $tax_rates, false ) );
		$fee->set_taxes( $taxes );
	}

	private function get_tax_rate_for_fee_line_item( $fee_key, $fee ) {
		$fee_details_id = 'fee-' . $fee_key;
		$tax_detail_line_item = $this->tax_details->get_line_item( $fee_details_id );
		return 100 * $tax_detail_line_item->get_tax_rate();
	}

	private function apply_tax_to_shipping_items() {
		foreach ( $this->order->get_shipping_methods() as $item ) {
			$this->apply_tax_to_shipping_item( $item );
		}
	}

	private function apply_tax_to_shipping_item( $item ) {
		if ( $this->tax_details->is_shipping_taxable() ) {
			$tax_rate = 100 * $this->tax_details->get_shipping_tax_rate();
			$wc_rate = Rate_Manager::add_rate(
				$tax_rate,
				'',
				$this->tax_details->is_shipping_taxable(),
				$this->tax_details->get_location()
			);

			$tax_rates = $this->prepare_tax_rates_for_application( $wc_rate );
			$taxes = array( 'total' => WC_Tax::calc_tax( $item->get_total(), $tax_rates, false ) );
			$item->set_taxes( $taxes );
		} else {
			$this->apply_zero_tax_to_item( $item );
		}
	}

	private function apply_zero_tax_to_item( $item ) {
		$item->set_taxes( false );
	}

	private function update_totals() {
		$tax_sums = $this->sum_taxes();
		$this->order->set_discount_tax( wc_round_tax_total( $tax_sums['cart_subtotal_tax'] - $tax_sums['cart_total_tax'] ) );
		$this->order->set_total( NumberUtil::round( $this->get_order_total(), wc_get_price_decimals() ) );
	}

	private function sum_taxes() {
		$tax_sums = array(
			'cart_subtotal_tax' => 0,
			'cart_total_tax' => 0
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

	private function get_order_total() {
		$cart_total = $this->get_cart_total_for_order();
		$tax_total = $this->order->get_cart_tax() + $this->order->get_shipping_tax();
		$fees_total = $this->order->get_total_fees();
		$shipping_total = $this->order->get_shipping_total();
		return $cart_total + $tax_total + $fees_total + $shipping_total;
	}

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
