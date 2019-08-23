<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Customer_Record extends TaxJar_Record {

	/**
	 * @param WC_Customer $object - allows loading of object without additional queries if available
	 */
	function load_object( $object = null ) {
		if ( $object && is_a( $object, 'WC_Customer' ) ) {
			$this->object = $object;
		} else {
			try {
				$customer =  new WC_Customer( $this->get_record_id() );
				if ( $customer instanceof WC_Customer ) {
					$this->object = $customer;
				} else {
					return;
				}
			} catch ( Exception $e ) {
				return;
			}
		}

		parent::load_object();
	}

	/**
	 * @return string - customer record type
	 */
	public function get_record_type() {
		return 'customer';
	}

	/**
	 * Validate if customer should be synced to TaxJar
	 *
	 * @param bool $ignore_status - if set ignores last sync time
	 *
	 * @return bool
	 */
	public function should_sync( $ignore_status = false ) {
		if ( ! isset( $this->object ) ) {
			$this->add_error( __( 'Customer failed validation - customer object not loaded to record before syncing.', 'wc-taxjar' ) );
			return false;
		}

		$data = $this->get_data();
		if ( empty( $data[ 'customer_id' ] ) ) {
			$this->add_error( __( 'Customer failed validation, customer missing required field: customer_id.', 'wc-taxjar' ) );
			return false;
		}

		if ( empty( $data[ 'exemption_type' ] ) ) {
			$this->add_error( __( 'Customer failed validation, customer missing required field: exemption_type.', 'wc-taxjar' ) );
			return false;
		}

		if ( empty( $data[ 'name' ] ) ) {
			$this->add_error( __( 'Customer failed validation, customer missing required field: name.', 'wc-taxjar' ) );
			return false;
		}

		if ( ! $this->get_force_push() ) {
			if ( hash( 'md5', serialize( $this->get_data() ) ) === $this->get_object_hash() ) {
				$this->add_error( __( 'Customer failed validation, customer data not different than previous sync.', 'wc-taxjar' ) );
				return false;
			}
		}

		return true;
	}

	/**
	 * Updates record in queue upon success
	 */
	public function sync_success() {
		parent::sync_success();
		$this->add_object_sync_metadata();
	}

	/**
	 * Create API request
	 * @return array|WP_Error - API response or WP_Error if request fails
	 */
	public function create_in_taxjar() {
		$data = $this->get_data();
		$url = self::API_URI . 'customers';
		$body = wp_json_encode( $data );

		$response = wp_remote_post( $url, array(
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
			'body' => $body,
		) );

		$this->set_last_request( $body );
		return $response;
	}

	/**
	 * Update customer API request
	 * @return array|WP_Error - API response or WP_Error if request fails
	 */
	public function update_in_taxjar(){
		$customer_id = $this->get_customer_id();
		$data = $this->get_data();

		$url = self::API_URI . 'customers/' . $customer_id;
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

		$this->set_last_request( $body );
		return $response;
	}

	/**
	 * Delete customer API request
	 * @return array|WP_Error - API response or WP_Error if request fails
	 */
	public function delete_in_taxjar(){
		$customer_id = $this->get_customer_id();
		$url = self::API_URI . 'customers/' . $customer_id;
		$data = array(
			'customer_id' => $customer_id,
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

		$this->set_last_request( $body );
		return $response;
	}

	/**
	 * Get customer API request
	 * @return array|WP_Error - API response or WP_Error if request fails
	 */
	public function get_from_taxjar() {
		$customer_id = $this->get_customer_id();
		$url = self::API_URI . 'customers/' . $customer_id;

		$response = wp_remote_request( $url, array(
			'method' => 'GET',
			'headers' => array(
				'Authorization' => 'Token token="' . $this->taxjar_integration->settings['api_token'] . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->taxjar_integration->ua,
		) );

		$this->set_last_request( $customer_id );
		return $response;
	}

	/**
	 * Get customer data from object
	 * @return array
	 */
	public function get_data_from_object() {
		$customer_data = array();

		$customer_data[ 'customer_id' ] = $this->get_customer_id();
		$customer_data[ 'exemption_type' ] = $this->get_exemption_type();

		$first_name = $this->object->get_shipping_first_name();
		$last_name = $this->object->get_shipping_last_name();

		if ( empty( $first_name ) ) {
			$first_name = $this->object->get_billing_first_name();
			if ( empty( $first_name ) ) {
				$first_name = $this->object->get_first_name();
			}
		}

		if ( empty( $last_name ) ) {
			$last_name = $this->object->get_billing_last_name();
			if ( empty( $last_name ) ) {
				$last_name = $this->object->get_last_name();
			}
		}

		if ( empty( $first_name ) && empty( $last_name ) ) {
			$name = '';
		} else if ( empty( $first_name ) ) {
			$name = $last_name;
		} else if ( empty( $last_name ) ) {
			$name = $first_name;
		} else {
			$name = $first_name . ' ' . $last_name;
		}
		$customer_data[ 'name' ] = $name;

		$customer_data[ 'exempt_regions' ] = $this->get_exempt_regions();

		$country = $this->object->get_shipping_country();
		if ( empty( $country ) ) {
			$country = $this->object->get_billing_country();
		}
		if ( ! empty( $country ) ) {
			$customer_data[ 'country' ] = $country;
		}

		$state = $this->object->get_shipping_state();
		if ( empty( $state ) ) {
			$state = $this->object->get_billing_state();
		}
		if ( ! empty( $state ) ) {
			$customer_data[ 'state' ] = $state;
		}

		$postcode = $this->object->get_shipping_postcode();
		if ( empty( $postcode ) ) {
			$postcode = $this->object->get_billing_postcode();
		}
		if ( ! empty( $postcode ) ) {
			$customer_data[ 'zip' ] = $postcode;
		}

		$city = $this->object->get_shipping_city();
		if ( empty( $city ) ) {
			$city = $this->object->get_billing_city();
		}
		if ( ! empty( $city ) ) {
			$customer_data[ 'city' ] = $city;
		}

		$address = $this->object->get_shipping_address();
		if ( empty( $address ) ) {
			$address = $this->object->get_billing_address();
		}
		if ( ! empty( $address ) ) {
			$customer_data[ 'street' ] = $address;
		}

		$customer_data = apply_filters( 'taxjar_customer_sync_data', $customer_data, $this->object );
		$this->data = $customer_data;
		return $customer_data;
	}

	/**
	 * @return int|string
	 */
	public function get_customer_id() {
		return apply_filters( 'taxjar_get_customer_id', $this->get_record_id(), $this->object );
	}

	public function get_exemption_type() {
		$valid_types = array( 'wholesale', 'government', 'other', 'non_exempt' );
		$exemption_type = get_user_meta( $this->object->get_id(), 'tax_exemption_type', true );
		if ( ! in_array( $exemption_type, $valid_types ) ) {
			$exemption_type = 'non_exempt';
		}
		return $exemption_type;
	}

	public function get_exempt_regions() {
		$states = WC_Taxjar_Customer_Sync::get_all_exempt_regions();
		$valid_exempt_regions = array_keys( $states );
		$exempt_meta = get_user_meta( $this->object->get_id(), 'tax_exempt_regions', true );
		$saved_regions = explode( ',', $exempt_meta );
		$intersect = array_intersect( $valid_exempt_regions, $saved_regions );
		$exempt_regions = array();

		if ( ! empty( $intersect ) ) {
			foreach( $intersect as $region ) {
				$exempt_regions[] = array(
					'country' => 'US',
					'state'   => $region
				);
			}
		}

		return $exempt_regions;
	}
}