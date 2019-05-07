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
	 * Add record to queue.
	 *
	 * @param int $record_id - id of the record to add to the queue (normally a post id)
	 * @param string $record_type - type of record to be synced to TaxJar
	 * @param array $data - array of data to be synced to TaxJar
	 * @return int|bool - if successful returns queue_id otherwise returns false
	 */
	static function add_to_queue( $record_id, $record_type, $data ) {

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

	}

	/**
	 * Check if record type is valid.
	 *
	 * @param int $record_type
	 * @return bool
	 */
	static function is_valid_record_type( $record_type ) {

	}

}