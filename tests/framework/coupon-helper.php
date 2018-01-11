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

		if ( version_compare( $woocommerce->version, '3.0', '>=' ) ) {
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_amount( $amount );
			$coupon->set_discount_type( $discount_type );
			$coupon->save();
		} else {
			$coupon_id = wp_insert_post( array(
				'post_title'   => $code,
				'post_type'    => 'shop_coupon',
				'post_status'  => 'publish',
				'post_excerpt' => 'This is a dummy coupon'
			) );

			update_post_meta( $coupon_id, 'coupon_amount', $amount );
			update_post_meta( $coupon_id, 'discount_type', $discount_type );

			$coupon = new WC_Coupon( $code );
		}

		return $coupon;
	}

}
