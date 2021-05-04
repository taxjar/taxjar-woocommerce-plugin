<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Tax_Applicator {

	private $order;
	private $tax_details;

	public function __construct( $order, $tax_details ) {
		$this->order = $order;
		$this->tax_details = $tax_details;
	}

	public function apply_tax_and_recalculate() {
		$this->apply_tax();
		$this->order->calculate_totals();
	}

	public function apply_tax() {
		$this->remove_existing_tax();
		$this->apply_new_tax();
	}

	private function remove_existing_tax(){
		$this->order->remove_order_items( 'tax' );
	}

	private function apply_new_tax() {
		$this->apply_tax_to_line_items();
		$this->apply_tax_to_fees();
		$this->maybe_apply_tax_to_shipping();
	}

	private function apply_tax_to_line_items() {
		foreach ( $this->order->get_items() as $item_key => $item ) {
			$this->apply_line_item_tax( $item_key, $item );
		}
	}

	private function apply_line_item_tax( $item_key, $item ) {
		$product_id    = $item->get_product_id();
		$line_item_key = $product_id . '-' . $item_key;
		$tax_detail_line_item = $this->tax_details->get_line_item( $line_item_key );
		$tax_rate = 100 * $tax_detail_line_item->get_tax_rate();

		$tax_class = $item->get_tax_class();
		$rate_id = $this->create_or_update_tax_rate( $tax_rate, $tax_class, $this->tax_details->is_shipping_taxable() );
		$item_tax = new WC_Order_Item_Tax();
		$item_tax->set_rate( $rate_id );
		$item_tax->set_order_id( $this->order->get_id() );
		$item_tax->save();
	}

	private function apply_tax_to_fees() {
		foreach ( $this->order->get_items( 'fee' ) as $fee_key => $fee ) {
			$this->apply_fee_tax( $fee_key, $fee );
		}
	}

	private function apply_fee_tax( $fee_key, $fee ) {
		$fee_details_id = 'fee-' . $fee_key;
		$tax_detail_line_item = $this->tax_details->get_line_item( $fee_details_id );
		$tax_rate = 100 * $tax_detail_line_item->get_tax_rate();

		$tax_class = $fee->get_tax_class();
		$rate_id = $this->create_or_update_tax_rate( $tax_rate, $tax_class, $this->tax_details->is_shipping_taxable() );
		$item_tax = new WC_Order_Item_Tax();
		$item_tax->set_rate( $rate_id );
		$item_tax->set_order_id( $this->order->get_id() );
		$item_tax->save();
	}

	private function maybe_apply_tax_to_shipping() {
		if ( $this->tax_details->is_shipping_taxable() ) {
			$tax_rate = 100 * $this->tax_details->get_shipping_tax_rate();
			$this->create_or_update_tax_rate( $tax_rate, '', $this->tax_details->is_shipping_taxable() );
		}
	}

	private function create_or_update_tax_rate( $rate, $tax_class, $freight_taxable = 1 ) {
		$tax_rate = $this->build_tax_rate( $rate, $tax_class, $freight_taxable );
		$wc_rate = $this->get_existing_rate( $tax_class );

		if ( ! empty( $wc_rate ) ) {
			$rate_id = $this->update_tax_rate( key( $wc_rate ), $tax_rate );
		} else {
			$rate_id = $this->create_tax_rate( $tax_rate );
		}

		return $rate_id;
	}

	private function build_tax_rate( $rate, $tax_class, $freight_taxable ) {
		return array(
			'tax_rate_country'  => $this->tax_details->get_country(),
			'tax_rate_state'    => $this->tax_details->get_state(),
			'tax_rate_name'     => sprintf( '%s Tax', $this->tax_details->get_state() ),
			'tax_rate_priority' => 1,
			'tax_rate_compound' => false,
			'tax_rate_shipping' => $freight_taxable,
			'tax_rate'          => $rate,
			'tax_rate_class'    => $tax_class,
		);
	}

	private function get_existing_rate( $tax_class ) {
		$rate_lookup = array(
			'country'   => $this->tax_details->get_country(),
			'state'     => sanitize_key( $this->tax_details->get_state() ),
			'postcode'  => $this->tax_details->get_zip(),
			'city'      => $this->tax_details->get_city(),
			'tax_class' => $tax_class,
		);
		$wc_rate = WC_Tax::find_rates( $rate_lookup );
		return $wc_rate;
	}

	private function update_tax_rate( $rate_id, $tax_rate ) {
		WC_Tax::_update_tax_rate( $rate_id, $tax_rate );
		return $rate_id;
	}

	private function create_tax_rate( $tax_rate ) {
		$rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
		WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $this->tax_details->get_zip() ) );
		WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $this->tax_details->get_city() ) );
		return $rate_id;
	}
}
