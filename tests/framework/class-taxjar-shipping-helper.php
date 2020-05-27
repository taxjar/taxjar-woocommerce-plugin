<?php
class TaxJar_Shipping_Helper {

	public static function create_simple_flat_rate( $cost = 10 ) {
		$flat_rate_settings = array(
			'enabled'      => 'yes',
			'title'        => 'Flat rate',
			'availability' => 'all',
			'countries'    => '',
			'tax_status'   => 'taxable',
			'cost'         => $cost,
		);

		update_option( 'woocommerce_flat_rate_settings', $flat_rate_settings );
		update_option( 'woocommerce_flat_rate', array() );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping->load_shipping_methods();
	}

	public static function delete_simple_flat_rate() {
		delete_option( 'woocommerce_flat_rate_settings' );
		delete_option( 'woocommerce_flat_rate' );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping->unregister_shipping_methods();
	}

	public static function create_local_pickup_rate( $cost = 10 ) {
		$shipping_settings = array(
			'enabled'      => 'yes',
			'title'        => 'Local Pickup',
			'availability' => 'all',
			'countries'    => '',
			'tax_status'   => 'taxable',
			'cost'         => $cost,
		);

		update_option( 'woocommerce_local_pickup_settings', $shipping_settings );
		update_option( 'woocommerce_local_pickup', array() );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping->load_shipping_methods();
	}

	public static function delete_local_pickup_rate() {
		delete_option( 'woocommerce_local_pickup_settings' );
		delete_option( 'woocommerce_local_pickup' );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
		WC()->shipping->unregister_shipping_methods();
	}

}
