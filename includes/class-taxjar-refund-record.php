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

	public function should_sync() {
		$data = $this->get_data();
		if ( empty( $data ) ) {
			$this->add_error( __( 'Refund object not loaded to record before syncing.', 'wc-taxjar' ) );
			return false;
		}

		if ( WC_Taxjar_Transaction_Sync::should_validate_order_completed_date() ) {
			if ( empty( $this->order_completed_date ) ) {
				$this->add_error( __( 'Parent order does not have a completed date. Only refunds of orders that have been completed can sync to TaxJar.', 'wc-taxjar' ) );
				return false;
			}
		}

		$valid_order_statuses = apply_filters( 'taxjar_valid_order_statuses_for_sync', array( 'completed', 'refunded' ) );
		if ( empty( $this->order_status ) || ! in_array( $this->order_status, $valid_order_statuses ) ) {
			$this->add_error( __( 'Parent order has an invalid status. Only refunds of orders with the following statuses can sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", $valid_order_statuses ) );
			return false;
		}

		if ( ! $this->get_force_push() ) {
			if ( hash( 'md5', serialize( $this->get_data() ) ) === $this->get_object_hash() ) {
				$this->add_error( __( 'Refund data is not different from previous sync, re-syncing the transaction is not necessary.', 'wc-taxjar' ) );
				return false;
			}
		}

		if ( ! in_array( $data[ 'to_country' ], TaxJar_Record::allowed_countries() ) ) {
			$this->add_error( __( 'Parent order ship to country is not supported for reporting and filing. Only orders shipped to the following countries will sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", TaxJar_Record::allowed_countries()) );
			return false;
		}

		if ( ! in_array( $this->object->get_currency(), TaxJar_Record::allowed_currencies() ) ) {
			$this->add_error( __( 'Refund currency is not supported. Only refunds with the following currencies will sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", TaxJar_Record::allowed_currencies() ) );
			return false;
		}

		if ( ! $this->has_valid_ship_from_address() ) {
			$this->add_error( __( 'Parent order is missing required ship from field.', 'wc-taxjar' ) );
			return false;
		}

		if ( empty( $data[ 'to_country' ] ) || empty( $data[ 'to_state' ] ) || empty( $data[ 'to_zip' ] ) || empty( $data[ 'to_city' ] ) ) {
			$this->add_error( __( 'Parent order is missing required ship to field', 'wc-taxjar' ) );
			return false;
		}

		return true;
	}

	public function sync_success() {
		parent::sync_success();
		$this->update_object_sync_success_meta_data();
	}

	public function sync_failure( $error_message ) {
		parent::sync_failure( $error_message );
		$this->update_object_sync_failure_meta_data( $error_message );
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

		$store_settings   = TaxJar_Settings::get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $this->object->get_amount() - abs( $this->object->get_total_tax() );

		$ship_to_address = $this->get_ship_to_address( $order );

		$refund_data = array(
			'transaction_id' => $this->get_transaction_id(),
			'transaction_reference_id' => apply_filters( 'taxjar_get_order_transaction_id', $order_id, $order ),
			'transaction_date' => $this->object->get_date_created()->date( DateTime::ISO8601 ),
			'from_country' => $from_country,
			'from_zip' => $from_zip,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'amount' => (string) $amount,
			'shipping' => (string) $this->object->get_shipping_total(),
			'sales_tax' => (string) $this->object->get_total_tax(),
			'line_items' => $this->get_line_items(),
		);

		$refund_data = array_merge( $refund_data, $ship_to_address );

		$customer_id = $order->get_customer_id();

		if ( $customer_id ) {
			$refund_data[ 'customer_id' ] = $customer_id;
		}

		$exemption_type = apply_filters( 'taxjar_refund_sync_exemption_type', '', $this->object );

		if ( TaxJar_Tax_Calculation::is_valid_exemption_type( $exemption_type ) ) {
			$refund_data[ 'exemption_type' ] = $exemption_type;
		}

		$refund_data = apply_filters( 'taxjar_refund_sync_data', $refund_data, $this->object, $order );
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
			$store_settings   = TaxJar_Settings::get_store_settings();
			$country  = $store_settings['country'];
			$state    = $store_settings['state'];
			$postcode = $store_settings['postcode'];
			$city     = $store_settings['city'];
			$street   = $store_settings['street'];
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = ( ! empty( $order->get_billing_country() ) ? $order->get_billing_country() : $order->get_shipping_country() );
			$state  = ( ! empty( $order->get_billing_state() ) ? $order->get_billing_state() : $order->get_shipping_state() );
			$postcode  = ( ! empty( $order->get_billing_postcode() ) ? $order->get_billing_postcode() : $order->get_shipping_postcode() );
			$city  = ( ! empty( $order->get_billing_city() ) ? $order->get_billing_city() : $order->get_shipping_city() );
			$street  = ( ! empty( $order->get_billing_address_1() ) ? $order->get_billing_address_1() : $order->get_shipping_address_1() );
		} else {
			$country  = ( ! empty( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country() );
			$state  = ( ! empty( $order->get_shipping_state() ) ? $order->get_shipping_state() : $order->get_billing_state() );
			$postcode  = ( ! empty( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $order->get_billing_postcode() );
			$city  = ( ! empty( $order->get_shipping_city() ) ? $order->get_shipping_city() : $order->get_billing_city() );
			$street  = ( ! empty( $order->get_shipping_address_1() ) ? $order->get_shipping_address_1() : $order->get_billing_address_1() );
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
				$product_name = '';
				$product_identifier = '';

				if ( $product ) {
					$product_name = $product->get_name();
					$product_identifier = $product->get_sku();
				}

				if ( $quantity <= 0 ) {
					$unit_price = $item->get_subtotal();
					$quantity = 1;
				} else {
					$unit_price = $item->get_subtotal() / $quantity;
				}

				$discount = $item->get_subtotal() - $item->get_total();
				$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $item->get_tax_class() );

				$line_items_data[] = array(
					'id' => $item->get_id(),
					'quantity' => $quantity,
					'product_identifier' => $product_identifier,
					'description' => $product_name,
					'product_tax_code' => $tax_code,
					'unit_price' => (string) $unit_price,
					'discount' => (string) $discount,
					'sales_tax' => (string) $item->get_total_tax(),
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
				$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $fee->get_tax_class() );

				if ( method_exists( $fee, 'get_amount' ) ) {
					$fee_amount = $fee->get_amount();
				} else {
					$fee_amount = $fee->get_total();
				}

				$line_items_data[] = array(
					'id' => $fee->get_id(),
					'quantity' => $fee->get_quantity(),
					'description' => $fee->get_name(),
					'product_tax_code' => $tax_code,
					'unit_price' => (string) $fee_amount,
					'sales_tax' => (string) $fee->get_total_tax(),
				);
			}
		}
		return $line_items_data;
	}

	public function create_in_taxjar() {
		$data = $this->get_data();
		$data[ 'provider' ] = $this->get_provider();
		$data[ 'plugin' ] = $this->get_plugin_parameter();
		$body = wp_json_encode( $data );

		$request = new TaxJar_API_Request( 'transactions/refunds', $body, 'post' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function update_in_taxjar() {
		$data = $this->get_data();
		$data[ 'provider' ] = $this->get_provider();
		$data[ 'plugin' ] = $this->get_plugin_parameter();
		$body = wp_json_encode( $data );

		$request = new TaxJar_API_Request( 'transactions/refunds/' . $this->get_transaction_id(), $body, 'put' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function delete_in_taxjar() {
		$refund_id = $this->get_transaction_id();
		$data = array(
			'transaction_id' => $refund_id,
			'provider' => $this->get_provider(),
			'plugin' => $this->get_plugin_parameter()
		);
		$body = wp_json_encode( $data );

		$request = new TaxJar_API_Request( 'transactions/refunds/' . $refund_id, $body, 'delete' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function get_from_taxjar() {
		$request = new TaxJar_API_Request(
			'transactions/refunds/' . $this->get_transaction_id() . '?provider=' . $this->get_provider() . '&plugin=' . $this->get_plugin_parameter(),
			null,
			'get'
		);
		$response = $request->send_request();

		$this->set_last_request( $this->get_transaction_id() );
		return $response;
	}

	public static function get_record_type() {
		return 'refund';
	}

	public function get_transaction_id() {
		return apply_filters( 'taxjar_get_refund_transaction_id', $this->get_record_id(), $this->object );
	}
}
