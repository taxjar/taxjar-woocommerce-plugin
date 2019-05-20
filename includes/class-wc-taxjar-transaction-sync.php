<?php
/**
 * TaxJar Transaction Sync
 *
 * @package  WC_Taxjar_Transaction_Sync
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Transaction_Sync {

	const PROCESS_QUEUE_HOOK = 'taxjar_process_queue';
	const PROCESS_BATCH_HOOK = 'taxjar_process_record_batch';
	const QUEUE_GROUP = 'taxjar-queue-group';

	public $taxjar_integration;

	public function __construct( $integration ) {
		self::init();

		$this->taxjar_integration = $integration;
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule_process_queue' ) );
		add_action( self::PROCESS_QUEUE_HOOK, array( __CLASS__, 'process_queue' ) );
		add_action( self::PROCESS_BATCH_HOOK, array( __CLASS__, 'process_batch' ) );

		add_action( 'woocommerce_new_order', array( __CLASS__, 'order_updated' ) );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'order_updated' ) );
	}

	public static function schedule_process_queue() {
		$next_timestamp = as_next_scheduled_action( self::PROCESS_QUEUE_HOOK );

		if ( ! $next_timestamp ) {
			as_schedule_recurring_action( time(), MINUTE_IN_SECONDS * 5, self::PROCESS_QUEUE_HOOK, array(), self::QUEUE_GROUP );
		}
	}

	/**
	 * Process the record queue and schedule batches
	 *
	 * @return null
	 */
	public static function process_queue() {
		$active_records = WC_Taxjar_Record_Queue::get_all_active_in_queue();

		if ( empty( $active_records ) ) {
			return;
		}

		$active_records = array_map( function( $arr ) {
			return (int)$arr[ 'queue_id' ];
		}, $active_records );

		// Allow batch size to be altered through a filter, may need this to be adjustable for performance
		$batches = array_chunk( $active_records, apply_filters( 'taxjar_record_batch_size', 50 ) );

		foreach( $batches as $batch ) {
			$batch_id = as_schedule_single_action( time(), self::PROCESS_BATCH_HOOK, array( 'queue_ids' => $batch ), self::QUEUE_GROUP );
			WC_Taxjar_Record_Queue::add_records_to_batch( $batch, $batch_id );
		}
	}

	/**
	 * Process the batch and sync records to TaxJar
	 *
	 * @return null
	 */
	public static function process_batch( $args ) {
		if ( empty( $args[ 'queue_ids' ] ) ) {
			return;
		}

		$records = WC_Taxjar_Record_Queue::get_data_for_batch( $args[ 'queue_ids' ] );

		foreach( $records as $record ) {
			if ( $record[ 'status' ] != 'in_batch' ) {
				continue;
			}
		}

	}

	public static function order_updated( $order_id ) {
		$order = wc_get_order( $order_id );
		$status = $order->get_status();

		if ( ! $status == 'completed' ) {
			return;
		}

		$queue_id = WC_Taxjar_Record_Queue::find_active_in_queue( $order_id );
		$data = WC_Taxjar_Record_Queue::get_order_data( $order );

		if ( $queue_id === false ) { // no record in queue
			WC_Taxjar_Record_Queue::add_to_queue( $order_id, 'order', $data );
		} else {
			WC_Taxjar_Record_Queue::update_queue( $queue_id, $data );
		}

		return $status;
	}

	public function maybe_create_order_in_taxjar( $queue_id, $order_id, $data ) {

		if ( ! apply_filters( 'taxjar_should_sync_order_to_taxjar', true, $order_id, $data ) ) {
			return false;
		}

		$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 429, 500, 503 );

		$response = $this->create_order_taxjar_api_request( $order_id, $data );

		if ( is_wp_error( $response ) ) {
			// handle wordpress error and add message to log here
			return $response;
		} elseif ( $response['response']['code'] == 200 ) {
			// successful order creation in taxjar - now need to update record queue
			WC_Taxjar_Record_Queue::sync_success( $queue_id );
			return true;
		} elseif ( $response['response']['code'] == 422 ) { // transaction already exists in TaxJar
			$update_response = $this->update_order_taxjar_api_request( $order_id, $data );

			if ( is_wp_error( $update_response ) ) {

			} elseif ( $update_response['response']['code'] == 200 ) {
				WC_Taxjar_Record_Queue::sync_success( $queue_id );
				return true;
			} else {
				WC_Taxjar_Record_Queue::sync_failure( $queue_id );
				return false;
			}
		} elseif ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
			WC_Taxjar_Record_Queue::sync_failure( $queue_id );
			return false;
		}

		return $response;
	}

	public function create_order_taxjar_api_request( $order_id, $data ) {
		$url = $this->taxjar_integration->uri . 'transactions/orders';
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

	public function update_order_taxjar_api_request( $order_id, $data ) {
		$url = $this->taxjar_integration->uri . 'transactions/orders/' . $order_id;
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

}