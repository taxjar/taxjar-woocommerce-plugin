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

		$query = "UPDATE {$table_name} SET batch_id = {$batch_id} WHERE queue_id IN ('{$queue_ids_string}')";
		$wpdb->get_results( $query );
	}

	/**
	 * Get the queue ids of all active (new and awaiting) records in queue
	 *
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_all_active_in_queue() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT queue_id FROM {$table_name} WHERE status IN ( 'new', 'awaiting' ) AND batch_id = 0";
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
	 * Remove all record from queue
	 *
	 * @return int|bool Bool true on success or false on error
	 */
	static function clear_queue() {
		global $wpdb;
		$table_name = self::get_queue_table_name();
		$query = "TRUNCATE TABLE {$table_name}";
		$result = $wpdb->query( $query );
		return $result;
	}

}