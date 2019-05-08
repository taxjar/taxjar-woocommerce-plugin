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
	static function add_to_queue( $record_id, $record_type, $data ) {
		// validate parameters
		if ( empty( $record_id ) || empty( $data ) || empty( $record_type ) ) {
			return false;
		}

		// validate record type
		if ( ! self::is_valid_record_type( $record_type ) ) {
			return false;
		}

		global $wpdb;
		$insert = array(
			'record_id' => $record_id,
			'record_type' => $record_type,
			'record_data' => json_encode( $data ),
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
	 * Remove record from queue.
	 *
	 * @param int $record_id - record id of item to search queue for
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function find_in_queue( $record_id ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		$query = "SELECT queue_id FROM {$table_name} WHERE record_id = {$record_id}";
		$results = $wpdb->get_results( $query );

		return $results;
	}

	/**
	 * Check if record type is valid.
	 *
	 * @param int $record_type
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

}