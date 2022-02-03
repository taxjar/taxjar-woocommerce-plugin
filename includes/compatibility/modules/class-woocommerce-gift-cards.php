<?php
/**
 * Compatibility module for WooCommerce Gift Cards plugin
 *
 * @package TaxJar
 */

namespace TaxJar;

/**
 * Class WooCommerce_Gift_Cards
 */
class WooCommerce_Gift_Cards extends Module {

	/**
	 * Determine if the module should be loaded
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return class_exists( 'WC_Gift_Cards' );
	}

	/**
	 * Load module
	 *
	 * @return void
	 */
	public function load() {
		add_filter( 'taxjar_order_total_amount', array( $this, 'add_gift_card_usage_to_total' ), 10, 2 );
	}

	/**
	 * Add gift card amount used to order total
	 *
	 * @param int|float $total_amount Order total.
	 * @param \WC_Order $order Order.
	 *
	 * @return mixed
	 */
	public function add_gift_card_usage_to_total( $total_amount, $order ) {
		$giftcards_total = WC_GC()->order->get_gift_cards( $order );
		if ( $giftcards_total['total'] > 0 ) {
			$total_amount = $total_amount + $giftcards_total['total'];
		}

		return $total_amount;
	}
}
