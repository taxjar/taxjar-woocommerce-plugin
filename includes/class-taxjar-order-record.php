<?php

use Automattic\WooCommerce\Utilities\NumberUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Record extends TaxJar_Record {

	/**
	 * @param WC_Order $object - allows loading of object without additional queries if available
	 */
	function load_object( $object = null ) {
		if ( $object && is_a( $object, 'WC_Order' ) ) {
			$this->object = $object;
		} else {
			$order = wc_get_order( $this->get_record_id() );
			if ( $order instanceof WC_Order && get_class( $order ) != 'WC_Subscription' ) {
				$this->object = $order;
			} else {
				return;
			}
		}

		parent::load_object();
	}

	public static function get_record_type() {
		return 'order';
	}

	public function should_sync( $ignore_status = false ) {
		if ( ! isset( $this->object ) ) {
			$this->add_error( __( 'Order object not loaded to record before syncing.', 'wc-taxjar' ) );
			return false;
		}

		if ( ! $ignore_status ) {
			$status = $this->object->get_status();
			$valid_statuses = apply_filters( 'taxjar_valid_order_statuses_for_sync', array( 'completed', 'refunded' ) );
			if ( ! in_array( $status, $valid_statuses ) ) {
				$this->add_error( __( 'Order has an invalid status. Only orders with the following statuses can sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", $valid_statuses) );
				return false;
			}

			if ( WC_Taxjar_Transaction_Sync::should_validate_order_completed_date() ) {
				if ( empty( $this->object->get_date_completed() ) ) {
					$this->add_error( __( 'Order does not have a completed date. Only orders that have been completed can sync to TaxJar.', 'wc-taxjar' ) );
					return false;
				}
			}
		}

		if ( ! $this->get_force_push() ) {
			if ( hash( 'md5', serialize( $this->get_data() ) ) === $this->get_object_hash() ) {
				$this->add_error( __( 'Order data is not different from previous sync, re-syncing the transaction is not necessary.', 'wc-taxjar' ) );
				return false;
			}
		}

		$order_data = $this->get_data();

		if ( ! in_array( $order_data[ 'to_country' ], TaxJar_Record::allowed_countries() ) ) {
			$this->add_error( __( 'Order ship to country is not supported for reporting and filing. Only orders shipped to the following countries will sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", TaxJar_Record::allowed_countries() ) );
			return false;
		}

		if ( ! in_array( $this->object->get_currency(), TaxJar_Record::allowed_currencies() ) ) {
			$this->add_error( __( 'Order currency is not supported. Only orders with the following currencies will sync to TaxJar: ', 'wc-taxjar' ) . implode( ", ", TaxJar_Record::allowed_currencies() ) );
			return false;
		}

		if ( ! $this->has_valid_ship_from_address() ) {
			$this->add_error( __( 'Order is missing required ship from field.', 'wc-taxjar' ) );
			return false;
		}

		if ( empty( $order_data[ 'to_country' ] ) || empty( $order_data[ 'to_state' ] ) || empty( $order_data[ 'to_zip' ] ) || empty( $order_data[ 'to_city' ] ) ) {
			$this->add_error( __( 'Order is missing required ship to field.', 'wc-taxjar' ) );
			return false;
		}

		return true;
	}

	public function sync_success() {
		parent::sync_success();

		// prevent creating new record in queue when updating a successfully synced order
		remove_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );
		$this->update_object_sync_success_meta_data();
		add_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );
	}

	public function sync_failure( $error_message ) {
		parent::sync_failure( $error_message );

		remove_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );
		$this->update_object_sync_failure_meta_data( $error_message );
		add_action( 'woocommerce_update_order', array( 'WC_Taxjar_Transaction_Sync', 'order_updated' ) );
	}

	public function create_in_taxjar() {
		$data = $this->get_data();
		$data[ 'provider' ] = $this->get_provider();
		$data[ 'plugin' ] = $this->get_plugin_parameter();
		$body = wp_json_encode( $data );

		$request = new TaxJar_API_Request( 'transactions/orders', $body, 'post' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function update_in_taxjar(){
		$data = $this->get_data();
		$data[ 'provider' ] = $this->get_provider();
		$data[ 'plugin' ] = $this->get_plugin_parameter();
		$body = wp_json_encode( $data );

		$endpoint = 'transactions/orders/' . $this->get_transaction_id();
		$request = new TaxJar_API_Request( $endpoint, $body, 'put' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function delete_in_taxjar(){
		$order_id = $this->get_transaction_id();
		$data = array(
			'transaction_id' => $order_id,
			'provider' => $this->get_provider(),
			'plugin' => $this->get_plugin_parameter()
		);
		$body = wp_json_encode( $data );

		$endpoint = 'transactions/orders/' . $order_id;
		$request = new TaxJar_API_Request( $endpoint, $body, 'delete' );
		$response = $request->send_request();

		$this->set_last_request( $body );
		return $response;
	}

	public function get_from_taxjar() {
		$order_id = $this->get_transaction_id();
		$endpoint = 'transactions/orders/' . $order_id . '?provider=' . $this->get_provider() . '&plugin=' . $this->get_plugin_parameter();
		$request = new TaxJar_API_Request( $endpoint, null, 'get' );
		$response = $request->send_request();

		$this->set_last_request( $order_id );
		return $response;
	}

	public function get_data_from_object() {
		$created_date = $this->object->get_date_created();
		if ( empty( $created_date ) ) {
			$this->object = null;
			return;
		}

		$store_settings   = TaxJar_Settings::get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $this->object->get_total() - wc_round_tax_total( $this->object->get_total_tax() );
		$amount = apply_filters( 'taxjar_order_total_amount', $amount, $this->object );

		$ship_to_address = $this->get_ship_to_address();
		$order_data = array(
			'transaction_id' => $this->get_transaction_id(),
			'transaction_date' => $created_date->date( DateTime::ISO8601 ),
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

		$order_data = array_merge( $order_data, $ship_to_address );

		$customer_id = $this->object->get_customer_id();
		if ( $customer_id ) {
			$order_data[ 'customer_id' ] = $customer_id;
		}

		$exemption_type = apply_filters( 'taxjar_order_sync_exemption_type', '', $this->object );

		if ( TaxJar_Tax_Calculation::is_valid_exemption_type( $exemption_type ) ) {
			$order_data[ 'exemption_type' ] = $exemption_type;
		}

		$order_data = apply_filters( 'taxjar_order_sync_data', $order_data, $this->object );
		$this->data = $order_data;
		return $order_data;
	}

	public function get_ship_to_address() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		$local_pickup = false;
		if ( $this->object->has_shipping_method( 'local_pickup' ) ) {
			$local_pickup = true;
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
			$country  = ( ! empty( $this->object->get_billing_country() ) ? $this->object->get_billing_country() : $this->object->get_shipping_country() );
			$state  = ( ! empty( $this->object->get_billing_state() ) ? $this->object->get_billing_state() : $this->object->get_shipping_state() );
			$postcode  = ( ! empty( $this->object->get_billing_postcode() ) ? $this->object->get_billing_postcode() : $this->object->get_shipping_postcode() );
			$city  = ( ! empty( $this->object->get_billing_city() ) ? $this->object->get_billing_city() : $this->object->get_shipping_city() );
			$street  = ( ! empty( $this->object->get_billing_address_1() ) ? $this->object->get_billing_address_1() : $this->object->get_shipping_address_1() );
		} else {
			$country  = ( ! empty( $this->object->get_shipping_country() ) ? $this->object->get_shipping_country() : $this->object->get_billing_country() );
			$state  = ( ! empty( $this->object->get_shipping_state() ) ? $this->object->get_shipping_state() : $this->object->get_billing_state() );
			$postcode  = ( ! empty( $this->object->get_shipping_postcode() ) ? $this->object->get_shipping_postcode() : $this->object->get_billing_postcode() );
			$city  = ( ! empty( $this->object->get_shipping_city() ) ? $this->object->get_shipping_city() : $this->object->get_billing_city() );
			$street  = ( ! empty( $this->object->get_shipping_address_1() ) ? $this->object->get_shipping_address_1() : $this->object->get_billing_address_1() );
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
				$product_name = '';
				$product_identifier = '';

				if ( $product ) {
					$product_name = $product->get_name();
					$product_identifier = $product->get_sku();
				}

				$quantity = $item->get_quantity();
				$unit_price = NumberUtil::round( $item->get_subtotal(), wc_get_price_decimals() ) / $quantity;
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

		return apply_filters( 'taxjar_order_sync_get_line_items', array_merge( $line_items_data, $fees ), $this->object );
	}

	public function get_fee_line_items() {
		$line_items_data = array();
		$fees = $this->object->get_fees();
		if ( !empty( $fees ) ) {
			foreach( $fees as $fee ) {
				$tax_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $fee->get_tax_class() );

				if ( method_exists( $fee, 'get_amount' ) && $fee->get_amount() ) {
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

	public function get_transaction_id() {
		return apply_filters( 'taxjar_get_order_transaction_id', $this->get_record_id(), $this->object );
	}
}
