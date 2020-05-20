<?php

/**
 * Class TaxJar_API_Order_Helper
 */
class TaxJar_API_Order_Helper {

	public static function create_order_request_body( $body ) {
		return wp_parse_args(
			$body,
			array(
				'payment_method'       => 'bacs',
				'payment_method_title' => 'Direct Bank Transfer',
				'set_paid'             => true,
				'currency'             => 'USD',
				'customer_id'          => 0,
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
					'first_name' => 'Test',
					'last_name'  => 'Customer',
					'address_1'  => '123 Main St.',
					'address_2'  => '',
					'city'       => 'Payson',
					'state'      => 'UT',
					'postcode'   => '84651',
					'country'    => 'US',
				),
				'line_items'           => array(
					array(
						'product_id' => 1,
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
			)
		);
	}

}
