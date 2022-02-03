<?php
/**
 * Compatibility module for WooCommerce Smart Coupons plugin
 *
 * @package TaxJar
 */

namespace TaxJar;

/**
 * Class WooCommerce_Smart_Coupons
 */
class WooCommerce_Smart_Coupons extends Module {

	/**
	 * Determine if the module should be loaded
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return class_exists( 'WC_SC_Order_Fields' );
	}

	/**
	 * Load module
	 *
	 * @return void
	 */
	public function load() {
		add_filter( 'taxjar_order_total_amount', array( $this, 'add_gift_card_credits_to_total' ), 10, 2 );
	}

	/**
	 * Add credit used to order total
	 *
	 * @param int|float $total_amount Order total.
	 * @param \WC_Order $order Order.
	 *
	 * @return mixed
	 */
	public function add_gift_card_credits_to_total( $total_amount, $order ) {
		$smart_coupons_order_fields = \WC_SC_Order_Fields::get_instance();
		$total_credit_used          = $smart_coupons_order_fields->get_total_credit_used_in_order( $order );
		$total_amount              += $total_credit_used;

		return $total_amount;
	}
}
