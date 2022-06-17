<?php
/**
 * Compatibility module for WooCommerce PDF Product Vouchers plugin
 *
 * @package TaxJar
 */

namespace TaxJar;

use TaxJar_Settings;
use WC_Cart;

/**
 * Class WooCommerce_PDF_Product_Vouchers
 */
class WooCommerce_PDF_Product_Vouchers extends Module {

	/**
	 * Determine if the module should be loaded
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return class_exists( 'WC_PDF_Product_Vouchers' );
	}

	/**
	 * Load module
	 *
	 * @return void
	 */
	public function load() {
		if ( ! TaxJar_Settings::is_tax_calculation_enabled() ) {
			return;
		}

		$redemption_handler = wc_pdf_product_vouchers()->get_redemption_handler_instance();

		remove_filter( 'woocommerce_calculated_total', array( $redemption_handler, 'apply_multi_purpose_vouchers_to_cart' ), 1100 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'apply_vouchers' ), 21 );
	}

	/**
	 * @param WC_Cart $cart cart
	 */
	public function apply_vouchers( $cart ) {
		$redemption_handler = wc_pdf_product_vouchers()->get_redemption_handler_instance();
		$total = $cart->get_total( 'edit' );
		$new_total = $redemption_handler->apply_multi_purpose_vouchers_to_cart( $total, $cart );
		$cart->set_total( max( 0, $new_total ) );
	}
}
