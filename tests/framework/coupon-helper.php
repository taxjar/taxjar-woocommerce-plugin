<?php
class TaxJar_Coupon_Helper {

	public static function create_coupon( $opts = array() ) {
		global $woocommerce;

		$defaults = array(
			'code' => 'HIRO',
			'amount' => '10',
			'discount_type' => 'fixed_cart',
		);
		$params = extract( array_replace_recursive( $defaults, $opts ) );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_amount( $amount );
		$coupon->set_discount_type( $discount_type );
		$coupon->save();

		return $coupon;
	}

}
