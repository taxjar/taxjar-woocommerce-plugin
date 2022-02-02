<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class TaxJar_Record {

	const QUEUE_NAME = 'taxjar_record_queue';
	const API_URI = 'https://api.taxjar.com/v2/';

	protected $queue_id;
	protected $record_id;
	protected $status;
	protected $batch_id;
	protected $created_datetime;
	protected $processed_datetime;
	protected $retry_count;
	protected $last_error;
	protected $force_push;

	public $error = array();
	public $last_request;

	public $uri;
	public $object;
	public $taxjar_integration;
	public $data;

	public function __construct( $record_id = null, $set_defaults = false ) {
		$this->taxjar_integration = TaxJar();

		if ( ! empty ( $record_id ) ) {
			$this->set_record_id( $record_id );
		}

		if ( $set_defaults ) {
			$this->set_defaults();
		}
	}

	public function load_object() {
		//$this->data = $this->get_data_from_object();
	}

	public function read() {
		global $wpdb;

		$queue_id = $this->get_queue_id();
		if ( empty( $queue_id ) ) {
			return false;
		}

		$table_name = self::get_queue_table_name();
		$query = "SELECT * FROM {$table_name} WHERE queue_id = {$queue_id}";
		$results = $wpdb->get_results( $query,  ARRAY_A );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return false;
		}

		$record_data = end( $results );
		if ( empty( $record_data ) ) {
			return false;
		}

		$this->set_record_id( $record_data[ 'record_id' ] );
		$this->set_batch_id( $record_data[ 'batch_id' ] );
		$this->set_created_datetime( $record_data[ 'created_datetime' ] );
		$this->set_processed_datetime( $record_data[ 'processed_datetime' ] );
		$this->set_retry_count( $record_data[ 'retry_count' ] );
		$this->set_status( $record_data[ 'status' ] );
		$this->set_force_push( $record_data[ 'force_push' ] );
		$this->set_last_error( $record_data[ 'last_error' ] );
	}

	public function create() {
		if ( empty( $this->get_record_id() ) ) {
			return false;
		}

		global $wpdb;
		$insert = array(
			'record_id'          => $this->get_record_id(),
			'record_type'        => static::get_record_type(),
			'status'             => $this->get_status(),
			'batch_id'           => $this->get_batch_id(),
			'created_datetime'   => $this->get_created_datetime(),
			'force_push'         => $this->get_force_push()
		);

		if ( ! empty( $this->get_processed_datetime() ) ) {
			$insert[ 'processed_datetime' ] = $this->get_processed_datetime();
		}

		if ( $this->get_last_error() === "" || ! empty( $this->get_last_error() ) ) {
			$insert[ 'last_error' ] = $this->get_last_error();
		}

		$result = $wpdb->insert( self::get_queue_table_name(), $insert );
		$this->set_queue_id( $wpdb->insert_id );
		return $result;
	}

	public function delete() {
		if ( empty( $this->get_queue_id() ) ) {
			return false;
		}

		global $wpdb;
		$table_name = self::get_queue_table_name();
		return $wpdb->delete( $table_name, array( 'queue_id' => $this->get_queue_id() ) );
	}

	public function save() {
		if ( empty( $this->get_queue_id() ) ) {
			return $this->create();
		}

		global $wpdb;
		$table_name = self::get_queue_table_name();

		$data = array(
			'record_id' => $this->get_record_id(),
			'status' => $this->get_status(),
			'record_type' => static::get_record_type(),
			'force_push' => $this->get_force_push()
		);

		if ( ! empty( $this->get_processed_datetime() ) ) {
			$data[ 'processed_datetime' ] =  $this->get_processed_datetime();
		}

		if ( $this->get_batch_id() === 0 || $this->get_batch_id() === '0' || ! empty( $this->get_batch_id() ) ) {
			$data[ 'batch_id' ] =  $this->get_batch_id();
		}

		if ( ! empty( $this->get_retry_count() ) ) {
			$data[ 'retry_count' ] =  $this->get_retry_count();
		}

		if ( $this->get_last_error() === "" || ! empty( $this->get_last_error() ) ) {
			$data[ 'last_error' ] =  $this->get_last_error();
		}

		$where = array(
			'queue_id' => $this->get_queue_id()
		);

		$result = $wpdb->update( $table_name, $data, $where );
		return $result;
	}

	public function set_defaults() {
		$this->set_status( 'new' );
		$this->set_batch_id( 0 );
		$this->set_created_datetime( gmdate( 'Y-m-d H:i:s' ) );
		$this->set_force_push( 0 );
	}

	abstract function get_data_from_object();

	public function get_data() {
		if ( empty( $this->data ) ) {
			$this->data = $this->get_data_from_object();
		}

		return $this->data;
	}

	public function sync() {
		try {
			$this->clear_error();
			$this->log( 'Attempting to sync ' . static::get_record_type() . ' # ' . $this->get_record_id() . ' (Queue # ' . $this->get_queue_id() . ')' );
			if ( ! apply_filters( 'taxjar_should_sync_' . static::get_record_type(), $this->should_sync() ) ) {
				if ( $this->get_error() ) {
					$this->sync_failure( $this->get_error()[ 'message' ] );
				} else {
					$this->sync_failure( "" );
				}

				return false;
			}

			$error_responses = array( 400, 401, 403, 404, 405, 406, 410, 429, 500, 503 );
			$success_responses = array( 200, 201 );

			if ( $this->get_status() == 'new' ) {
				$response = $this->create_in_taxjar();
				if ( is_wp_error( $response ) ) {
					$this->sync_failure( __( 'WP_Error occurred on create request - ' , 'wc-taxjar' ) . $response->get_error_message() );
					return false;
				}
				if ( isset( $response['response']['code'] ) && $response['response']['code'] == 422 ) {
					$this->log( 'Record already exists in TaxJar so could not create, attempting to update instead.' );
					$last_request = 'update';
					$response = $this->update_in_taxjar();
				} else {
					$last_request = 'create';
				}
			} else {
				$response = $this->update_in_taxjar();
				if ( is_wp_error( $response ) ) {
					$this->sync_failure( __( 'WP_Error occurred on update request - ' , 'wc-taxjar' ) . $response->get_error_message() );
					return false;
				}
				if ( isset( $response['response']['code'] ) && $response['response']['code'] == 404 ) {
					$this->log( 'Record does not exist in TaxJar so could not update, attempting to create instead.' );
					$last_request = 'create';
					$response = $this->create_in_taxjar();
				} else {
					$last_request = 'update';
				}
			}

			if ( is_wp_error( $response ) ) {
				$this->sync_failure( __( 'WP_Error occurred on ' . $last_request . ' request. Details: ' , 'wc-taxjar' ) . $response->get_error_message() );
				return false;
			}

			if ( ! isset( $response[ 'response' ][ 'code' ] ) ) {
				$this->sync_failure( __( 'Unknown error occurred in sync.' , 'wc-taxjar' ) );
				return false;
			}

			if ( in_array( $response[ 'response' ][ 'code' ], $error_responses ) ) {
				switch( $response[ 'response' ][ 'code' ] ) {
					case 400:
						if ( ! empty( $response['body'] ) ) {
							$error_message = "Invalid request sent to TaxJar. Details: ";
							$body = json_decode( $response['body'] );

							if ( ! empty( $body->detail ) ) {
								$error_message .= $body->detail;
							}
						}
						break;
					case 401:
						$error_message = "Authorization error. Please check API key.";
						break;
					case 403:
						$error_message = "Authorization error. Please check API key.";
						break;
					case 404:
						$error_message = "Record does not exist in TaxJar, could not update.";
						break;
					case 429:
						$error_message = "Rate limit reached.";
						break;
					default:
						$error_message = "Error in request, TaxJar response code " . $response[ 'response' ][ 'code' ];
				}

				$this->sync_failure( $error_message );
				$this->log( ' Request: ' . $this->get_last_request() . ' Response: ' . $response[ 'body' ] );
				return false;
			}

			if ( in_array( $response[ 'response' ][ 'code' ], $success_responses ) ) {
				$this->sync_success();
				$this->log( __(  ucfirst( $last_request ) . ' request successful ' , 'wc-taxjar' ) . ' Request: ' . $this->get_last_request() . ' Response: ' . $response[ 'body' ] );
				return true;
			}

			$this->sync_failure(  __( 'Unknown error occurred in sync.' , 'wc-taxjar' ) );
			return false;

		} catch ( Exception $e ) {
			$this->sync_failure( __( 'Unexpected error in sync with message: ', 'wc-taxjar' ) . $e->getMessage() );
			return false;
		}
	}

	public function log( $message ) {
		if ( static::get_record_type() == 'customer' ) {
			$this->taxjar_integration->customer_sync->_log( $message );
		} else {
			$this->taxjar_integration->transaction_sync->_log( $message );
		}
	}

	abstract function should_sync();

	public function sync_success() {
		$current_datetime =  gmdate( 'Y-m-d H:i:s' );
		$this->set_processed_datetime( $current_datetime );
		$this->set_last_error( "" );
		$this->set_status( 'completed' );
		$this->save();
	}

	public function update_object_sync_success_meta_data() {
		$data = $this->get_data();
		$data_hash = hash( 'md5', serialize( $data ) );
		$sync_datetime =  $this->get_processed_datetime();
		$this->object->update_meta_data( '_taxjar_last_sync', $sync_datetime );
		$this->object->update_meta_data( '_taxjar_hash', $data_hash );
		$this->object->delete_meta_data( '_taxjar_sync_last_error' );
		$this->object->save();
	}

	public function get_last_sync_time() {
		return $this->object->get_meta( '_taxjar_last_sync', true );
	}

	public function get_object_hash() {
		return $this->object->get_meta( '_taxjar_hash', true );
	}

	public function hash_match() {
		$object_hash = $this->get_object_hash();
		$record_hash = hash( 'md5', serialize( $this->get_data() ) );
		if ( $object_hash === $record_hash ) {
			return true;
		} else {
			return false;
		}
	}

	public function sync_failure( $error_message ) {
		$this->log( $error_message );
		$this->set_last_error( $error_message );

		$retry_count = $this->get_retry_count() + 1;
		$this->set_retry_count( $retry_count );
		if ( $this->get_retry_count() >= 3 ) {
			$this->set_status( 'failed' );
		} else {
			$this->set_batch_id( 0 );
		}

		$this->save();
	}

	public function update_object_sync_failure_meta_data( $error_message ) {
		if ( $this->object ) {
			$this->object->update_meta_data( '_taxjar_sync_last_error', $error_message );
			$this->object->save();
		}
	}

	abstract function create_in_taxjar();
	abstract function update_in_taxjar();
	abstract function delete_in_taxjar();
	abstract function get_from_taxjar();

	public function get_provider() {
		return apply_filters( 'taxjar_get_' . static::get_record_type() . '_provider', 'woo', $this->object, $this );
	}

	/**
	 * Find record in queue
	 *
	 * @param int $record_id - record id of item to search queue for
	 * @return TaxJar_Record|bool - if successful returns a TaxJar_Record object, otherwise returns false
	 */
	static function find_active_in_queue( $record_id ) {
		global $wpdb;

		$table_name = self::get_queue_table_name();
		$record_type = static::get_record_type();
		$query = "SELECT queue_id FROM {$table_name} WHERE record_id = {$record_id} AND record_type = '{$record_type}' AND status IN ( 'new', 'awaiting' )";
		$results = $wpdb->get_results( $query,  ARRAY_A );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return false;
		}

		$last_element = end( $results );
		if ( empty( $last_element[ 'queue_id' ] ) ) {
			return false;
		}

		$record = new static();
		$record->set_queue_id( (int)$last_element[ 'queue_id' ] );
		$record->read();

		return $record;
	}

	public static function create_from_record_row( $record_row ) {
		if ( $record_row[ 'record_type' ] == 'order' ) {
			$record = new TaxJar_Order_Record( $record_row[ 'record_id'] );
		} elseif ( $record_row[ 'record_type' ] == 'refund' ) {
			$record = new TaxJar_Refund_Record( $record_row[ 'record_id'] );
		} elseif ( $record_row[ 'record_type' ] == 'customer' ) {
			$record = new TaxJar_Customer_Record( $record_row[ 'record_id'] );
		} else {
			// remove record from queue as it's of a type not supported
			$record = new TaxJar_Order_Record( $record_row[ 'record_id' ] );
			$record->set_queue_id( $record_row[ 'queue_id'] );
			$record->delete();
			return false;
		}

		$record->set_queue_id( $record_row[ 'queue_id'] );
		$record->set_retry_count( $record_row[ 'retry_count'] );
		$record->set_status( $record_row[ 'status'] );
		$record->set_created_datetime( $record_row[ 'status'] );
		$record->set_batch_id( $record_row[ 'batch_id' ] );
		$record->set_processed_datetime( $record_row[ 'processed_datetime' ] );
		$record->set_force_push( $record_row[ 'force_push' ] );
		$record->set_last_error( $record_row[ 'last_error' ] );
		$record->load_object();

		// handle records deleted after being added to queue
		if ( ! is_object( $record->object ) ) {
			$record->delete();
			return false;
		}

		return $record;
	}

	/**
	 * Get queue table name
	 *
	 * @return string - name of queue table in db
	 */
	protected static function get_queue_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::QUEUE_NAME;
	}

	/**
	 * @return array - Country codes that will pass validation when syncing records
	 */
	public static function allowed_countries() {
		return apply_filters( 'taxjar_sync_allowed_countries', array ( 'US' ) );
	}

	/**
	 * @return array - Currencies that will pass validation when syncing records
	 */
	public static function allowed_currencies() {
		return apply_filters( 'taxjar_sync_allowed_currencies', array ( 'USD' ) );
	}

	/**
	 * Validates that required fields are present on records prior to syncing
	 * @return bool
	 */
	public function has_valid_ship_from_address() {
		$order_data = $this->get_data();

		if ( empty( $order_data[ 'from_country' ] ) || empty( $order_data[ 'from_zip' ] ) || empty( $order_data[ 'from_city' ] ) ) {
			return false;
		}

		if ( in_array( $order_data[ 'from_country' ], array( 'US', 'CA' ) ) ) {
			if ( empty( $order_data['from_state'] ) ) {
				return false;
			}
		}

		return true;
	}

	public function set_queue_id( $queue_id ) {
		$this->queue_id = $queue_id;
	}

	public function get_queue_id() {
		return $this->queue_id;
	}

	public function set_record_id( $record_id ) {
		$this->record_id = $record_id;
	}

	public function get_record_id() {
		return $this->record_id;
	}

	abstract static function get_record_type();

	public function set_status( $status ) {
		$this->status = $status;
	}

	public function get_status() {
		return $this->status;
	}

	public function set_batch_id( $batch_id ) {
		$this->batch_id = $batch_id;
	}

	public function get_batch_id() {
		return $this->batch_id;
	}

	public function set_created_datetime( $datetime ) {
		$this->created_datetime = $datetime;
	}

	public function get_created_datetime() {
		return $this->created_datetime;
	}

	public function set_processed_datetime( $datetime ) {
		$this->processed_datetime = $datetime;
	}

	public function get_processed_datetime() {
		return $this->processed_datetime;
	}

	public function set_retry_count( $retry_count ) {
		$this->retry_count = $retry_count;
	}

	public function get_retry_count() {
		return $this->retry_count;
	}

	public function set_force_push( $force_push = 0 ) {
		if ( $force_push ) {
			$this->force_push = 1;
		} else {
			$this->force_push = 0;
		}
	}

	public function get_force_push() {
		return $this->force_push;
	}

	public function get_error() {
		return $this->error;
	}

	public function clear_error() {
		$this->error = array();
	}

	public function add_error( $message, $data = null ) {
		$this->error = array(
			'message' => $message,
			'data'    => $data
		);
	}

	public function get_last_request() {
		return $this->last_request;
	}

	public function set_last_request( $last_request ) {
		$this->last_request = $last_request;
	}

	public function get_last_error() {
		return $this->last_error;
	}

	public function set_last_error( $last_error ) {
		$this->last_error = $last_error;
	}

	/**
	 * Generates the plugin parameter used to identify requests in the TaxJar API
	 * @return string
	 */
	public function get_plugin_parameter() {
		return 'woo';
	}
}
