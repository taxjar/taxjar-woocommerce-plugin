<?php
class TaxJar_Customer_Helper {

	public static function create_customer( $opts = array() ) {
		$defaults = array(
			'country' => 'US',
			'state' => 'CO',
			'zip' => '80111',
			'city' => 'Greenwood Village',
		);
		$params = extract( array_replace_recursive( $defaults, $opts ) );

		$customer = new WC_Customer();
		$customer->set_shipping_location( $country, $state, $zip, $city );

		return $customer;
	}

}
