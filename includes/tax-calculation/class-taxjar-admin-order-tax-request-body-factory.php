<?php

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Admin_Order_Tax_Request_Body_Factory extends TaxJar_Order_Tax_Request_Body_Factory {

	protected function get_ship_to_address() {
		$to_country = isset( $_POST['country'] ) ? strtoupper( wc_clean( $_POST['country'] ) ) : false;
		$to_state   = isset( $_POST['state'] ) ? strtoupper( wc_clean( $_POST['state'] ) ) : false;
		$to_zip     = isset( $_POST['postcode'] ) ? strtoupper( wc_clean( $_POST['postcode'] ) ) : false;
		$to_city    = isset( $_POST['city'] ) ? strtoupper( wc_clean( $_POST['city'] ) ) : false;
		$to_street  = isset( $_POST['street'] ) ? strtoupper( wc_clean( $_POST['street'] ) ) : false;

		$this->tax_request_body->set_to_country( $to_country );
		$this->tax_request_body->set_to_state( $to_state );
		$this->tax_request_body->set_to_zip( $to_zip );
		$this->tax_request_body->set_to_city( $to_city );
		$this->tax_request_body->set_to_street( $to_street );
	}

	protected function get_customer_id() {
		$customer_id = isset( $_POST['customer_user'] ) ? wc_clean( $_POST['customer_user'] ) : 0;
		$this->tax_request_body->set_customer_id( apply_filters( 'taxjar_get_customer_id', $customer_id ) );
	}
}