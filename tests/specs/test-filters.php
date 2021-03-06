<?php
class TJ_WC_Filters extends WP_UnitTestCase {

	public function setUp() {
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	}

	function test_append_base_address_to_customer_taxable_address() {
		TaxJar_Woocommerce_Helper::prepare_woocommerce();

		$tj = TaxJar();
		WC()->session->set( 'chosen_shipping_methods', array( 'local_pickup' ) );

		$address = array( 'US', 'CO', '81210', 'Denver', '1437 Bannock St' );
		$address = apply_filters( 'woocommerce_customer_taxable_address', $address );

		$this->assertEquals( strtoupper( $address[2] ), strtoupper( $tj->settings['store_postcode'] ) );
		$this->assertEquals( strtoupper( $address[3] ), strtoupper( $tj->settings['store_city'] ) );
		$this->assertEquals( strtoupper( $address[4] ), strtoupper( $tj->settings['store_street'] ) );
	}

}
