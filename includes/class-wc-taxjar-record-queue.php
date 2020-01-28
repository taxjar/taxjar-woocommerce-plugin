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
	 * Get the queue ids of all active (new and awaiting) records in queue that are not in a batch
	 *
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_all_active_in_queue() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT queue_id FROM {$table_name} WHERE status IN ( 'new', 'awaiting' )";
		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results;
	}

	/**
	 * Get the queue ids of a set number of active (new and awaiting) records in queue
	 *
	 * @param int $number_to_process - number of records to get from queue
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_active_records_to_process( $number_to_process ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT * FROM {$table_name} WHERE status IN ( 'new', 'awaiting' ) LIMIT {$number_to_process}";
		$results = $wpdb->get_results( $query, ARRAY_A );

		return $results;
	}

	/**
	 * Gets the total number of records in queue that need processing
	 *
	 * @return int
	 */
	static function get_active_record_count() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT COUNT(*) FROM {$table_name} WHERE status IN ( 'new', 'awaiting' )";
		$results = $wpdb->get_var( $query );

		return $results;
	}

	/**
	 * Get the queue ids of all records in queue limiting by 20
	 *
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_records_from_queue( ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT * FROM {$table_name} ORDER BY queue_id DESC LIMIT 0, 20";
		$results = $wpdb->get_results( $query );
		return $results;
	}

	/**
	 * Get the record and queue ids of all active (new and awaiting) records in queue
	 *
	 * @return array|bool - if active records are found returns array, otherwise returns false
	 */
	static function get_all_active_record_ids_in_queue() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "SELECT queue_id, record_id FROM {$table_name} WHERE status IN ( 'new', 'awaiting' )";
		$results = $wpdb->get_results( $query, ARRAY_A );

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
		$results = $wpdb->get_results( $query, ARRAY_A );

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

	/**
	 * Remove all active orders and refunds from queue
	 */
	static function clear_active_transaction_records() {
		global $wpdb;

		$table_name = self::get_queue_table_name();

		$query = "DELETE FROM {$table_name} WHERE status IN ( 'new', 'awaiting' ) AND record_type IN ( 'order', 'refund' )";
		$results = $wpdb->query( $query );

		return $results;
	}

	/**
	 * Cleans up queue where batches have finished but records never updated queue ID to 0
	 */
	public static function clean_orphaned_records() {
		global $wpdb;
		$table_name = self::get_queue_table_name();

		$args = array(
			'hook' => WC_Taxjar_Transaction_Sync::PROCESS_BATCH_HOOK,
			'status' => ActionScheduler_Store::STATUS_PENDING,
			'per_page' => 0,
		);
		$active_batches = as_get_scheduled_actions( $args, 'ids' );
		$active_batches[] = 0;
		$active_batches_string = join( "','", $active_batches );

		$query = "UPDATE {$table_name} SET batch_id = 0 WHERE batch_id NOT IN ('{$active_batches_string}') AND status IN ('new', 'awaiting')";
		$results = $wpdb->get_results( $query );
	}

}