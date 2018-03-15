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
			'price' => '10',
			'sku' => 'SIMPLE1',
			'tax_class' => '',
			'tax_status' => 'taxable',
			'downloadable' => 'no',
			'virtual' => 'no',
		);

		$post = array(
			'post_title' => 'Dummy Product',
			'post_type' => 'product',
			'post_status' => 'publish',
		);
		$post_meta = array_replace_recursive( $defaults, $opts );
		$post_meta['regular_price'] = $post_meta['price'];

		$post_id = wp_insert_post( $post );

		register_taxonomy(
			'product_type',
			'product'
		);

		update_post_meta( $post_id, '_price', $post_meta['price'] );
		update_post_meta( $post_id, '_regular_price', $post_meta['regular_price'] );
		update_post_meta( $post_id, '_sale_price', '' );
		update_post_meta( $post_id, '_sku', $post_meta['sku'] );
		update_post_meta( $post_id, '_manage_stock', 'no' );
		update_post_meta( $post_id, '_tax_class', $post_meta['tax_class'] );
		update_post_meta( $post_id, '_tax_status', $post_meta['tax_status'] );
		update_post_meta( $post_id, '_downloadable', $post_meta['downloadable'] );
		update_post_meta( $post_id, '_virtual', $post_meta['virtual'] );
		update_post_meta( $post_id, '_stock_status', 'instock' );

		wp_set_object_terms( $post_id, 'simple', 'product_type' );

		$products = get_posts( array(
			'post_type' => 'product',
			'_sku' => $post_meta['sku'],
		) );

		$factory = new WC_Product_Factory();
		return $factory->get_product( $products[0]->ID );
	}

	private static function create_subscription_product( $opts = array() ) {
		$defaults = array(
			'price' => '19.99',
			'sku' => 'SUBSCRIPTION1',
			'tax_class' => '',
			'tax_status' => 'taxable',
			'downloadable' => 'no',
			'virtual' => 'yes',
			'interval' => 1,
			'period' => 'month',
			'sign_up_fee' => 0,
			'trial_length' => 1,
			'trial_period' => 'month',
		);

		$post = array(
			'post_title' => 'Dummy Subscription',
			'post_type' => 'product',
			'post_status' => 'publish',
		);
		$post_meta = array_replace_recursive( $defaults, $opts );
		$post_meta['regular_price'] = $post_meta['price'];

		$post_id = wp_insert_post( $post );

		register_taxonomy(
			'product_type',
			'subscription'
		);

		update_post_meta( $post_id, '_price', $post_meta['price'] );
		update_post_meta( $post_id, '_regular_price', $post_meta['regular_price'] );
		update_post_meta( $post_id, '_sale_price', '' );
		update_post_meta( $post_id, '_sku', $post_meta['sku'] );
		update_post_meta( $post_id, '_manage_stock', 'no' );
		update_post_meta( $post_id, '_tax_class', $post_meta['tax_class'] );
		update_post_meta( $post_id, '_tax_status', $post_meta['tax_status'] );
		update_post_meta( $post_id, '_downloadable', $post_meta['downloadable'] );
		update_post_meta( $post_id, '_virtual', $post_meta['virtual'] );
		update_post_meta( $post_id, '_stock_status', 'instock' );

		// Subscription meta
		update_post_meta( $post_id, '_subscription_price', $post_meta['price'] );
		update_post_meta( $post_id, '_subscription_period_interval', $post_meta['interval'] );
		update_post_meta( $post_id, '_subscription_period', $post_meta['period'] );
		update_post_meta( $post_id, '_subscription_sign_up_fee', $post_meta['sign_up_fee'] );
		update_post_meta( $post_id, '_subscription_trial_length', $post_meta['trial_length'] );
		update_post_meta( $post_id, '_subscription_trial_period', $post_meta['trial_period'] );

		wp_set_object_terms( $post_id, 'subscription', 'product_type' );

		return new WC_Product_Subscription( $post_id );
	}

}
