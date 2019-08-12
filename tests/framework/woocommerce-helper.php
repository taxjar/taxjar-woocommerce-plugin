<?php
class TaxJar_Woocommerce_Helper {

	public static function prepare_woocommerce() {
		global $wpdb;

		// Start with an empty cart
		WC()->cart->empty_cart();
		WC()->session->set( 'cart', null );
		WC()->session->set( 'cart_totals', null );
		WC()->session->set( 'applied_coupons', null );
		WC()->session->set( 'coupon_discount_totals', null );
		WC()->session->set( 'coupon_discount_tax_totals', null );
		WC()->session->set( 'removed_cart_contents', null );
		WC()->session->set( 'order_awaiting_payment', null );
		WC()->session->set( 'customer', null );
		WC()->session->set( 'chosen_shipping_methods', array() );
		WC()->cart->remove_coupons();
		WC()->shipping->shipping_total = 0;
		WC()->shipping->reset_shipping();

		// Ensure default is tax based on shipping
		update_option( 'woocommerce_tax_based_on', 'shipping' );

		// Reset tax rates
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rates' );
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rate_locations' );

		// Create a default customer shipping address
		WC()->customer = TaxJar_Customer_Helper::create_customer();

		// Set default tax classes
		// WooCommerce 3.2 checks for a valid class
		update_option( 'woocommerce_tax_classes', "Reduced rate\nZero Rate\nClothing Rate - 20010" );

		if ( version_compare( WC()->version, '3.7.0', '>=' ) ) {
			WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
		}

		// Allow calculate_totals to run in specs for WooCommerce < 3.2
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
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
