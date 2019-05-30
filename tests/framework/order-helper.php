<?php

class TaxJar_Order_Helper {

	public static function delete_order( $order_id ) {

		$order = wc_get_order( $order_id );

		TaxJar_Shipping_Helper::delete_simple_flat_rate();

		// Delete the order post.
		$order->delete( true );
	}

	public static function create_order( $customer_id = 1, $order_options = array() ) {
		$options = array(
			'price' => '100'
		);
		$product = TaxJar_Product_Helper::create_product( 'simple', $options );


		TaxJar_Shipping_Helper::create_simple_flat_rate( 10 );

		$order_data = array(
			'status'        => 'pending',
			'customer_id'   => $customer_id,
			'customer_note' => '',
			'total'         => '',
		);
		$order_data = array_replace_recursive( $order_data, $order_options );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception
		$order 					= wc_create_order( $order_data );

		// Add order products
		$item = new WC_Order_Item_Product();
		$item->set_props( array(
			'product'  => $product,
			'quantity' => 1,
			'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => 1 ) ),
			'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => 1 ) ),
		) );
		$item->set_taxes( array(
			'total' => array( 7.25 ),
			'subtotal' => array( 7.25 )
		) );
		$item->save();
		$order->add_item( $item );

		// Set billing address
		$order->set_billing_first_name( 'Fname' );
		$order->set_billing_last_name( 'Lname' );
		$order->set_billing_address_1( 'Billing Address' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( 'Greenwood Village' );
		$order->set_billing_state( 'CO' );
		$order->set_billing_postcode( '80111' );
		$order->set_billing_country( 'US' );
		$order->set_billing_email( 'admin@example.org' );
		$order->set_billing_phone( '111-111-1111' );

		// Set shipping address
		$order->set_shipping_first_name( 'Fname' );
		$order->set_shipping_last_name( 'Lname' );
		$order->set_shipping_address_1( 'Shipping Address' );
		$order->set_shipping_address_2( '' );
		$order->set_shipping_city( 'Greenwood Village' );
		$order->set_shipping_state( 'CO' );
		$order->set_shipping_postcode( '80111' );
		$order->set_shipping_country( 'US' );


		// Add shipping costs
		$shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		$rate   = new WC_Shipping_Rate( 'flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate' );
		$item   = new WC_Order_Item_Shipping();
		$item->set_props( array(
			'method_title' => $rate->label,
			'method_id'    => $rate->id,
			'total'        => wc_format_decimal( $rate->cost ),
			'taxes'        => $rate->taxes,
		) );
		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		// Set payment gateway
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );

		// Set totals
		$order->set_shipping_total( 10 );
		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_cart_tax( 7.25 );
		$order->set_shipping_tax( 0.73 );
		$order->set_total( 117.98 ); // 4 x $10 simple helper product
		$order->save();

		return $order;
	}

	public static function create_local_pickup_order( $customer_id = 1, $order_options = array() ) {
		$options = array(
			'price' => '100'
		);
		$product = TaxJar_Product_Helper::create_product( 'simple', $options );

		$order_data = array(
			'status'        => 'pending',
			'customer_id'   => $customer_id,
			'customer_note' => '',
			'total'         => '',
		);
		$order_data = array_replace_recursive( $order_data, $order_options );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception
		$order 					= wc_create_order( $order_data );

		// Add order products
		$item = new WC_Order_Item_Product();
		$item->set_props( array(
			'product'  => $product,
			'quantity' => 1,
			'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => 1 ) ),
			'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => 1 ) ),
		) );
		$item->set_taxes( array(
			'total' => array( 7.25 ),
			'subtotal' => array( 7.25 )
		) );
		$item->save();
		$order->add_item( $item );

		// Set billing address
		$order->set_billing_first_name( 'Fname' );
		$order->set_billing_last_name( 'Lname' );
		$order->set_billing_address_1( 'Billing Address' );
		$order->set_billing_address_2( '' );
		$order->set_billing_city( 'Greenwood Village' );
		$order->set_billing_state( 'CO' );
		$order->set_billing_postcode( '80111' );
		$order->set_billing_country( 'US' );
		$order->set_billing_email( 'admin@example.org' );
		$order->set_billing_phone( '111-111-1111' );

		// Set shipping address
		$order->set_shipping_first_name( 'Fname' );
		$order->set_shipping_last_name( 'Lname' );
		$order->set_shipping_address_1( 'Shipping Address' );
		$order->set_shipping_address_2( '' );
		$order->set_shipping_city( 'Greenwood Village' );
		$order->set_shipping_state( 'CO' );
		$order->set_shipping_postcode( '80111' );
		$order->set_shipping_country( 'US' );


		// Add shipping costs
		$shipping_taxes = WC_Tax::calc_shipping_tax( '10', WC_Tax::get_shipping_tax_rates() );
		$rate   = new WC_Shipping_Rate( 'local_pickup', 'Local Pickup', '0', array(), 'local_pickup' );
		$item   = new WC_Order_Item_Shipping();
		$item->set_props( array(
			'method_title' => $rate->label,
			'method_id'    => $rate->id,
			'total'        => wc_format_decimal( $rate->cost ),
			'taxes'        => $rate->taxes,
		) );
		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}
		$order->add_item( $item );

		// Set payment gateway
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$order->set_payment_method( $payment_gateways['bacs'] );

		// Set totals
		$order->set_shipping_total( 0 );
		$order->set_discount_total( 0 );
		$order->set_discount_tax( 0 );
		$order->set_cart_tax( 7.25 );
		$order->set_shipping_tax( 0 );
		$order->set_total( 107.25 ); // 4 x $10 simple helper product
		$order->save();

		return $order;
	}

	static function get_test_order_data() {
		$test_data = array(
			"transaction_id" => "111111111",
			"transaction_date" => "2019-05-21T00:00:00+0000",
			"from_country" => "US",
			"from_zip" => "80111",
			"from_state" => "CO",
			"from_city" => "Greenwood Village",
			"from_street" => "6060 S Quebec St",
			"to_country" => "US",
			"to_zip" => "80111",
			"to_state" => "CO",
			"to_city" => "Greenwood Village",
			"to_street" => "6060 S Quebec St",
			"amount" => 110,
			"shipping" => "10",
			"sales_tax" => "7.98",
			"line_items" => array(
				array(
					"id" => 1,
					"quantity" => 1,
					"product_identifier" => "simple-product",
					"description" => "Simple Product",
					"product_tax_code" => "",
					"unit_price" => 100,
					"discount" => 0,
					"sales_tax" => "7.25"
				)
			),
			"customer_id" => 1
		);

		return $test_data;
	}
}
