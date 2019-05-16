<?php
class TaxJar_Subscription_Helper {

	public static function prepare_subscription_request( $parameters = array() ) {
		$subscription_product = TaxJar_Product_Helper::create_product( 'subscription', array(
			'price' => '100',
			'sign_up_fee' => 0,
			'trial_length' => 0,
		) );
		$subscription_product_id = $subscription_product->get_id();

		$next_payment_date = date("Y-m-d H:i:s", strtotime("+1 month" ) );

		$request = new WP_REST_Request( 'POST', '/wc/v1/subscriptions' );

		$default_parameters = array(
			'customer_id' => 1,
			'status' => 'active',
			'billing_period' => 'month',
			'billing_interval' => 1,
			'next_payment_date' => $next_payment_date,
			'payment_method' => 'stripe',
			'payment_method_title'       => 'Credit Card (Stripe)',
			'set_paid'             => true,
			'billing'              => array(
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'address_1'  => '969 Market',
				'address_2'  => '',
				'city'       => 'Greenwood Village',
				'state'      => 'CO',
				'postcode'   => '80111',
				'country'    => 'US',
				'email'      => 'john.doe@example.com',
				'phone'      => '(555) 555-5555',
			),
			'shipping'             => array(
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'address_1'  => '969 Market',
				'address_2'  => '',
				'city'       => 'Greenwood Village',
				'state'      => 'CO',
				'postcode'   => '80111',
				'country'    => 'US',
			),
			'line_items'           => array(
				array(
					'product_id' => $subscription_product_id,
					'quantity'   => 1
				)
			),
			'shipping_lines'       => array(
				array(
					'method_id'    => 'flat_rate',
					'method_title' => 'Flat rate',
					'total'        => '10',
				),
			),
		);

		$parameters = array_replace_recursive( $default_parameters, $parameters );
		$request->set_body_params( $parameters );
		return $request;
	}

}
