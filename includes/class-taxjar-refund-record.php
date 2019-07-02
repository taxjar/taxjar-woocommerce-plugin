<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Refund_Record extends TaxJar_Record {

	public $order_status;
	public $order_completed_date;

	public function load_object() {
		$refund =  wc_get_order( $this->get_record_id() );
		if ( $refund instanceof WC_Order_Refund ) {
			$this->object = $refund;
		} else {
			return;
		}

		parent::load_object();
	}

	public function sync() {
		if ( ! apply_filters( 'taxjar_should_sync_refund', $this->should_sync() ) ) {
			$this->sync_failure();
			return false;
		}

		$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 429, 500, 503 );
		$success_responses = array( 200, 201 );

		if ( $this->get_status() == 'new' ) {
			$response = $this->create_in_taxjar();
			if ( is_wp_error( $response ) ) {
				// handle wordpress error and add message to log here
				$this->sync_failure();
				return false;
			}
			if ( isset( $response['response']['code'] ) && $response['response']['code'] == 422 ) {
				$response = $this->update_in_taxjar();
			}
		} else {
			$response = $this->update_in_taxjar();
			if ( is_wp_error( $response ) ) {
				// handle wordpress error and add message to log here
				$this->sync_failure();
				return false;
			}
			if ( isset( $response['response']['code'] ) && $response['response']['code'] == 404 ) {
				$response = $this->create_in_taxjar();
			}
		}

		if ( ! isset( $response[ 'response' ][ 'code' ] ) ) {
			$this->sync_failure();
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
			$this->sync_failure();
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $success_responses ) ) {
			$this->sync_success();
			return true;
		}

		// handle any unexpected responses
		$this->sync_failure();
		return false;
	}

	public function should_sync() {
		$data = $this->get_data();
		if ( empty( $data ) ) {
			return false;
		}

		if ( empty( $this->order_completed_date ) ) {
			return false;
		}

		$valid_order_statuses = array( 'completed', 'refunded' );
		if ( empty( $this->order_status ) || ! in_array( $this->order_status, $valid_order_statuses ) ) {
			return false;
		}

		if ( ! $this->get_force_push() ) {
			if ( hash( 'md5', serialize( $this->get_data() ) ) === $this->get_object_hash() ) {
				return false;
			}
		}

		if ( $data[ 'to_country' ] != 'US' ) {
			return false;
		}

		if ( $this->object->get_currency() != 'USD' ) {
			return false;
		}

		if ( empty( $data[ 'from_country' ] ) || empty( $data[ 'from_state' ] ) || empty( $data[ 'from_zip' ] ) || empty( $data[ 'from_city' ] ) ) {
			return false;
		}

		if ( empty( $data[ 'to_country' ] ) || empty( $data[ 'to_state' ] ) || empty( $data[ 'to_zip' ] ) || empty( $data[ 'to_city' ] ) ) {
			return false;
		}

		return true;
	}

	public function sync_success() {
		parent::sync_success();
		$this->add_object_sync_metadata();
	}

	public function get_data_from_object() {
		if ( ! isset( $this->object ) ) {
			$this->data = array();
			return array();
		}

		$order_id = $this->object->get_parent_id();
		$order = wc_get_order( $order_id );
		if ( $order === false ) {
			$this->data = array();
			return array();
		}

		$this->order_status = $order->get_status();
		$this->order_completed_date = $order->get_date_completed();

		$store_settings   = $this->taxjar_integration->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $this->object->get_amount() + $this->object->get_total_tax();

		$ship_to_address = $this->get_ship_to_address( $order );

		$refund_data = array(
			'transaction_id' => $this->get_transaction_id(),
			'transaction_reference_id' => $this->object->get_parent_id(),
			'transaction_date' => $this->object->get_date_created()->date( DateTime::ISO8601 ),
			'from_country' => $from_country,
			'from_zip' => $from_zip,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'amount' => $amount,
			'shipping' => $this->object->get_shipping_total(),
			'sales_tax' => $this->object->get_total_tax(),
			'line_items' => $this->get_line_items(),
		);

		$refund_data = array_merge( $refund_data, $ship_to_address );

		$customer_id = $order->get_customer_id();
		if ( $customer_id ) {
			$refund_data[ 'customer_id' ] = $customer_id;
		}

		$this->data = $refund_data;
		return $refund_data;
	}

	public function get_ship_to_address( $order ) {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		$local_pickup = false;
		$shipping_methods = $order->get_shipping_methods();
		if ( !empty( $shipping_methods ) ) {
			foreach( $shipping_methods as $shipping_method ) {
				if ( $shipping_method->get_method_id() == 'local_pickup' ) {
					$local_pickup = true;
				}
			}
		}

		if ( $local_pickup ) {
			$tax_based_on = 'base';
		}

		if ( 'base' === $tax_based_on ) {
			$store_settings   = $this->taxjar_integration->get_store_settings();
			$country  = $store_settings['country'];
			$state    = $store_settings['state'];
			$postcode = $store_settings['postcode'];
			$city     = $store_settings['city'];
			$street   = $store_settings['street'];
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = $order->get_billing_country();
			$state    = $order->get_billing_state();
			$postcode = $order->get_billing_postcode();
			$city     = $order->get_billing_city();
			$street   = $order->get_billing_address_1();
		} else {
			$country  = $order->get_shipping_country();
			$state    = $order->get_shipping_state();
			$postcode = $order->get_shipping_postcode();
			$city     = $order->get_shipping_city();
			$street   = $order->get_shipping_address_1();
		}

		$to_country = isset( $country ) && ! empty( $country ) ? $country : false;
		$to_state = isset( $state ) && ! empty( $state ) ? $state : false;
		$to_zip = isset( $postcode ) && ! empty( $postcode ) ? $postcode : false;
		$to_city = isset( $city ) && ! empty( $city ) ? $city : false;
		$to_street = isset( $street ) && ! empty( $street ) ? $street : false;

		return array(
			'to_country' => $to_country,
			'to_state' => $to_state,
			'to_zip' => $to_zip,
			'to_city' => $to_city,
			'to_street' => $to_street,
		);
	}

	public function get_line_items() {
		$line_items_data = array();
		$items = $this->object->get_items();

		if ( ! empty( $items ) ) {
			foreach( $items as $item ) {
				$product = $item->get_product();

				$quantity = $item->get_quantity();
				$unit_price = $item->get_subtotal() / $quantity;
				$discount = $item->get_subtotal() - $item->get_total();

				$tax_class = explode( '-', $product->get_tax_class() );
				$tax_code = '';
				if ( isset( $tax_class ) && is_numeric( end( $tax_class ) ) ) {
					$tax_code = end( $tax_class );
				}

				if ( ! $product->is_taxable() || 'zero-rate' == sanitize_title( $product->get_tax_class() ) ) {
					$tax_code = '99999';
				}

				$line_items_data[] = array(
					'id' => $item->get_id(),
					'quantity' => $quantity,
					'product_identifier' => $product->get_sku(),
					'description' => $product->get_name(),
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
					'sales_tax' => $item->get_total_tax(),
				);
			}
		}

		$fees = $this->get_fee_line_items();


		return array_merge( $line_items_data, $fees );
	}

	public function get_fee_line_items() {
		$line_items_data = array();
		$fees = $this->object->get_fees();
		if ( !empty( $fees ) ) {
			foreach( $fees as $fee ) {
				$tax_class = explode( '-', $fee->get_tax_class() );
				$tax_code = '';
				if ( isset( $tax_class ) && is_numeric( end( $tax_class ) ) ) {
					$tax_code = end( $tax_class );
				}

				$tax_status = $fee->get_tax_status();
				if ( $tax_status != 'taxable' || 'zero-rate' == sanitize_title( $fee->get_tax_class() ) ) {
					$tax_code = '99999';
				}

				$line_items_data[] = array(
					'id' => $fee->get_id(),
					'quantity' => $fee->get_quantity(),
					'description' => $fee->get_name(),
					'product_tax_code' => $tax_code,
					'unit_price' => $fee->get_amount(),
					'sales_tax' => $fee->get_total_tax(),
				);
			}
		}
		return $line_items_data;
	}

	public function create_in_taxjar() {
		$data = $this->get_data();
		$url = self::API_URI . 'transactions/refunds';
		$data[ 'provider' ] = $this->get_provider();
		$body = wp_json_encode( $data );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function update_in_taxjar() {
		$refund_id = $this->get_transaction_id();
		$data = $this->get_data();

		$url = self::API_URI . 'transactions/refunds/' . $refund_id;
		$data[ 'provider' ] = $this->get_provider();
		$body = wp_json_encode( $data );

		$response = wp_remote_request( $url, array(
			'method' => 'PUT',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function delete_in_taxjar() {
		$refund_id = $this->get_transaction_id();
		$url = self::API_URI . 'transactions/refunds/' . $refund_id;
		$data = array(
			'transaction_id' => $refund_id,
			'provider' => $this->get_provider()
		);
		$body = wp_json_encode( $data );

		$response = wp_remote_request( $url, array(
			'method' => 'DELETE',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		return $response;
	}

	public function get_from_taxjar() {
		$refund_id = $this->get_transaction_id();
		$url = self::API_URI . 'transactions/refunds/' . $refund_id . '?provider=woo';

		$response = wp_remote_request( $url, array(
			'method' => 'GET',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
		) );

		return $response;
	}

	public function get_record_type() {
		return 'refund';
	}

	public function get_transaction_id() {
		return apply_filters( 'taxjar_get_refund_transaction_id', $this->object->get_id() );
	}
}