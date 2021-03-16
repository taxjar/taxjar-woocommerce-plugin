<?php
/**
 * TaxJar Connection
 *
 * @package  TaxJar/Classes
 * @author   TaxJar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WC_Taxjar_Connection
 */
class WC_Taxjar_Connection {

	/**
	 * Whether or not the API token is valid.
	 *
	 * @var bool $api_token_valid
	 */
	private $api_token_valid;

	/**
	 * Whether or not the plugin can connect to the TaxJar API.
	 *
	 * @var bool $can_connect
	 */
	private $can_connect;

	/**
	 * Setting field description to indicate error or successful connection to TaxJar.
	 *
	 * @var string $html_description
	 */
	private $html_description;

	/**
	 * WC_Taxjar_Connection constructor.
	 */
	public function __construct() {
		$this->check_status();
	}

	/**
	 * Returns whether or not the API token is valid
	 *
	 * @return bool
	 */
	public function is_api_token_valid() {
		return apply_filters( 'taxjar_api_token_valid', $this->api_token_valid );
	}

	/**
	 * Returns whether or not the integration can connect to the TaxJar API
	 *
	 * @return bool
	 */
	public function can_connect_to_api() {
		return apply_filters( 'taxjar_can_connect', $this->can_connect );
	}

	/**
	 * Gets the HTML description to display on the settings page
	 * Different descriptions are display according to whether or not the API token is valid, etc.
	 *
	 * @return string
	 */
	public function get_html_description() {
		return $this->html_description;
	}

	/**
	 * Checks the status of the connection to the TaxJar API
	 *
	 * @return array
	 */
	public function check_status() {
		if ( ! apply_filters( 'taxjar_should_check_status', true ) ) {
			$this->api_token_valid  = apply_filters( 'taxjar_api_token_valid', false );
			$this->can_connect      = apply_filters( 'taxjar_can_connect', false );
			$this->html_description = '';
			return;
		}

		$response = $this->send_verify_request();
		$this->validate_verify_response( $response );
	}

	/**
	 * Sends a request to the TaxJar API to verify the API Token
	 *
	 * @return array|WP_Error
	 */
	public function send_verify_request() {
		$request_body = 'token=' . TaxJar_Settings::post_or_setting( 'api_token' );
		$request      = new TaxJar_API_Request(
			'verify',
			$request_body,
			'post',
			'application/x-www-form-urlencoded'
		);
		return $request->send_request();
	}

	/**
	 * Validates the verify response
	 *
	 * @param array|WP_Error $response - response from validate request.
	 */
	public function validate_verify_response( $response ) {
		$this->api_token_valid  = true;
		$this->can_connect      = true;
		$this->html_description = '';

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$body = json_decode( $response['body'] );

			if ( isset( $body->enabled ) && false === $body->enabled ) {
				$this->api_token_valid  = false;
				$this->html_description = $this->get_taxjar_account_error_message();
			}

			if ( isset( $body->valid ) && false === $body->valid ) {
				$this->api_token_valid  = false;
				$this->html_description = $this->get_invalid_api_token_message();
			}

			$this->can_connect = true;
		} else {
			if ( is_wp_error( $response ) ) {
				$this->html_description = $this->get_error_message_html(
					// translators: %s: error message.
					sprintf( __( 'Error: %s', 'wc-taxjar' ), wc_clean( $response->get_error_message() ) )
				);
			} else {
				$this->html_description = $this->get_error_message_html(
					// translators: %s: response status code.
					sprintf( __( 'Status code: %s', 'wc-taxjar' ), wc_clean( $response['response']['code'] ) )
				);
			}

			$this->can_connect = false;
		}
	}

	/**
	 * Gets the TaxJar status settings field
	 *
	 * @return array
	 */
	public function get_form_settings_field() {
		return array(
			'title' => __( 'TaxJar Status', 'wc-taxjar' ),
			'type'  => 'title',
			'desc'  => $this->get_html_description(),
		);
	}

	/**
	 * Gets the message to display when there is an error with the TaxJar account
	 *
	 * @return string
	 */
	public function get_taxjar_account_error_message() {
		$message  = '<div style="color: #ff0000;"><strong>';
		$message .= 'There is an issue with your TaxJar subscription.';
		$message .= sprintf(
			'<br><a href="%s" target="_blank">Please review your account.</a>',
			WC_Taxjar_Integration::$app_uri . 'account/plan'
		);
		$message .= '</strong></div>';
		return $message;
	}

	/**
	 * Gets the message to display when API token in invalid
	 *
	 * @return string
	 */
	public function get_invalid_api_token_message() {
		$message  = '<span style="color: #ff0000;"><strong>';
		$message .= 'It looks like your API token is invalid.';
		$message .= '<br><span style="color: black;">Please attempt to reconnect to TaxJar or </span>';
		$message .= sprintf(
			'<a href="%s" target="_blank">review your API token.</a>',
			WC_Taxjar_Integration::$app_uri . 'account#api-access'
		);
		$message .= '</strong></span>';
		return $message;
	}

	/**
	 * Gets the HTML message when verify request fails.
	 *
	 * @param string $error_details - details of error that occurred.
	 *
	 * @return string
	 */
	public function get_error_message_html( $error_details ) {
		$message  = '<span style="color: #ff0000;">';
		$message .= __(
			'wp_remote_post() failed. TaxJar could not connect to server. Please contact your hosting provider.',
			'wc-taxjar'
		);
		$message .= '<br> ';
		$message .= $error_details;
		$message .= '</span>';
		return $message;
	}

}
