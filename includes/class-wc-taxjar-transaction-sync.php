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
		$this->init();
		$this->taxjar_integration = $integration;
	}

	public function init() {
		add_action( 'init', array( __CLASS__, 'schedule_process_queue' ) );
		add_action( self::PROCESS_QUEUE_HOOK, array( __CLASS__, 'process_queue' ) );
		add_action( self::PROCESS_BATCH_HOOK, array( $this, 'process_batch' ) );

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
	 * @return array - array of batch IDs that were created
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

		$batch_ids = array();
		foreach( $batches as $batch ) {
			$batch_id = as_schedule_single_action( time(), self::PROCESS_BATCH_HOOK, array( 'queue_ids' => $batch ), self::QUEUE_GROUP );
			$batch_ids[] = $batch_id;
			WC_Taxjar_Record_Queue::add_records_to_batch( $batch, $batch_id );
		}

		return $batch_ids;
	}

	/**
	 * Process the batch and sync records to TaxJar
	 *
	 * @return null
	 */
	public function process_batch( $args ) {
		if ( empty( $args[ 'queue_ids' ] ) ) {
			return;
		}

		$record_rows = WC_Taxjar_Record_Queue::get_data_for_batch( $args[ 'queue_ids' ] );
		foreach( $record_rows as $record_row ) {
			$record = TaxJar_Record::create_from_record_row( $record_row );
			if ( $record == false ) {
				continue;
			}

			if ( $record->get_status() != 'new' && $record->get_stauts() != 'awaiting' ) {
				continue;
			}

			if ( empty( $record->get_batch_id() ) ) {
				continue;
			}

			$record->sync();
		}
	}

	public static function order_updated( $order_id ) {
		$order = wc_get_order( $order_id );
		$status = $order->get_status();

		if ( $status != "completed" ) {
			return;
		}

		$queue_id = WC_Taxjar_Record_Queue::find_active_in_queue( $order_id );
		$data = WC_Taxjar_Record_Queue::get_order_data( $order );

		$status = 'new';
		$taxjar_last_sync = get_post_meta( $order_id, '_taxjar_last_sync', true );
		if ( !empty( $taxjar_last_sync ) ) {
			$status = 'awaiting';
		}

		if ( $queue_id === false ) { // no record in queue
			WC_Taxjar_Record_Queue::add_to_queue( $order_id, 'order', $data, $status );
		} else {
			WC_Taxjar_Record_Queue::update_queue( $queue_id, $data, null, $status );
		}

		return $status;
	}

	public function maybe_create_order_in_taxjar( $queue_id, $order_id, $data ) {

		if ( ! apply_filters( 'taxjar_should_sync_order_to_taxjar', true, $order_id, $data ) ) {
			return false;
		}

		$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 429, 500, 503 );
		$success_responses = array( 200, 201 );

		$response = $this->create_order_taxjar_api_request( $order_id, $data );

		if ( is_wp_error( $response ) ) {
			// handle wordpress error and add message to log here
			WC_Taxjar_Record_Queue::sync_failure( $queue_id );
			return $response;
		}

		if ( $response['response']['code'] == 422 ) {
			$response = $this->update_order_taxjar_api_request( $order_id, $data );

			// must recheck for wp error after generating new response
			if ( is_wp_error( $response ) ) {
				// handle wordpress error and add message to log here
				WC_Taxjar_Record_Queue::sync_failure( $queue_id );
				return $response;
			}
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
			WC_Taxjar_Record_Queue::sync_failure( $queue_id );
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $success_responses ) ) {
			WC_Taxjar_Record_Queue::sync_success( $queue_id, $order_id );
			return true;
		}

		// handle any unexpected response value
		WC_Taxjar_Record_Queue::sync_failure( $queue_id );
		return false;
	}

	public function maybe_update_order_in_taxjar( $queue_id, $order_id, $data ) {

		if ( ! apply_filters( 'taxjar_should_sync_order_to_taxjar', true, $order_id, $data ) ) {
			return false;
		}

		$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 422, 429, 500, 503 );
		$success_responses = array( 200, 201 );

		$response = $this->update_order_taxjar_api_request( $order_id, $data );

		if ( is_wp_error( $response ) ) {
			// handle wordpress error and add message to log here
			WC_Taxjar_Record_Queue::sync_failure( $queue_id );
			return $response;
		}

		if ( $response['response']['code'] == 404 ) {
			$response = $this->create_order_taxjar_api_request( $order_id, $data );

			// must recheck for wp error after generating new response
			if ( is_wp_error( $response ) ) {
				// handle wordpress error and add message to log here
				WC_Taxjar_Record_Queue::sync_failure( $queue_id );
				return $response;
			}
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
			WC_Taxjar_Record_Queue::sync_failure( $queue_id );
			return false;
		}

		if ( in_array( $response[ 'response' ][ 'code' ], $success_responses ) ) {
			WC_Taxjar_Record_Queue::sync_success( $queue_id, $order_id );
			return true;
		}

		// handle any unexpected response value
		WC_Taxjar_Record_Queue::sync_failure( $queue_id );
		return false;
	}

	public function create_order_taxjar_api_request( $order_id, $data ) {
		$url = $this->taxjar_integration->uri . 'transactions/orders';
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

	public function update_order_taxjar_api_request( $order_id, $data ) {
		$url = $this->taxjar_integration->uri . 'transactions/orders/' . $order_id;
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

	public function delete_order_taxjar_api_request( $order_id ) {
		$url = $this->taxjar_integration->uri . 'transactions/orders/' . $order_id;
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

}