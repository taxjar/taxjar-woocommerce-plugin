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

		add_action( 'wp_trash_post', array( $this, 'maybe_delete_transaction_from_taxjar' ), 9, 1 );
		add_action( 'before_delete_post', array( $this, 'maybe_delete_transaction_from_taxjar' ), 9, 1 );
		add_action( 'before_delete_post', array( $this, 'maybe_delete_refund_from_taxjar' ), 9, 1 );
		add_action( 'untrashed_post', array( $this, 'untrash_post' ), 11 );

		add_action( 'woocommerce_order_status_cancelled', array( $this, 'order_cancelled' ), 10, 2 );
	}

	public function add_order_meta_box_action( $actions ) {
		global $theorder;

		$valid_statuses = array( 'completed', 'refunded' );
		if ( ! in_array( $theorder->get_status(), $valid_statuses ) ) {
			return $actions;
		}

		$actions['taxjar_sync_action'] = __( 'Sync order to TaxJar', 'taxjar' );
		return $actions;
	}

	/**
	 * Prints debug info to wp-content/uploads/wc-logs/taxjar-transaction-sync-*.log
	 *
	 * @return void
	 */
	public function _log( $message ) {
		do_action( 'taxjar_transaction_sync_log', $message );
		if ( $this->taxjar_integration->debug ) {
			if ( ! isset( $this->log ) ) {
				$this->log = new WC_Logger();
			}
			if ( is_array( $message ) || is_object( $message ) ) {
				$this->log->add( 'taxjar-transaction-sync', print_r( $message, true ) );
			} else {
				$this->log->add( 'taxjar-transaction-sync', $message );
			}
		}
	}

	public function log_with_record_error( $message, $record ) {
		$error = $record->get_error();
		if ( ! empty( $error ) ) {
			if ( ! empty( $error['message'] ) ) {
				$message .= ' Error Message: ' . $error['message'];
			}
			if ( ! empty( $error['data'] ) ) {
				$message .= 'Data: ' . $error['data'];
			}
			$record->clear_error();
		}
		$this->_log( $message );
	}

	public function manual_order_sync( $order ) {
		$record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $order->get_id(), true );
		}
		$record->set_force_push( 1 );
		$record->load_object();
		$order_result = $record->sync();

		if ( $order_result ) {
			$this->_log( 'Order ID# ' . $record->get_record_id() . ' (Queue ID ' . $record->get_queue_id() . ') successfully manual synced to TaxJar.' );
		} else {
			$this->log_with_record_error( 'Order ID# ' . $record->get_record_id() . ' (Queue ID ' . $record->get_queue_id() . ') failed to manual sync to TaxJar.', $record );
			return;
		}

		$refunds = $order->get_refunds();
		$refund_success = true;
		foreach( $refunds as $refund ) {
			$refund_record = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );
			if ( ! $refund_record ) {
				$refund_record = new TaxJar_Refund_Record( $refund->get_id(), true );
			}

			$refund_record->set_force_push( 1 );
			$refund_record->load_object();
			$refund_result = $refund_record->sync();
			if ( ! $refund_result ) {
				$refund_success = false;
			}

			if ( $refund_result ) {
				$this->_log( 'Refund ID# ' . $refund_record->get_record_id() . ' (Queue ID ' . $refund_record->get_queue_id() . ') successfully manual synced to TaxJar.' );
			} else {
				$this->log_with_record_error( 'Refund ID# ' . $refund_record->get_record_id() . ' (Queue ID ' . $refund_record->get_queue_id() . ') failed to manual sync to TaxJar.', $refund_record );
			}
		}

		if ( $order_result && $refund_success ) {
			$order->add_order_note( __( 'Order and refunds (if any) manually synced to TaxJar by admin action.', 'taxjar' ) );
		} else if ( $order_result && ! $refund_success ) {
			$order->add_order_note( __( 'Order manual sync failed. Check TaxJar logs for additional details', 'taxjar' ) );
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
		if ( empty( $args ) ) {
			return;
		}

		if ( empty( $args[ 'queue_ids' ] ) ) {
			$queue_ids = $args;
		} else {
			$queue_ids = $args[ 'queue_ids' ];
		}

		$record_rows = WC_Taxjar_Record_Queue::get_data_for_batch( $queue_ids );
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

			$previous_sync_datetime = $record->object->get_meta( '_taxjar_last_sync', true );

			$result = $record->sync();

			if ( $result && $record->get_record_type() == 'order' ) {
				if ( empty( $previous_sync_datetime ) ) {
					$record->object->add_order_note( __( 'Order synced to TaxJar', 'taxjar' ) );
				}
			}

			if ( $result ) {
				$this->_log( ucfirst( $record->get_record_type() ) .' ID# ' . $record->get_record_id() . ' (Queue ID ' . $record->get_queue_id() . ') successfully synced to TaxJar.' );
			} else {
				$this->log_with_record_error( ucfirst( $record->get_record_type() ) . ' ID# ' . $record->get_record_id() . ' (Queue ID ' . $record->get_queue_id() . ') failed to sync to TaxJar.', $record );
				return;
			}
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
		if ( ! empty( $taxjar_last_sync ) ) {
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

	public function untrash_post( $id ) {
		if ( ! $id ) {
			return;
		}

		$post_type = get_post_type( $id );
		if ( 'shop_order' != $post_type ) {
			return;
		}

		$record = TaxJar_Order_Record::find_active_in_queue( $id );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $id, true );
		}
		$record->load_object();

		if ( $record->should_sync() ) {
			$record->set_force_push( true );
			$record->save();
			$refunds = $record->object->get_refunds();
			foreach( $refunds as $refund ) {
				$refund_record = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );
				if ( ! $refund_record ) {
					$refund_record = new TaxJar_Refund_Record( $refund->get_id(), true );
				}

				$refund_record->load_object();
				if ( $refund_record->should_sync() ) {
					$refund_record->set_force_push( true );
				    $refund_record->save();
				}
			}
		}
	}

	public function maybe_delete_transaction_from_taxjar( $post_id ) {
		if ( 'shop_order' != get_post_type( $post_id ) ) {
			return;
		}

		$record = TaxJar_Order_Record::find_active_in_queue( $post_id );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $post_id, true );
		}
		$record->load_object();

		$should_delete = false;
		if ( $record->get_object_hash() || $record->get_last_sync_time() ) {
			$should_delete = true;
		} else {
			if ( $record->should_sync() ) {
				$should_delete = true;
			}
		}

		if ( ! $should_delete ) {
			return;
		}

		$record->delete_in_taxjar();
		$record->delete();

		$refunds = $record->object->get_refunds();
		foreach( $refunds as $refund ) {
			$refund_record = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );
			if ( ! $refund_record ) {
				$refund_record = new TaxJar_Refund_Record( $refund->get_id(), true );
			}
			$refund_record->load_object();

			$refund_record->delete_in_taxjar();
			$refund_record->delete();
		}
	}

	public function maybe_delete_refund_from_taxjar( $post_id ) {
		if ( 'shop_order_refund' != get_post_type( $post_id ) ) {
			return;
		}

		$record = TaxJar_Refund_Record::find_active_in_queue( $post_id );
		if ( ! $record ) {
			$record = new TaxJar_Refund_Record( $post_id, true );
		}
		$record->load_object();

		$should_delete = false;
		if ( $record->get_object_hash() || $record->get_last_sync_time() ) {
			$should_delete = true;
		} else {
			if ( $record->should_sync() ) {
				$should_delete = true;
			}
		}

		if ( ! $should_delete ) {
			return;
		}

		$record->delete_in_taxjar();
		$record->delete();
	}

	public function order_cancelled( $order_id, $order ) {
		$record = TaxJar_Order_Record::find_active_in_queue( $order_id );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $order_id, true );
		}
		$record->load_object();

		$should_delete = false;
		$order = wc_get_order( $order_id );

		if ( $record->get_object_hash() || $record->get_last_sync_time() ) {
			$should_delete = true;
		} else if ( $order->get_date_completed() ) {
			if ( $record->should_sync( true ) ) {
				$should_delete = true;
			}
		}

		if ( ! $should_delete ) {
			return;
		}

		$record->delete_in_taxjar();
		$record->delete();

		$refunds = $record->object->get_refunds();
		foreach( $refunds as $refund ) {
			$refund_record = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );
			if ( ! $refund_record ) {
				$refund_record = new TaxJar_Refund_Record( $refund->get_id(), true );
			}
			$refund_record->load_object();

			$refund_record->delete_in_taxjar();
			$refund_record->delete();
		}
	}

	public function transaction_backfill( $start_date = null, $end_date = null, $force = false ) {
		global $wpdb;
		$queue_table = WC_Taxjar_Record_Queue::get_queue_table_name();
		$current_datetime = gmdate( 'Y-m-d H:i:s' );

		$order_ids = $this->get_orders_to_backfill( $start_date, $end_date, $force );
		if ( empty( $order_ids ) ) {
			return 0;
		}

		$transaction_ids = $order_ids;
		$refund_ids = $this->get_refunds_to_backfill( $order_ids );
		if ( ! empty( $refund_ids ) ) {
			$transaction_ids = array_merge( $order_ids, $refund_ids );
		}

		$active_records = WC_Taxjar_Record_Queue::get_all_active_record_ids_in_queue();
		$record_ids = array_map( function( $record ) {
			return $record['record_id'];
		}, $active_records );

		$diff = array_diff( $order_ids, $record_ids );

		if ( ! empty( $diff ) ) {
			if ( $force ) {
				$query = "INSERT INTO {$queue_table} (record_id, record_type, force_push, status, created_datetime) VALUES";
				$count = 0;
				foreach( $diff as $order_id ) {
					if ( ! $count ) {
						$query .= " ( {$order_id}, 'order', 1, 'awaiting', '{$current_datetime}' )";
					} else {
						$query .= ", ( {$order_id}, 'order', 1,  'awaiting', '{$current_datetime}' )";
					}
					$count++;
				}
			} else {
				$query = "INSERT INTO {$queue_table} (record_id, record_type, status, created_datetime) VALUES";
				$count = 0;
				foreach( $diff as $order_id ) {
					if ( ! $count ) {
						$query .= " ( {$order_id}, 'order', 'awaiting', '{$current_datetime}' )";
					} else {
						$query .= ", ( {$order_id}, 'order', 'awaiting', '{$current_datetime}' )";
					}
					$count++;
				}
			}
			$wpdb->query( $query );
		}

		$refunds_diff = array_diff( $refund_ids, $record_ids );

		if ( ! empty( $refunds_diff ) ) {
			if ( $force ) {
				$query = "INSERT INTO {$queue_table} (record_id, record_type, force_push, status, created_datetime) VALUES";
				$count = 0;
				foreach( $refunds_diff as $refund_id ) {
					if ( ! $count ) {
						$query .= " ( {$refund_id}, 'refund', 1, 'awaiting', '{$current_datetime}' )";
					} else {
						$query .= ", ( {$refund_id}, 'refund', 1,  'awaiting', '{$current_datetime}' )";
					}
					$count++;
				}
			} else {
				$query = "INSERT INTO {$queue_table} (record_id, record_type, status, created_datetime) VALUES";
				$count = 0;
				foreach( $refunds_diff as $refund_id ) {
					if ( ! $count ) {
						$query .= " ( {$refund_id}, 'refund', 'awaiting', '{$current_datetime}' )";
					} else {
						$query .= ", ( {$refund_id}, 'refund', 'awaiting', '{$current_datetime}' )";
					}
					$count++;
				}
			}
			$wpdb->query( $query );
		}

		if ( $force ) {
			$queue_ids = array_map( function( $record ) {
				return $record['queue_id'];
			}, $active_records );
			$records = array_combine( $record_ids, $queue_ids );

			$in_queue = array_values( array_intersect_key( $records, array_flip( $transaction_ids ) ) );
			if ( ! empty( $in_queue ) ) {
				$in_queue_string = implode( ', ', $in_queue );
				$query = "UPDATE {$queue_table} SET force_push = 1 WHERE queue_id in ( {$in_queue_string} )";
				$wpdb->query( $query );
			}
		}

		return count( $transaction_ids );
	}

	public function get_orders_to_backfill( $start_date = null, $end_date = null, $force = false ) {
		global $wpdb;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d H:i:s', strtotime( 'midnight', current_time( 'timestamp' ) ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d H:i:s', strtotime( '+1 day, midnight', current_time( 'timestamp' ) ) );
		}

		if ( $force ) {
			$posts = $wpdb->get_results(
					"
				SELECT p.id 
				FROM {$wpdb->posts} AS p 
				INNER JOIN {$wpdb->postmeta} AS order_meta_completed_date ON ( p.id = order_meta_completed_date.post_id )  AND ( order_meta_completed_date.meta_key = '_completed_date' ) 
				WHERE p.post_type = 'shop_order' 
				AND p.post_status IN ( 'wc-completed', 'wc-refunded' ) 
				AND p.post_date >= '{$start_date}' 
				AND p.post_date < '{$end_date}' 
				AND order_meta_completed_date.meta_value IS NOT NULL 
				ORDER BY p.post_date ASC
				", ARRAY_N
			);
		} else {
			$posts = $wpdb->get_results(
					"
				SELECT p.id 
				FROM {$wpdb->posts} AS p 
				INNER JOIN {$wpdb->postmeta} AS order_meta_completed_date ON ( p.id = order_meta_completed_date.post_id )  AND ( order_meta_completed_date.meta_key = '_completed_date' ) 
				LEFT JOIN {$wpdb->postmeta} AS order_meta_last_sync ON ( p.id = order_meta_last_sync.post_id )  AND ( order_meta_last_sync.meta_key = '_taxjar_last_sync' )
				WHERE p.post_type = 'shop_order' 
				AND p.post_status IN ( 'wc-completed', 'wc-refunded' ) 
				AND p.post_date >= '{$start_date}' 
				AND p.post_date < '{$end_date}' 
				AND order_meta_completed_date.meta_value IS NOT NULL 
				AND ((order_meta_last_sync.meta_value IS NULL) OR (p.post_modified_gmt > order_meta_last_sync.meta_value)) 
				ORDER BY p.post_date ASC
				", ARRAY_N
			);
		}

		if ( empty( $posts ) ) {
			return array();
		}

		return call_user_func_array( 'array_merge', $posts );
	}

	public function get_refunds_to_backfill( $order_ids ) {
		if ( empty( $order_ids ) ) {
			return array();
		}

		global $wpdb;
		$order_ids_string = implode( ',', $order_ids );

		$posts = $wpdb->get_results(
			"
			SELECT p.id 
			FROM {$wpdb->posts} AS p 
			WHERE p.post_type = 'shop_order_refund' 
			AND p.post_status = 'wc-completed' 
			AND p.post_parent IN ( {$order_ids_string} ) 
			ORDER BY p.post_date ASC
			", ARRAY_N
		);

		if ( empty( $posts ) ) {
			return array();
		}

		return call_user_func_array( 'array_merge', $posts );
	}

}