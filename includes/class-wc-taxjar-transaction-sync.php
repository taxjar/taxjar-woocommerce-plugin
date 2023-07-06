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

use Automattic\WooCommerce\Utilities\OrderUtil;

class WC_Taxjar_Transaction_Sync {

	const PROCESS_QUEUE_HOOK = 'taxjar_process_queue';
	const PROCESS_BATCH_HOOK = 'taxjar_process_record_batch';
	const QUEUE_GROUP = 'taxjar-queue-group';

	public $taxjar_integration;

	/**
	 * Constructor for class
	 */
	public function __construct( $integration ) {
		$this->taxjar_integration = $integration;
		$this->init();
	}

	/**
	 * Add actions and filters
	 */
	public function init() {
		$sales_tax_enabled = apply_filters( 'taxjar_enabled', isset( $this->taxjar_integration->settings['enabled'] ) && 'yes' == $this->taxjar_integration->settings['enabled'] );
		$transaction_sync_enabled = isset( $this->taxjar_integration->settings['taxjar_download'] ) && 'yes' == $this->taxjar_integration->settings['taxjar_download'];

		if ( $sales_tax_enabled || $transaction_sync_enabled ) {
			add_action( 'admin_init', array( __CLASS__, 'schedule_process_queue' ) );
			add_action( self::PROCESS_QUEUE_HOOK, array( $this, 'process_queue' ) );
		}

		if ( $transaction_sync_enabled ) {
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

			add_action( 'woocommerce_product_options_tax', array( $this, 'display_notice_after_product_options_tax' ), 5 );
			add_action( 'woocommerce_variation_options_tax', array( $this, 'display_notice_after_product_options_tax' ), 5 );
		}
	}

	/**
	 * Add action to edit order page in admin.
	 *
	 * @return array - list of order actions
	 */
	public function add_order_meta_box_action( $actions ) {
		global $theorder;

		$valid_statuses = apply_filters( 'taxjar_valid_order_statuses_for_sync', array( 'completed', 'refunded' ) );
		if ( ! in_array( $theorder->get_status(), $valid_statuses ) ) {
			return $actions;
		}

		if ( WC_Taxjar_Transaction_Sync::should_validate_order_completed_date() ) {
			if ( empty( $theorder->get_date_completed() ) ) {
				return $actions;
			}
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

	/**
	 * Manually sync order - triggered from edit order page in admin
	 *
	 * @param WC_Order $order - order to sync to TaxJar
	 * @return void
	 */
	public function manual_order_sync( $order ) {
		$record = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );
		if ( ! $record ) {
			$record = new TaxJar_Order_Record( $order->get_id(), true );
		}
		$record->set_force_push( 1 );
		$record->load_object();

		$this->_log( 'Manual sync for Order # ' . $record->get_record_id() . ' (Queue # ' . $record->get_queue_id() . ') triggered.' );
		$order_result = $record->sync();

		if ( $order_result ) {
			$refunds        = $order->get_refunds();
			$refund_success = true;
			foreach ( $refunds as $refund ) {
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
			}
		} else {
			$refund_success = false;
		}

		if ( $order_result && $refund_success ) {
			$order->add_order_note( __( 'Order and refunds (if any) manually synced to TaxJar by admin action.', 'taxjar' ) );
		} else if ( $order_result && ! $refund_success ) {
			$order->add_order_note( __( 'Order manual sync failed. Check TaxJar logs for additional details', 'taxjar' ) );
		} else {
			$order->add_order_note( __( 'Order manual sync failed. Check TaxJar logs for additional details', 'taxjar' ) );
		}
	}

	/**
	 * Schedule worker to process queue into batches
	 */
	public static function schedule_process_queue() {
		$next_timestamp = as_next_scheduled_action( self::PROCESS_QUEUE_HOOK );

		if ( ! $next_timestamp ) {
			$process_queue_interval = apply_filters( 'taxjar_process_queue_interval', 20 );
			$next_queue_process_time = time() + ( MINUTE_IN_SECONDS * $process_queue_interval );
			as_schedule_single_action( $next_queue_process_time, self::PROCESS_QUEUE_HOOK, array(), self::QUEUE_GROUP );
		}
	}

	/**
	 * Process the record queue
	 */
	public function process_queue() {
		$batch_size = apply_filters( 'taxjar_record_batch_size', 50 );
		$total_records_to_process = intval( WC_Taxjar_Record_Queue::get_active_record_count() );
		$active_records = WC_Taxjar_Record_Queue::get_active_records_to_process( $batch_size );
		$process_queue_interval = apply_filters( 'taxjar_process_queue_interval', 20 );

		$params['status'] = ActionScheduler_Store::STATUS_PENDING;
		$job_id = ActionScheduler::store()->find_action( self::PROCESS_QUEUE_HOOK, $params );

		if ( empty( $active_records ) && !$job_id ) {
			$next_queue_process_time = time() + ( MINUTE_IN_SECONDS * $process_queue_interval );
			as_schedule_single_action( $next_queue_process_time, self::PROCESS_QUEUE_HOOK, array(), self::QUEUE_GROUP );
			return;
		}

		foreach( $active_records as $record_row ) {
			$record = TaxJar_Record::create_from_record_row( $record_row );
			if ( $record == false ) {
				continue;
			}
			$this->_log( 'Record # ' . $record->get_record_id() . ' (Queue # ' . $record->get_queue_id() . ') triggered to sync.' );

			if ( $record->get_status() != 'new' && $record->get_status() != 'awaiting' ) {
				$this->_log( 'Record could not sync due to invalid status.' );
				continue;
			}

			$previous_sync_datetime = $record->object->get_meta( '_taxjar_last_sync', true );
			$result = $record->sync();

			if ( $result && $record::get_record_type() == 'order' ) {
				if ( empty( $previous_sync_datetime ) ) {
					$record->object->add_order_note( __( 'Order synced to TaxJar', 'taxjar' ) );
				}
			}
		}

		if ( !$job_id ) {
			if ( $total_records_to_process > $batch_size ) {
				as_schedule_single_action( time(), self::PROCESS_QUEUE_HOOK, array(), self::QUEUE_GROUP );
				return;
			}

			$next_queue_process_time = time() + ( MINUTE_IN_SECONDS * $process_queue_interval );
			as_schedule_single_action( $next_queue_process_time, self::PROCESS_QUEUE_HOOK, array(), self::QUEUE_GROUP );
		}
	}

	/**
	 * Runs when order is updated, checks if order should be added to queue and adds it
	 *
	 * @param int $order_id - id of updated order
	 */
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
		} else {
			$refunds = $record->object->get_refunds();

			foreach ( $refunds as $refund ) {
				$refund_queue_id = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );

				if ( $refund_queue_id ) {
					continue;
				}

				$refund_record = new TaxJar_Refund_Record( $refund->get_id(), true );
				$refund_record->load_object();

				if ( ! $refund_record->object ) {
					continue;
				}

				if ( ! apply_filters( 'taxjar_should_sync_refund', $refund_record->should_sync() ) ) {
					continue;
				}

				$refund_last_sync = $refund_record->get_last_sync_time();

				if ( ! empty( $refund_last_sync ) ) {
					$refund_record->set_status( 'awaiting' );
				}

				$refund_record->save();
			}
		}

		$record->save();
	}

	/**
	 * Runs when refund is created and maybe adds it to queue
	 *
	 * @param int $order_id - id of parent order
	 * @param int $refund_id - id of refund
	 */
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

	/**
	 * Maybe re-enqueues order when restored from trash
	 *
	 * @param int $id - post id of order
	 */
	public function untrash_post( $id ) {
		if ( ! $id ) {
			return;
		}

		$order_type = OrderUtil::get_order_type( $id );;
		if ( 'shop_order' != $order_type ) {
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

	/**
	 * Deletes order from TaxJar when trashed or deleted
	 *
	 * @param int $post_id - post id of order
	 */
	public function maybe_delete_transaction_from_taxjar( $post_id ) {
		if ( 'shop_order' != OrderUtil::get_order_type( $post_id ) ) {
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

	/**
	 * Deletes refund from TaxJar when trashed or deleted
	 *
	 * @param int $post_id - post id of refund
	 */
	public function maybe_delete_refund_from_taxjar( $post_id ) {
		if ( 'shop_order_refund' != OrderUtil::get_order_type( $post_id ) ) {
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

	/**
	 * Deletes order from TaxJar when cancelled
	 *
	 * @param int $order_id - id of order
	 * @param WC_Order $order - cancelled order
	 */
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
		} else if ( WC_Taxjar_Transaction_Sync::should_validate_order_completed_date() ) {
			if ( $order->get_date_completed() ) {
				if ( $record->should_sync( true ) ) {
					$should_delete = true;
				}
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

	/**
	 * Queries for and enqueues qualifying records in date range
	 *
	 * @param string $start_date - start date of query
	 * @param string $end_date - end date of query
	 * @param boolean $force - determines whether or not to ignore last sync time
	 *
	 * @return int - number of records back filled
	 */
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

			if ( $wpdb->last_error === "Table 'wordpress.wp_taxjar_record_queue' doesn't exist" ) {
				return 'record queue table does not exist';
			}

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

	/**
	 * @param string $start_date - start date of query
	 * @param string $end_date - end date of query
	 * @param bool $force - determines whether or not to ignore last sync time
	 *
	 * @return array - order ids that need back filled
	 */
	public function get_orders_to_backfill( $start_date = null, $end_date = null, $force = false ) {
		global $wpdb;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d H:i:s', strtotime( 'midnight', current_time( 'timestamp' ) ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d H:i:s', strtotime( '+1 day, midnight', current_time( 'timestamp' ) ) );
		}

		$valid_post_statuses = apply_filters( 'taxjar_valid_post_statuses_for_sync', array( 'wc-completed', 'wc-refunded' ) );
		$post_status_string = "( '" . implode( "', '", $valid_post_statuses ) . " ')";

		$should_validate_completed_date = WC_Taxjar_Transaction_Sync::should_validate_order_completed_date();

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			if ( $force ) {
				$query = "SELECT o.id FROM {$wpdb->wc_orders} AS o ";

				if ( $should_validate_completed_date ) {
					$query .= "INNER JOIN {$wpdb->wc_orders_meta} AS order_meta_completed_date ON ( o.id = order_meta_completed_date.order_id ) AND ( order_meta_completed_date.meta_key = '_completed_date' ) ";
				}

				$query .= "WHERE o.type = 'shop_order' AND o.status IN {$post_status_string} AND o.date_created_gmt >= '{$start_date}' AND o.date_created_gmt < '{$end_date}' ";

				if ( $should_validate_completed_date ) {
					$query .= "AND order_meta_completed_date.meta_value IS NOT NULL AND order_meta_completed_date.meta_value != '' ";
				}

				$query .= "ORDER BY o.date_created_gmt ASC";
			} else {
				$query = "SELECT o.id FROM {$wpdb->wc_orders} AS o ";

				if ( $should_validate_completed_date ) {
					$query .= "INNER JOIN {$wpdb->wc_orders_meta} AS order_meta_completed_date ON ( o.id = order_meta_completed_date.order_id ) AND ( order_meta_completed_date.meta_key = '_completed_date' ) ";
				}

				$query .= "LEFT JOIN {$wpdb->wc_order_meta} AS order_meta_last_sync ON ( o.id = order_meta_last_sync.order_id ) AND ( order_meta_last_sync.meta_key = '_taxjar_last_sync' ) ";
				$query .= "WHERE o.type = 'shop_order' AND o.status IN {$post_status_string} AND o.date_created_gmt >= '{$start_date}' AND o.date_created_gmt < '{$end_date}' ";

				if ( $should_validate_completed_date ) {
					$query .= "AND order_meta_completed_date.meta_value IS NOT NULL AND order_meta_completed_date.meta_value != '' ";
				}

				$query .= "AND ((order_meta_last_sync.meta_value IS NULL) OR (o.date_updated_gmt > order_meta_last_sync.meta_value)) ORDER BY o.date_created_gmt ASC";
			}
		} else {
			// Traditional CPT-based orders are in use.
			if ( $force ) {
				$query = "SELECT p.id FROM {$wpdb->posts} AS p ";

				if ( $should_validate_completed_date ) {
					$query .= "INNER JOIN {$wpdb->postmeta} AS order_meta_completed_date ON ( p.id = order_meta_completed_date.post_id ) AND ( order_meta_completed_date.meta_key = '_completed_date' ) ";
				}

				$query .= "WHERE p.post_type = 'shop_order' AND p.post_status IN {$post_status_string} AND p.post_date >= '{$start_date}' AND p.post_date < '{$end_date}' ";

				if ( $should_validate_completed_date ) {
					$query .= "AND order_meta_completed_date.meta_value IS NOT NULL AND order_meta_completed_date.meta_value != '' ";
				}

				$query .= "ORDER BY p.post_date ASC";
			} else {
				$query = "SELECT p.id FROM {$wpdb->posts} AS p ";

				if ( $should_validate_completed_date ) {
					$query .= "INNER JOIN {$wpdb->postmeta} AS order_meta_completed_date ON ( p.id = order_meta_completed_date.post_id ) AND ( order_meta_completed_date.meta_key = '_completed_date' ) ";
				}

				$query .= "LEFT JOIN {$wpdb->postmeta} AS order_meta_last_sync ON ( p.id = order_meta_last_sync.post_id ) AND ( order_meta_last_sync.meta_key = '_taxjar_last_sync' ) ";
				$query .= "WHERE p.post_type = 'shop_order' AND p.post_status IN {$post_status_string} AND p.post_date >= '{$start_date}' AND p.post_date < '{$end_date}' ";

				if ( $should_validate_completed_date ) {
					$query .= "AND order_meta_completed_date.meta_value IS NOT NULL AND order_meta_completed_date.meta_value != '' ";
				}

				$query .= "AND ((order_meta_last_sync.meta_value IS NULL) OR (p.post_modified_gmt > order_meta_last_sync.meta_value)) ORDER BY p.post_date ASC";
			}
		}

		$posts = $wpdb->get_results( $query, ARRAY_N );

		if ( empty( $posts ) ) {
			return array();
		}

		return call_user_func_array( 'array_merge', $posts );
	}

	/**
	 * @param array $order_ids - order ids that may contain refunds to back fill
	 *
	 * @return array - ids of refunds to back fill
	 */
	public function get_refunds_to_backfill( $order_ids ) {
		if ( empty( $order_ids ) ) {
			return array();
		}

		global $wpdb;
		$order_ids_string = implode( ',', $order_ids );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// HPOS usage is enabled.
			$posts = $wpdb->get_results(
				"
			SELECT o.id
			FROM {$wpdb->wc_orders} AS o
			WHERE o.type = 'shop_order_refund'
			AND o.status = 'wc-completed'
			AND o.parent IN ( {$order_ids_string} )
			ORDER BY o.date_created_gmt ASC
			", ARRAY_N
			);
		} else {
			// Traditional CPT-based orders are in use.
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
		}

		if ( empty( $posts ) ) {
			return array();
		}

		return call_user_func_array( 'array_merge', $posts );
	}

	/**
	 * Unschedules all queue actions
	 */
	public static function unschedule_actions() {
		$timestamp = wp_next_scheduled( self::PROCESS_QUEUE_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::PROCESS_QUEUE_HOOK );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::PROCESS_QUEUE_HOOK );
			as_unschedule_all_actions( self::PROCESS_BATCH_HOOK );
		}
	}

	/**
	 * Displays notice regarding tax settings on edit product page in admin
	 */
	public function display_notice_after_product_options_tax() {
		$notice = '<p style="font-style: italic;">';
		$notice .= __( 'Note: Setting a product as none taxable or having the "Zero rate" tax class will cause no tax to be calculated on the item during checkout. However these settings are not supported in the TaxJar app and will cause discrepancies between expected and collected tax. In order to properly exempt products please use product exemption codes as explained in ', 'wc-taxjar' );
		$notice .= '<a target="_blank" href="https://support.taxjar.com/article/309-overriding-tax-rates-and-exempting-products-in-woocommerce">';
		$notice .= __( 'this article', 'wc-taxjar' );
		$notice .= '</a>.</p>';
		echo $notice;
	}

	/**
	 * Checks whether or not completed date should be validated before syncing order or refund to TaxJar
	 *
	 * @return bool
	 */
	public static function should_validate_order_completed_date() {
		$default_value = true;
		return apply_filters( 'taxjar_should_validate_order_completed_date', $default_value );
	}
}
