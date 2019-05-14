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
	 * @param int $record_id - id of the record to add to the queue (normally a post id)
	 * @param string $record_type - type of record to be synced to TaxJar
	 * @param array $data - array of data to be synced to TaxJar
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function update_queue( $queue_id, $record_id = null, $record_type = null, $data = null ) {

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

}