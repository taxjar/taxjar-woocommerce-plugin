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

		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'refund_created' ), 10, 2 );

		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_action' ) );
		add_action( 'woocommerce_order_action_taxjar_sync_action', array( $this, 'manual_order_sync' ) );
	}

	public function add_order_meta_box_action( $actions ) {
		global $theorder;

		if ( ! $theorder->has_status( 'completed') ) {
			return $actions;
		}

		$actions['taxjar_sync_action'] = __( 'Sync order to TaxJar', 'taxjar' );
		return $actions;
	}

	public function manual_order_sync( $order ) {
		$record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $order->get_id(), true );
		}
		$record->load_object();

		$result = $record->sync();
		if ( $result ) {
			$order->add_order_note( __( 'Order manually synced to TaxJar by admin action.', 'taxjar' ) );
		} else {
			$order->add_order_note( __( 'Order manual sync failed. Check TaxJar logs for additional details', 'taxjar' ) );
		}
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

			if ( $record->get_status() != 'new' && $record->get_status() != 'awaiting' ) {
				continue;
			}

			if ( empty( $record->get_batch_id() ) ) {
				continue;
			}

			$record->sync();
		}
	}

	public static function order_updated( $order_id ) {
		$queue_id = TaxJar_Order_Record::find_active_in_queue( $order_id );
		if ( $queue_id ) {
			return;
		}

		$record = new TaxJar_Order_Record( $order_id, true );
		$record->load_object();
		if ( ! $record->object ) {
			return;
		}

		if ( ! apply_filters( 'taxjar_should_sync_order', $record->should_sync() ) ) {
			return;
		}

		$taxjar_last_sync = $record->get_last_sync_time();
		if ( !empty( $taxjar_last_sync ) ) {
			$record->set_status( 'awaiting' );
		}

		$record->save();
	}

	public static function refund_created( $order_id, $refund_id ) {
		$queue_id = TaxJar_Refund_Record::find_active_in_queue( $refund_id );
		if ( $queue_id ) {
			return;
		}

		$record = new TaxJar_Refund_Record( $refund_id, true );
		$record->load_object();
		if ( ! $record->object ) {
			return;
		}

		if ( ! apply_filters( 'taxjar_should_sync_refund', $record->should_sync() ) ) {
			return;
		}

		$taxjar_last_sync = $record->get_last_sync_time();
		if ( !empty( $taxjar_last_sync ) ) {
			$record->set_status( 'awaiting' );
		}

		$record->save();
	}
}