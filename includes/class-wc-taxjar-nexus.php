<?php
/**
 * TaxJar Nexus
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Nexus {

	const INVALID_OR_EXPIRED_API_TOKEN = 'Unauthorized';

	public function __construct( $integration ) {
		$this->integration = $integration;
		$this->nexus = $this->get_or_update_cached_nexus();
	}

	public function get_form_settings_field() {
		$desc_text = '';
		//$desc_text .= '<h3>Nexus Information</h3>';

		if ( count( $this->nexus ) > 0 ) {
			$desc_text .= '<p>Sales tax will be calculated on orders delivered into the following regions: </p>';

			foreach ( $this->nexus as $key => $nexus ) {
				$desc_text .= '<br>';
				if ( isset( $nexus->region ) && isset( $nexus->country ) ) {
					$desc_text .= sprintf( "%s, %s", $nexus->region, $nexus->country );
				} else {
					if ( isset( $nexus->country ) ) {
						$desc_text .= $nexus->country;
					}
				}
			}
		} else {
			$desc_text .= "<p>TaxJar needs your business locations in order to calculate sales tax properly. Please add them <a href='" . $this->integration->regions_uri . "' target='_blank'>here</a>.<p>";
		}

		$desc_text .= "<p><br><button class='button js-wc-taxjar-sync-nexus-addresses'>Sync Nexus Addresses</button>&nbsp; or &nbsp;<a href='" . $this->integration->regions_uri . "' target='_blank'>Manage Nexus Locations</a></p>";

		return array(
			'title'             => 'Nexus Information',
			'type'              => 'title',
			'desc'       => $desc_text,
			'description' => $desc_text,
		);
	}

	public function has_nexus_check( $country, $state = null ) {
		$store_settings   = $this->integration->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];

		$nexus_areas = $this->get_or_update_cached_nexus();

		if ( count( $nexus_areas ) == 0 ) {
			return true;
		}

		array_push(
			$nexus_areas,
			(object) array(
				'country_code' => $store_settings['country'],
				'region_code' => $store_settings['state'],
			)
		);

		foreach ( $nexus_areas as $key => $nexus ) {
			if ( isset( $nexus->country_code ) && isset( $nexus->region_code ) && 'US' == $nexus->country_code ) {
				if ( $country == $nexus->country_code && $state == $nexus->region_code ) {
					return true;
				}
			} elseif ( isset( $nexus->country_code ) ) {
				if ( $country == $nexus->country_code ) {
					return true;
				}

				if ( 'GB' == $country && 'UK' == $nexus->country_code ) {
					return true;
				}

				if ( 'GR' == $country && 'EL' == $nexus->country_code ) {
					return true;
				}
			}
		}

		return false;
	}

	public function get_or_update_cached_nexus( $force_update = false ) {
		$nexus_list = $this->get_nexus_from_cache();

		if ( ! $force_update && self::INVALID_OR_EXPIRED_API_TOKEN == $nexus_list ) {
			return array();
		}

		if ( $force_update || false === $nexus_list || null === $nexus_list || ( is_array( $nexus_list ) && count( $nexus_list ) == 0 ) ) {
			delete_transient( 'tj_nexus' );
			$nexus_list = $this->get_nexus_from_cache();
		}

		return $nexus_list;
	}

	public function get_nexus_from_api() {
		$url = $this->integration->uri . 'nexus/regions';

		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Authorization' => 'Token token="' . $this->integration->post_or_setting( 'api_token' ) . '"',
				'Content-Type' => 'application/json',
			),
			'user-agent' => $this->integration->ua,
		) );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$this->integration->_log( ':::: Nexus addresses updated ::::' );
			$body = json_decode( $response['body'] );
			$this->clear_non_nexus_rates( $body->regions );
			return $body->regions;
		}

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 400 ) {
			return self::INVALID_OR_EXPIRED_API_TOKEN;
		}

		return array();
	}

	public function get_nexus_from_cache() {
		$cache_key = 'tj_nexus';
		$response  = get_transient( $cache_key );

		if ( false === $response ) {
			$response = $this->get_nexus_from_api();
			set_transient( $cache_key, $response, 0.5 * DAY_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Clear non US nexus states from rates table to prevent tax calculation when nexus is removed in a state
	 * @param $regions - array of nexus regions from API request
	 */
	public function clear_non_nexus_rates( $regions ) {
		global $wpdb;
		$nexus_states = array();

		foreach( $regions as $region ) {
			if ( ! empty( $region->country_code ) && $region->country_code === 'US' ) {
				if ( ! empty( $region->region_code ) ) {
					$nexus_states[] = $region->region_code;
				}
			}
		}

		$nexus_states_string = join( "','", $nexus_states );
		$query = "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'US' AND tax_rate_state NOT IN ('{$nexus_states_string}')";
		$results = $wpdb->query( $query );
	}

} // End WC_Taxjar_Nexus.
