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

	public static function create_complete_customer() {
		$customer = self::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->set_billing_first_name( 'Test' );
		$customer->set_billing_last_name( 'Test' );

		$customer->save();
		return $customer;
	}

}
