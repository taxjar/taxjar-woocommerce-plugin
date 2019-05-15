<?php
/**
 * TaxJar Record Queue
 *
 * @package  WC_Taxjar_Record_Queue
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Record_Queue {

	/**
	 * Get queue table name
	 *
	 * @return string - name of queue table in db
	 */
	static function get_queue_table_name() {
		global $wpdb;
		return "{$wpdb->prefix}taxjar_record_queue";
	}

	/**
	 * Add record to queue.
	 *
	 * @param int $record_id - id of the record to add to the queue (normally a post id)
	 * @param string $record_type - type of record to be synced to TaxJar
	 * @param array $data - array of data to be synced to TaxJar
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function add_to_queue( $record_id, $record_type, $data, $status = 'new', $batch_id = 0 ) {
		// validate parameters
		if ( empty( $record_id ) || empty( $data ) || empty( $record_type ) ) {
			return false;
		}

		// validate record type and status
		if ( ! self::is_valid_record_type( $record_type ) || ! self::is_valid_status( $status ) ) {
			return false;
		}

		global $wpdb;
		$insert = array(
			'record_id'        => $record_id,
			'record_type'      => $record_type,
			'record_data'      => json_encode( $data ),
			'status'           => $status,
			'batch_id'         => $batch_id,
			'created_datetime' => gmdate( 'Y-m-d H:i:s' )
		);

		$result = $wpdb->insert( self::get_queue_table_name(), $insert );
	}

	/**
	 * Remove record from queue.
	 *
	 * @param int $queue_id - queue id of item to remove
	 * @return null
	 */
	static function remove_from_queue( $queue_id ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		return $wpdb->delete( $table_name, array( 'queue_id' => $queue_id ) );
	}

	/**
	 * Update record in queue.
	 *
	 * @param int $queue_id - queue id of item to update
	 * @param array $record_data - array of data to be synced to TaxJar
	 * @param int $record_id - id of the record to add to the queue (normally a post id)
	 * @param string $record_type - type of record to be synced to TaxJar
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function update_queue( $queue_id, $record_data = null, $record_id = null, $record_type = null ) {
		global $wpdb;
		$table_name = self::get_queue_table_name();

		$data = array();

		if ( ! empty( $record_type ) && self::is_valid_record_type( $record_type ) ) {
			$data[ 'record_type' ] = $record_type;
		}

		if ( ! empty( $record_id ) ) {
			$data[ 'record_id' ] = $record_id;
		}

		if ( ! empty( $record_data ) ) {
			$data[ 'record_data' ] = json_encode( $record_data );
		}

		if ( empty( $data ) ) {
			return false;
		}

		$where = array(
			'queue_id' => $queue_id
		);

		$result = $wpdb->update( $table_name, $data, $where );
		return $result;
	}

	/**
	 * Update record status and batch id in queue
	 *
	 * @param array $queue_ids - array of queue ids that were added to batch
	 * @param array $batch_id - id of batch
	 * @return null
	 */
	static function add_records_to_batch( $queue_ids, $batch_id ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		$queue_ids_string = join( "','", $queue_ids );

		$query = "UPDATE {$table_name} SET status = 'in_batch', batch_id = {$batch_id} WHERE queue_id IN ('{$queue_ids_string}')";
		$wpdb->get_results( $query );
	}

	/**
	 * Find record in queue
	 *
	 * @param int $record_id - record id of item to search queue for
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function find_active_in_queue( $record_id ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		$query = "SELECT queue_id FROM {$table_name} WHERE record_id = {$record_id} AND status IN ( 'new', 'in_batch' )";
		$results = $wpdb->get_results( $query,  ARRAY_A );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return false;
		}

		$last_element = end( $results );
		if ( empty( $last_element[ 'queue_id' ] ) ) {
			return false;
		}

		return (int)$last_element[ 'queue_id' ];
	}

	/**
	 * Get the queue ids of all active (processing and in_batch) records in queue
	 *
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_all_active_in_queue() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT queue_id FROM {$table_name} WHERE status IN ( 'new' )";
		$results = $wpdb->get_results( $query,  ARRAY_A );

		return $results;
	}

	/**
	 * Get the data for all records in the batch
	 *
	 * @return array
	 */
	static function get_data_for_batch( $queue_ids ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		$queue_ids_string = join( "','", $queue_ids );

		$query = "SELECT * FROM {$table_name} WHERE queue_id IN ('{$queue_ids_string}')";
		$results = $wpdb->get_results( $query,  ARRAY_A );

		return $results;
	}

	/**
	 * Check if record type is valid.
	 *
	 * @param string $record_type
	 * @return bool
	 */
	static function is_valid_record_type( $record_type ) {
		$valid_types = array( 'order' );
		if ( in_array( $record_type, $valid_types ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if status is valid.
	 *
	 * @param string $status
	 * @return bool
	 */
	static function is_valid_status( $status ) {
		$valid_types = array( 'new', 'failed', 'in_batch', 'completed', 'processing' );
		if ( in_array( $status, $valid_types ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get order data to store in record queue
	 *
	 * @param WC_Order $order
	 * @return array|bool
	 */
	static function get_order_data( $order ) {

		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$taxjar_integration = WC()->integrations->integrations[ 'taxjar-integration' ];

		$store_settings   = $taxjar_integration->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];

		$amount = $order->get_total() - $order->get_total_tax();

		$order_data = array(
			'transaction_id' => $order->get_order_number(),
			'transaction_date' => $order->get_date_created()->date( DateTime::ISO8601 ),
			'from_country' => $from_country,
			'from_zip' => $from_zip,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'to_country' => $order->get_shipping_country(),
			'to_zip' => $order->get_shipping_postcode(),
			'to_state' => $order->get_shipping_state(),
			'to_city' => $order->get_shipping_city(),
			'to_street' => $order->get_shipping_address_1(),
			'amount' => $amount,
			'shipping' => $order->get_shipping_total(),
			'sales_tax' => $order->get_total_tax(),
			'customer_id' => '',
			'line_items' => self::get_line_items( $order ),
		);

		//TODO: Should we sync order number or order ID?
		//TODO: is transaction date the date created, paid or completed?
		//TODO: from address - is it always the store address or can it be different?
		//TODO: better to get from address at time order is added to queue or when syncing order to taxjar?
		//TODO: do we need to send over a customer ID?
		//TODO: is there any scenario where shipping address wouldn't be present on the order - get billing instead?

		return $order_data;

	}

	/**
	 * Get line items from order
	 *
	 * @param WC_Order $order
	 * @return array|bool
	 */
	static function get_line_items( $order ) {
		$line_items_data = array();
		$items = $order->get_items();

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
					'description' => '',
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
					'sales_tax' => $item->get_total_tax(),
				);
			}
		}


		return $line_items_data;

		//TODO: What is the id that we currently pull for each line item from the woocommerce API?
		//TODO: what to use as the product identifier?
		//TODO: should we send fees as line items?
		//TODO: description - use short_description of product? would have to only take first 255 chars
		//TODO: unit price include or exclude discount?

	}

}