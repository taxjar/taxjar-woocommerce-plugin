<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Order_Record extends TaxJar_Record {

	public function __construct( $record_id = null, $queue_id = null ) {
		parent::__construct( $record_id, $queue_id );
	}

	static function load( $record_id = null, $queue_id = null ) {
		try {
			return new TaxJar_Order_Record( $record_id, $queue_id );
		} catch ( Exception $e ) {
			return false;
		}
	}

	function load_object() {
		$order = wc_get_order( $this->get_record_id() );
		if ( ! is_object( $order ) ) {
			throw new Exception( "Order object does not exist" );
		}

		$this->object = $order;
	}

	public function get_record_type() {
		return 'order';
	}

	public function sync_success() {
		global $wpdb;
		$table_name = self::get_queue_table_name();
		$current_datetime =  gmdate( 'Y-m-d H:i:s' );
		$query = "UPDATE {$table_name} SET status = 'complete', processed_datetime = '{$current_datetime}' WHERE queue_id = {$this->get_queue_id()}";
		$results = $wpdb->get_results( $query );

		update_post_meta( $this->get_record_id(), '_taxjar_last_sync', $current_datetime );

		return $results;
	}

	public function sync_failure() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$retry_count = $this->get_retry_count() + 1;
		$this->set_retry_count( $retry_count );
		if ( $this->get_retry_count() >= 3 ) {
			$query = "UPDATE {$table_name} SET status = 'failed', retry_count = {$retry_count}, batch_id = 0 WHERE queue_id = {$this->get_queue_id()}";
			$results = $wpdb->get_results( $query );
		} else {
			$query = "UPDATE {$table_name} SET retry_count = {$retry_count}, batch_id = 0, status = 'new' WHERE queue_id = {$this->get_queue_id()}";
			$results = $wpdb->get_results( $query );
		}
	}

	public function create_in_taxjar() {
		$data = $this->get_order_data();
		$url = self::API_URI . 'transactions/orders';
		$data[ 'provider' ] = 'woo';
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

	public function update_in_taxjar(){
		$order_id = $this->get_transaction_id();
		$data = $this->get_order_data();

		$url = self::API_URI . 'transactions/orders/' . $order_id;
		$data[ 'provider' ] = 'woo';
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

	public function delete_in_taxjar(){
		$order_id = $this->get_transaction_id();
		$url = self::API_URI . 'transactions/orders/' . $order_id;
		$data = array(
			'transaction_id' => $order_id,
			'provider' => 'woo'
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

	public function get_order_data() {
		$store_settings   = $this->taxjar_integration->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $this->object->get_total() - $this->object->get_total_tax();

		$order_data = array(
			'transaction_id' => $this->get_transaction_id(),
			'transaction_date' => $this->object->get_date_created()->date( DateTime::ISO8601 ),
			'from_country' => $from_country,
			'from_zip' => $from_zip,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'to_country' => $this->object->get_shipping_country(),
			'to_zip' => $this->object->get_shipping_postcode(),
			'to_state' => $this->object->get_shipping_state(),
			'to_city' => $this->object->get_shipping_city(),
			'to_street' => $this->object->get_shipping_address_1(),
			'amount' => $amount,
			'shipping' => $this->object->get_shipping_total(),
			'sales_tax' => $this->object->get_total_tax(),
			'line_items' => $this->get_line_items(),
		);

		$customer_id = $this->object->get_customer_id();
		if ( $customer_id ) {
			$order_data[ 'customer_id' ] = $customer_id;
		}

		return $order_data;
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

		return $line_items_data;
	}

	public function get_transaction_id() {
		return apply_filters( 'taxjar_get_order_transaction_id', $this->object->get_id() );
	}
}