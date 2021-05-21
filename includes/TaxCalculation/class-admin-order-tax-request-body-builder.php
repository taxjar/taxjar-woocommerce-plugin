<?php
/**
 * TaxJar Admin Order Tax Request Body Builder
 *
 * Builds tax request body from order created or edited through WooCommerce admin dashboard.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Order_Tax_Request_Body_Builder
 */
class Admin_Order_Tax_Request_Body_Builder extends Order_Tax_Request_Body_Builder {

	/**
	 * Get ship to address from $_POST and set on tax request body.
	 */
	protected function get_ship_to_address() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$to_country = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : false;
		$to_state   = isset( $_POST['state'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['state'] ) ) ) : false;
		$to_zip     = isset( $_POST['postcode'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) ) : false;
		$to_city    = isset( $_POST['city'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['city'] ) ) ) : false;
		$to_street  = isset( $_POST['street'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['street'] ) ) ) : false;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->tax_request_body->set_to_country( $to_country );
		$this->tax_request_body->set_to_state( $to_state );
		$this->tax_request_body->set_to_zip( $to_zip );
		$this->tax_request_body->set_to_city( $to_city );
		$this->tax_request_body->set_to_street( $to_street );
	}

	/**
	 * Get customer ID from $_POST and set on tax request body.
	 */
	protected function get_customer_id() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$customer_id = isset( $_POST['customer_user'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_user'] ) ) : 0;
		$this->tax_request_body->set_customer_id( apply_filters( 'taxjar_get_customer_id', $customer_id ) );
	}
}
