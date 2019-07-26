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

	public static function create_exempt_customer() {
		$customer = self::create_customer();
		$customer->set_email( 'test@test.com' );
		$customer->set_billing_first_name( 'First' );
		$customer->set_billing_last_name( 'Last' );
		$customer->set_shipping_address_1( '123 Test St' );

		$customer->save();

		update_user_meta( $customer->get_id(), 'tax_exempt_regions', 'UT,CO' );
		update_user_meta( $customer->get_id(), 'tax_exemption_type', 'wholesale' );

		return $customer;
	}

}
