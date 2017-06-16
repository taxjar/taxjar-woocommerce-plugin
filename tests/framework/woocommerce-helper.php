<?php
class TaxJar_Woocommerce_Helper {

	public static function prepare_woocommerce() {
		global $woocommerce;

		$woocommerce->product_factory = new WC_Product_Factory();
		$woocommerce->order_factory = new WC_Order_Factory();
		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		$woocommerce->session  = new $session_class();
		$woocommerce->cart = new WC_Cart();

		// Start with an empty cart
		$woocommerce->cart->empty_cart();
		$woocommerce->shipping->shipping_total = 0;

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( array(
			'store_zip' => '80111',
			'store_city' => 'Greenwood Village',
		) );

		// Create a default customer shipping address
		$woocommerce->customer = TaxJar_Customer_Helper::create_customer();
	}

	public static function set_shipping_origin( $opts = array() ) {
		$current_settings = get_option( 'woocommerce_taxjar-integration_settings' );
		$new_settings = array_replace_recursive( $current_settings, $opts );
		update_option( 'woocommerce_taxjar-integration_settings', $new_settings );
	}

}
