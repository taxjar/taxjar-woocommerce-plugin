<?php
/**
 * TaxJar Connection
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Taxjar_Connection {

	public function __construct( $integration ) {
		$this->integration = $integration;
		$this->check_status();
	}

	public function get_form_settings_field() {
		return array(
			'title'             => __( 'TaxJar Status', 'wc-taxjar' ),
			'type'              => 'title',
			'desc'              => $this->description,
		);
	}

	private function check_status() {
		if ( ! apply_filters( 'taxjar_should_check_status', true ) ) {
			$this->api_token_valid = apply_filters( 'taxjar_api_token_valid', false );
			$this->can_connect = apply_filters( 'taxjar_can_connect', false );
			return;
		}

		$description = '';
		$url         = $this->integration->uri . 'verify';
		$body_string = 'token=' . $this->integration->post_or_setting( 'api_token' );

		$response = wp_remote_post( $url, array(
			'timeout'     => 60,
			'headers'     => array(
								'Authorization' => 'Token token="' . $this->integration->post_or_setting( 'api_token' ) . '"',
								'Content-Type' => 'application/x-www-form-urlencoded',
							),
			'user-agent'  => $this->integration->ua,
			'body'        => $body_string,
		) );

		$this->api_token_valid = true;

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$body = json_decode( $response['body'] );

			if ( isset( $body->enabled ) && false === $body->enabled ) {
				$description .= '<div style="color: #ff0000;"><strong>';
				$description .= 'There is an issue with your TaxJar subscription.';
				$description .= sprintf( '<br><a href="%s" target="_blank">Please review your account.</a>', $this->integration->app_uri . 'account/plan' );
				$description .= '</strong></div>';
				$this->api_token_valid = false;
			}

			if ( $this->integration->post_or_setting( 'api_token' ) && isset( $body->valid ) && false === $body->valid ) {
				$description .= '<span style="color: #ff0000;"><strong>';
				$description .= 'It looks like your API token is invalid.';
				$description .= sprintf( '<br><a href="%s" target="_blank">Please review your API token.</a>', $this->integration->app_uri . 'account#api-access' );
				$description .= '</strong></span>';
				$this->api_token_valid = false;
			}

			$this->can_connect = true;
		} else {
			$description .= '<span style="color: #ff0000;">';
			$description .= __( 'wp_remote_post() failed. TaxJar could not connect to server. Please contact your hosting provider.', 'wc-taxjar' );
			$description .= '<br>';

			if ( is_wp_error( $response ) ) {
				// translators: Placeholder for error messages
				$description .= ' ' . sprintf( __( 'Error: %s', 'wc-taxjar' ), wc_clean( $response->get_error_message() ) );
			} else {
				// translators: Placeholder for status code type
				$description .= ' ' . sprintf( __( 'Status code: %s', 'wc-taxjar' ), wc_clean( $response['response']['code'] ) );
			}

			$description .= '</span>';
			$this->can_connect = false;
    	} // End if().

		$this->description = $description;
	} // End check_status().

} // End WC_Taxjar_Connection.
