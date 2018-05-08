<?php
class TaxJar_Woocommerce_Helper {

	public static function prepare_woocommerce() {
		global $wpdb;

		WC()->product_factory = new WC_Product_Factory();
		WC()->order_factory = new WC_Order_Factory();
		$session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
		WC()->session  = new $session_class();
		WC()->cart = new WC_Cart();
		WC()->countries = new WC_Countries();

		// Start with an empty cart
		WC()->cart->empty_cart();
		WC()->cart->remove_coupons();
		WC()->shipping->shipping_total = 0;

		// Reset tax rates
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rates' );
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rate_locations' );

		// Create a default customer shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer();

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
