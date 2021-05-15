<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Tax_Calculation_Validator implements TaxJar_Tax_Calculation_Validator_Interface {

	private $order;
	private $nexus;

	public function __construct( $order, $nexus ) {
		$this->order = $order;
		$this->nexus = $nexus;
	}

	public function validate( $request_body ) {
		$request_body->validate();
		$this->validate_vat_exemption( $request_body );
		$this->validate_order_has_nexus( $request_body );
	}

	private function validate_vat_exemption( $request_body ) {
		if ( $this->is_order_vat_exempt() ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'is_vat_exempt',
				__( 'Tax calculation is not performed if order is vat exempt.', 'taxjar' )
			);
		}

		if ( $this->is_customer_vat_exempt( $request_body ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'is_vat_exempt',
				__( 'Tax calculation is not performed if customer is vat exempt.', 'taxjar' )
			);
		}
	}

	private function is_order_vat_exempt() {
		$vat_exemption = 'yes' === $this->order->get_meta( 'is_vat_exempt' );
		return apply_filters( 'woocommerce_order_is_vat_exempt', $vat_exemption, $this->order );
	}

	private function is_customer_vat_exempt( $request_body ) {
		$customer_id = intval( $request_body->get_customer_id() );
		if ( $customer_id > 0 ) {
			$customer = new WC_Customer( $customer_id );
			return $customer->is_vat_exempt();
		}
		
		return false;
	}

	private function validate_order_has_nexus( $request_body ) {
		if ( $this->is_out_of_nexus_areas( $request_body ) ) {
			throw new TaxJar_Tax_Calculation_Exception(
				'no_nexus',
				__( 'Order does not have nexus.', 'taxjar' )
			);
		}
	}

	private function is_out_of_nexus_areas( $request_body ) {
		return ! $this->nexus->has_nexus_check( $request_body->get_to_country(), $request_body->get_to_state() );
	}

}