<?php
/**
 * TaxJar Download Orders
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Download_Orders {

	public function __construct( $integration ) {
		$this->integration      = $integration;
		$this->taxjar_download  = filter_var( $this->integration->get_option( 'taxjar_download' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Validate the option to enable TaxJar order downloads and link or unlink shop
	 * @see validate_settings_fields()
	 */
	public function validate_taxjar_download_field( $key ) {
		$value = $this->integration->get_value_from_post( $key );
		$previous_value = $this->integration->get_option( 'taxjar_download' );

		if ( isset( $value ) && $value ) {
			$value = 'yes';
		} else {
			$value = 'no';
		}
		$value = apply_filters( 'taxjar_download_orders', $value );

		if ( $value != $previous_value ) {
			if ( $value == 'no' ) {
				WC_Taxjar_Record_Queue::clear_active_transaction_records();
			}
		}

		return $value;
	}

	/**
	 * Called by the integration to show on the TaxJar settings page
	 *
	 * @return array
	 */
	public function get_form_settings_field() {
		return array(
			'title'             => __( 'Sales Tax Reporting', 'wc-taxjar' ),
			'type'              => 'checkbox',
			'label'             => __( 'Enable order sync to TaxJar', 'wc-taxjar' ),
			'default'           => 'no',
			'desc'              => __( 'If enabled, will automatically sync your orders to TaxJar for reporting.', 'wc-taxjar' ),
			'id'                => 'woocommerce_taxjar-integration_settings[taxjar_download]'
		);
	}

	/**
	 * Disconnect this store from the user's Taxjar account
	 *
	 * @return boolean
	 */
	public function unlink_provider( $store_url ) {
		$this->disable_taxjar_user();

		$url = $this->integration->uri . 'plugins/woo/deregister';
		$body_string = sprintf( 'store_url=%s', $store_url );

		$request = new TaxJar_API_Request(
			'plugins/woo/deregister',
			$body_string,
			'delete',
			'application/x-www-form-urlencoded'
		);
		$response = $request->send_request();

		if ( is_wp_error( $response ) ) {
			new WP_Error( 'request', __( "There was an error unlinking this store to your TaxJar account. Please contact support@taxjar.com" ) );
			return false;
		} elseif ( 200 == $response['response']['code'] ) {
			$this->integration->_log( 'Successfully unlinked shop to TaxJar account' );
		} else {
			// Log Response Error
			$this->integration->_log( "Received (" . $response['response']['code'] . "): " . $response['body'] );
		}

		return true;
	}

	/**
	 * Search for the row in DB with the TaxJar user
	 *
	 * @return OBJECT
	 */
	private function api_user_query() {
		global $wpdb;
		return $wpdb->get_row( "SELECT * FROM $wpdb->users WHERE user_login LIKE 'api_taxjar_%'" );
	}

	/**
	 * Deletes the TaxJar user and stores keys for 45 days
	 *
	 * @return array|void
	 */
	private function disable_taxjar_user() {
		// If we cannot delete users, do nothing
		if ( current_user_can( 'delete_users' ) ) {
			$user = $this->api_user_query();
			if ( isset( $user ) ) {
				$key = hash( 'md5', $user->ID );
				// If the keys don't exist
				if ( false === ( $cache_value = get_transient( $key ) ) ) {
					$userdata = get_userdata( $user->ID );
					$consumer_key = $userdata->woocommerce_api_consumer_key;
					$consumer_secret = $userdata->woocommerce_api_consumer_secret;
					// Store the keys
					set_transient( $key, $consumer_key . '%' . $consumer_secret, 45 * DAY_IN_SECONDS );
					// Must include user.php to use this method
					wp_delete_user( $user->ID );
				} else {
					// Only delete the user if the keys are stored
					wp_delete_user( $user->ID );
				}
			}
		}
	}

	/**
	 * Creates a new TaxJar user or returns the existing one
	 *
	 * @return WordPress User
	 */
	private function get_or_create_taxjar_user() {
		// Get the User object
		$user = $this->api_user_query();

		if ( ! isset( $user ) && current_user_can( 'create_users' ) ) {
			// Unique Username with TaxJar prefix
			$username = uniqid( 'api_taxjar_', true );

			if ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				// Use OPENSSL_RANDOM_PSEDUO if we can for a password
				try {
					$password = openssl_random_pseudo_bytes( 32 );
				} catch ( Exception $e ) {
					$password = uniqid( '', true );
				}
			} else {
				$password = uniqid( '', true );
			}

			// User is created with role shop manager and strong password
			$user_id  = wp_insert_user( array(
				'user_login' => $username,
				'user_pass' => $password,
				'user_nicename' => 'TaxJar API User',
				'user_url' => 'http://taxjar.com',
				'nickname' => 'TaxJar',
				'description' => 'User account created by TaxJar for downloading orders.',
				'role' => 'shop_manager',
			) );

			return get_user_by( 'id', $user_id );

			if ( is_wp_error( $user_id ) ) {
				new WP_Error( 'general_failure', __( 'There was an error creating the new user TaxJar uses to access your store. Please check your server configuration or try to create your keys <a href="profile.php">here</a>.' ) );
			}
		} else {
			new WP_Error( 'permission', __( 'Sorry, it looks like you cannot create users. You must be able to create users to use this feature.' ) );
			return false;
		}

		return $user;
	}

	/**
	 * Check if there is an existing WooCommerce 2.4 API Key
	 *
	 * @return boolean
	 */
	private function existing_api_key() {
		global $wpdb;
		$sql = "SELECT count(key_id)
			FROM {$wpdb->prefix}woocommerce_api_keys
			LEFT JOIN $wpdb->users
			ON {$wpdb->prefix}woocommerce_api_keys.user_id={$wpdb->users}.ID
			WHERE ({$wpdb->users}.user_login LIKE '%taxjar%' OR {$wpdb->prefix}woocommerce_api_keys.description LIKE '%taxjar%');";
		return ( $wpdb->get_var( $sql ) > 0 );
	}

	/**
	 * Generates v1 WooCommerce API keys just as implemented in WC 2.1
	 *
	 * @param int
	 * @return void
	 */
	private function get_or_generate_v1_api_keys( $user_id ) {
		// Get userdata and hash for our transient
		$user = get_userdata( $user_id );
		$key = hash( 'md5', $this->integration->id );

		// Check for existing < 45 day old keys
		if ( false === ( $cache_value = get_transient( $key ) ) ) {
			// Generate them if they don't exist
			$consumer_key = 'ck_' . hash( 'md5', $user->user_login . date( 'U' ) . mt_rand() );
			$consumer_secret = 'cs_' . hash( 'md5', $user->ID . date( 'U' ) . mt_rand() );
		} else {
			// Read them from the transient if they do exist
			$cache_value = explode( '%', $cache_value );
			$consumer_key = $cache_value[0];
			$consumer_secret = $cache_value[1];
			// Delete the transient since it served its purpose
			delete_transient( $key );
		}

		// If the user does not have keys, add them
		if ( (empty( $user->woocommerce_api_consumer_key ) ) && (empty( $user->woocommerce_api_consumer_secret ) ) ) {
			$permissions = 'read';
			update_user_meta( $user_id, 'woocommerce_api_consumer_key', $consumer_key );
			update_user_meta( $user_id, 'woocommerce_api_consumer_secret', $consumer_secret );
			update_user_meta( $user_id, 'woocommerce_api_key_permissions', $permissions );
		} else {
			// Set the keys if the user already has them
			$consumer_key = $user->woocommerce_api_consumer_key;
			$consumer_secret = $user->woocommerce_api_consumer_secret;
		}

		return array( 'consumer_key' => $consumer_key, 'consumer_secret' => $consumer_secret );
	}

	/**
	 * Direct copy of how API keys are generated via AJAX in WooCommerce
	 *
	 * @return boolean
	 */
	private function generate_v2_api_keys( $user_id ) {
		global $wpdb;

		$consumer_key    = 'ck_' . wc_rand_hash();
		$consumer_secret = 'cs_' . wc_rand_hash();

		$data = array(
			'user_id'         => $user_id,
			'description'     => 'TaxJar',
			'permissions'     => 'read',
			'consumer_key'    => wc_api_hash( $consumer_key ),
			'consumer_secret' => $consumer_secret,
			'truncated_key'   => substr( $consumer_key, -7 ),
		);

		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_api_keys',
			$data,
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		$key_id = $wpdb->insert_id;

		return array(
			'consumer_key' => $consumer_key,
			'consumer_secret' => $consumer_secret,
		);
	}

	/**
	 * Compares WooCommerce version and returns the appropriate API key
	 *
	 * @return array
	 */
	private function get_or_create_woocommerce_api_keys() {
		if ( version_compare( WC()->version, '2.4.0', '>=' ) ) {
			global $current_user;
			wp_get_current_user();

			$this->delete_wc_taxjar_keys();
			return $this->generate_v2_api_keys( $current_user->ID );
		} else {
			$user = $this->get_or_create_taxjar_user();

			if ( ! isset( $user ) ) {
				return false;
			}

			return $this->get_or_generate_v1_api_keys( $user->ID );
		}
	}

	/**
	 * Deletes any existing TaxJar WooCommerce API keys
	 *
	 * @return void
	 */
	public function delete_wc_taxjar_keys() {
		global $wpdb;

		$key_ids = $wpdb->get_results("SELECT key_id
			FROM {$wpdb->prefix}woocommerce_api_keys
			LEFT JOIN $wpdb->users
			ON {$wpdb->prefix}woocommerce_api_keys.user_id={$wpdb->users}.ID
			WHERE ({$wpdb->users}.user_login LIKE '%taxjar%' OR {$wpdb->prefix}woocommerce_api_keys.description LIKE '%taxjar%');");

		foreach ( $key_ids as $row ) {
			$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $row->key_id ), array( '%d' ) );
		}
	}

} // End WC_Taxjar_Download_Orders.
