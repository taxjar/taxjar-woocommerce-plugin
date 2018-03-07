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

}
