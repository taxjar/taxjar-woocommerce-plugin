<?php

class TaxJar_Order_Helper {

	public static function create_order( $customer_id = 1, $options_override = array() ) {
		$options_override[ 'customer_id' ] = $customer_id;
		return TaxJar_Test_Order_Factory::create( $options_override );
	}

	public static function delete_order( $order_id ) {
		$order = wc_get_order( $order_id );
		TaxJar_Shipping_Helper::delete_simple_flat_rate();
		$order->delete( true );
	}

	public static function create_order_with_no_tax() {
		$options_override = array(
			'products' => array(
				0 => array(
					'tax_total' => array( 0 ),
					'tax_subtotal' => array( 0 )
				)
			),
			'shipping_method' => array(
				'taxes' => array( 0 ),
			),
			'totals' => array(
				'shipping_total' => 10,
				'discount_total' => 0,
				'discount_tax' => 0,
				'cart_tax' => 0,
				'shipping_tax' => 0,
				'total' => 110
			)
		);

		return TaxJar_Test_Order_Factory::create( $options_override );
	}

	public static function create_order_quantity_two() {
		$options_override = array(
			'products' => array(
				0 => array (
					'quantity' => 2,
					'tax_total' => array( 14.50 ),
					'tax_subtotal' => array( 14.50 )
				)
			),
			'totals' => array(
				'cart_tax' => 14.50,
				'shipping_tax' => .73,
				'total' => 225.23
			)
		);

		return TaxJar_Test_Order_Factory::create( $options_override );
	}

	public static function create_order_with_no_customer_information() {
		$options_override = array(
			'shipping_address' => array(
				'first_name' => '',
				'last_name' => '',
				'address_1' => '',
				'city' => '',
				'state' => '',
				'postcode' => '',
				'country' => '',
			),
			'billing_address' => array(
				'first_name' => '',
				'last_name' => '',
				'address_1' => '',
				'city' => '',
				'state' => '',
				'postcode' => '',
				'country' => '',
				'email' => '',
				'phone' => ''
			),
		);

		return TaxJar_Test_Order_Factory::create( $options_override );
	}

	public static function create_local_pickup_order() {
		$options_override = array(
			'shipping_method' => array(
				'id' => 'local_pickup',
				'label' => 'Local Pickup',
				'cost' => '0',
				'taxes' => array( 0 ),
				'method_id' =>  'local_pickup'
			),
			'totals' => array(
				'shipping_total' => 0,
				'shipping_tax' => 0,
				'total' => 107.25
			)
		);

		return TaxJar_Test_Order_Factory::create( $options_override );
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

	static function create_refund_from_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Ensure order is in a valid status for refunds (WC 8.x+ requirement)
		$valid_statuses = array( 'processing', 'completed', 'on-hold' );
		if ( ! in_array( $order->get_status(), $valid_statuses, true ) ) {
			$order->set_status( 'completed' );
			$order->save();
		}

		$order_items = $order->get_items( array( 'line_item', 'shipping', 'fee' ) );

		$line_items = array();
		foreach ( $order_items as $item_id => $item ) {
			$line_items[ $item_id ] = array(
				'qty'          => $item->get_quantity(),
				'refund_total' => $item->get_total(),
				'refund_tax'   => array(  $item->get_total_tax() )
			);
		}

		$refund = wc_create_refund(
			array(
				'amount'         => $order->get_total(),
				'reason'         => 'Refund Reason',
				'order_id'       => $order_id,
				'line_items'     => $line_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			throw new Exception( 'create_refund_from_order failed: ' . $refund->get_error_message() . ' (order_id=' . $order_id . ', status=' . $order->get_status() . ', total=' . $order->get_total() . ')' );
		}

		return $refund;
	}

	static function create_partial_refund_from_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Ensure order is in a valid status for refunds (WC 8.x+ requirement)
		$valid_statuses = array( 'processing', 'completed', 'on-hold' );
		if ( ! in_array( $order->get_status(), $valid_statuses, true ) ) {
			$order->set_status( 'completed' );
			$order->save();
		}

		$order_items = $order->get_items( array( 'line_item', 'shipping', 'fee' ) );

		$line_items = array();
		$refund_total = 0;
		foreach ( $order_items as $item_id => $item ) {
			if ( $item->get_type() != 'line_item' ) {
				continue;
			}
			$line_refund_total = $item->get_total() / $item->get_quantity();
			$line_refund_tax = $item->get_total_tax() / $item->get_quantity();
			$refund_total += $line_refund_total;
			$refund_total += $line_refund_tax;
			$line_items[ $item_id ] = array(
				'qty'          => 1,
				'refund_total' => $line_refund_total,
				'refund_tax'   => array( $line_refund_tax )
			);
		}

		$refund = wc_create_refund(
			array(
				'amount'         => $refund_total,
				'reason'         => 'Refund Reason',
				'order_id'       => $order_id,
				'line_items'     => $line_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			throw new Exception( 'create_partial_refund_from_order failed: ' . $refund->get_error_message() . ' (order_id=' . $order_id . ', status=' . $order->get_status() . ')' );
		}

		return $refund;
	}

	static function create_fee_refund_from_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Ensure order is in a valid status for refunds (WC 8.x+ requirement)
		$valid_statuses = array( 'processing', 'completed', 'on-hold' );
		if ( ! in_array( $order->get_status(), $valid_statuses, true ) ) {
			$order->set_status( 'completed' );
			$order->save();
		}

		$order_items = $order->get_items( array( 'line_item', 'shipping', 'fee' ) );

		$line_items = array();
		$refund_total = 0;
		foreach ( $order_items as $item_id => $item ) {
			if ( $item->get_type() != 'fee' ) {
				continue;
			}

			if ( method_exists( $item, 'get_amount' ) ) {
				$fee_amount = $item->get_amount();
			} else {
				$fee_amount = $item->get_total();
			}

			$line_refund_total = $fee_amount;
			$line_refund_tax = $item->get_total_tax();
			$refund_total += $line_refund_total;
			$refund_total += $line_refund_tax;
			$line_items[ $item_id ] = array(
				'qty'          => 1,
				'refund_total' => $line_refund_total,
				'refund_tax'   => array( $line_refund_tax )
			);
		}

		$refund = wc_create_refund(
			array(
				'amount'         => $refund_total,
				'reason'         => 'Refund Reason',
				'order_id'       => $order_id,
				'line_items'     => $line_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			throw new Exception( 'create_fee_refund_from_order failed: ' . $refund->get_error_message() . ' (order_id=' . $order_id . ', status=' . $order->get_status() . ')' );
		}

		return $refund;
	}

	static function create_partial_line_item_refund_from_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Ensure order is in a valid status for refunds (WC 8.x+ requirement)
		$valid_statuses = array( 'processing', 'completed', 'on-hold' );
		if ( ! in_array( $order->get_status(), $valid_statuses, true ) ) {
			$order->set_status( 'completed' );
			$order->save();
		}

		$order_items = $order->get_items( array( 'line_item', 'shipping', 'fee' ) );

		$line_items = array();
		$refund_total = 0;
		foreach ( $order_items as $item_id => $item ) {
			if ( $item->get_type() != 'line_item' ) {
				continue;
			}
			$line_refund_total = $item->get_total() / $item->get_quantity() / 2;
			$line_refund_tax = $item->get_total_tax() / $item->get_quantity() / 2;
			$refund_total += $line_refund_total;
			$refund_total += $line_refund_tax;
			$line_items[ $item_id ] = array(
				'qty'          => 0,
				'refund_total' => $line_refund_total,
				'refund_tax'   => array( $line_refund_tax )
			);
		}

		$refund = wc_create_refund(
			array(
				'amount'         => $refund_total,
				'reason'         => 'Refund Reason',
				'order_id'       => $order_id,
				'line_items'     => $line_items,
			)
		);

		if ( is_wp_error( $refund ) ) {
			throw new Exception( 'create_partial_line_item_refund_from_order failed: ' . $refund->get_error_message() . ' (order_id=' . $order_id . ', status=' . $order->get_status() . ')' );
		}

		return $refund;
	}
}
