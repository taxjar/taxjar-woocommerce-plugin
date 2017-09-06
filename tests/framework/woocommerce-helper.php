<?php
class TaxJar_Woocommerce_Helper {

	public static function prepare_woocommerce() {
		global $woocommerce;
		global $wpdb;

		$woocommerce->product_factory = new WC_Product_Factory();
		$woocommerce->order_factory = new WC_Order_Factory();
		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		$woocommerce->session  = new $session_class();
		$woocommerce->cart = new WC_Cart();

		// Start with an empty cart
		$woocommerce->cart->empty_cart();
		$woocommerce->cart->remove_coupons();
		$woocommerce->shipping->shipping_total = 0;

		// Reset tax rates
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rates' );
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rate_locations' );

		// Create a default customer shipping address
		$woocommerce->customer = TaxJar_Customer_Helper::create_customer();

		// Set default tax classes
		// WooCommerce 3.2 checks for a valid class
		update_option( 'woocommerce_tax_classes', "Reduced rate\nZero Rate\nClothing Rate - 20010" );
	}

	public static function set_shipping_origin( $integration, $opts = array() ) {
		$current_settings = get_option( 'woocommerce_taxjar-integration_settings' );
		$new_settings = array_replace_recursive( $current_settings, $opts );

		update_option( 'woocommerce_taxjar-integration_settings', $new_settings );

		if ( isset( $opts['store_country'] ) && isset( $opts['store_state'] ) ) {
			update_option( 'woocommerce_default_country', $opts['store_country'] . ':' . $opts['store_state'] );
			$integration->init_settings();
		}
	}

}
