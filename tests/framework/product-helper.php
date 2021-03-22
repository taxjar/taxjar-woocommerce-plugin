<?php
class TaxJar_Product_Helper {

	public static function create_product( $type = 'simple', $opts = array() ) {
		switch ($type) {
			case 'subscription':
				return TaxJar_Product_Helper::create_subscription_product( $opts );
			default:
				return TaxJar_Product_Helper::create_simple_product( $opts );
		}
	}

	private static function create_simple_product( $opts = array() ) {
		$defaults = array(
			'name'          => 'Dummy Product',
			'price'         => 10,
			'sku'           => 'SIMPLE1',
			'manage_stock'  => false,
			'tax_status'    => 'taxable',
			'downloadable'  => false,
			'virtual'       => false,
			'stock_status'  => 'instock',
			'weight'        => '1.1',
		);

		$props = array_replace_recursive( $defaults, $opts );
		$props[ 'regular_price' ] = $props[ 'price' ];
		$product = new WC_Product_Simple();
		$product->set_props( $props );

		if ( ! empty( $opts[ 'tax_class' ] ) ) {
			$product->set_tax_class( $opts[ 'tax_class' ] );
		}

		$product->save( );
		return wc_get_product( $product->get_id() );
	}

	private static function create_subscription_product( $opts = array() ) {
		$defaults = array(
			'name'          => 'Dummy Product',
			'price'         => '19.99',
			'sku'           => 'SUBSCRIPTION1',
			'manage_stock'  => false,
			'tax_status'    => 'taxable',
			'downloadable'  => false,
			'virtual'       => false,
			'stock_status'  => 'instock',
			'weight'        => '1.1',
			'interval' => 1,
			'period' => 'month',
			'sign_up_fee' => 0,
			'trial_length' => 1,
			'trial_period' => 'month',
		);

		$props = array_replace_recursive( $defaults, $opts );
		$props[ 'regular_price' ] = $props[ 'price' ];
		$product = new WC_Product_Subscription();
		$product->set_props( $props );
		$product->save();
		$product_id = $product->get_id();

		// Subscription meta
		update_post_meta( $product_id, '_subscription_price', $props['price'] );
		update_post_meta( $product_id, '_subscription_period_interval', $props['interval'] );
		update_post_meta( $product_id, '_subscription_period', $props['period'] );
		update_post_meta( $product_id, '_subscription_sign_up_fee', $props['sign_up_fee'] );
		update_post_meta( $product_id, '_subscription_trial_length', $props['trial_length'] );
		update_post_meta( $product_id, '_subscription_trial_period', $props['trial_period'] );

		return new WC_Product_Subscription( $product_id );
	}

}
