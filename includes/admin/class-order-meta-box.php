<?php
/**
 * Order Meta Box
 *
 * Outputs the contents of the meta box containing TaxJar calculation and sync metadata.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Order_Meta_Box
 */
class Order_Meta_Box {

	/**
	 * Output meta box contents.
	 *
	 * @param mixed $post WP Post.
	 */
	public static function output( $post ) {
		$order_id = $post->ID;
		$order    = wc_get_order( $order_id );
		$metadata = self::get_taxjar_order_metadata( $order );
		wp_enqueue_script( 'accordion' );

		include_once dirname( __FILE__ ) . '/views/html-order-meta-box.php';
	}

	/**
	 * Get the metadata to display in the meta box.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	private static function get_taxjar_order_metadata( \WC_Order $order ): array {
		$metadata                = array();
		$raw_calculation_result  = $order->get_meta( '_taxjar_tax_result' );
		$metadata['sync_status'] = self::get_last_sync_status( $order );

		if ( ! empty( $raw_calculation_result ) ) {
			$result                                     = Tax_Calculation_Result::from_json_string( $raw_calculation_result );
			$metadata['calculation_status']             = self::get_calculation_status( $result );
			$metadata['calculation_status_description'] = self::get_calculation_status_description( $result );
			$metadata['request_json']                   = self::get_request_json( $result );
			$metadata['response_json']                  = self::get_response_json( $result );
		} else {
			$metadata['calculation_status']             = 'unknown';
			$metadata['calculation_status_description'] = 'No TaxJar calculation data is present on the order. This may indicate that TaxJar was not enabled when this order was placed, that tax calculation has not yet occurred (if creating the order manually through admin) or that the tax was calculated prior to TaxJar version 4.1.0 which introduced this status feature.';
			$metadata['request_json']                   = '';
			$metadata['response_json']                  = '';
		}

		return $metadata;
	}

	/**
	 * Get the calculation status of the result.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation result.
	 *
	 * @return string
	 */
	private static function get_calculation_status( Tax_Calculation_Result $result ): string {
		if ( $result->get_success() ) {
			return 'success';
		} else {
			return 'fail';
		}
	}

	/**
	 * Get the calculation status description.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation result.
	 *
	 * @return string
	 */
	private static function get_calculation_status_description( Tax_Calculation_Result $result ): string {
		if ( $result->get_success() ) {
			return 'Tax was calculated in realtime through the TaxJar API.';
		} else {
			return 'TaxJar did not or was unable to perform a tax calculation on this order.<br>Reason: ' . $result->get_error_message();
		}
	}

	/**
	 * Get the calculation request JSON string.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation result.
	 *
	 * @return false|string
	 */
	private static function get_request_json( Tax_Calculation_Result $result ) {
		$request_json = '';

		if ( ! empty( $result->get_raw_request() ) ) {
			$request_json = wp_json_encode( json_decode( $result->get_raw_request() ), JSON_PRETTY_PRINT );
		}

		return $request_json;
	}

	/**
	 * Get the calculation response JSON string.
	 *
	 * @param Tax_Calculation_Result $result Tax calculation result.
	 *
	 * @return false|string
	 */
	private static function get_response_json( Tax_Calculation_Result $result ) {
		$response_json = '';

		if ( ! empty( $result->get_raw_response() ) ) {
			$response = json_decode( $result->get_raw_response() );

			if ( ! empty( $response->body ) ) {
				$response->body = json_decode( $response->body );
			}

			$response_json = wp_json_encode( $response, JSON_PRETTY_PRINT );
		}

		return $response_json;
	}

	/**
	 * Get the sync status of the order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	private static function get_last_sync_status( \WC_Order $order ): string {
		if ( is_a( $order, 'WC_Subscription' ) ) {
			return 'Subscriptions are not synced to TaxJar. Each order created from the subscription must be individually synced.';
		}

		$last_sync_timestamp = $order->get_meta( '_taxjar_last_sync' );
		if ( ! empty( $last_sync_timestamp ) ) {
			$sync_date = wp_date( wc_date_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			$sync_time = wp_date( wc_time_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			return 'Last Synced on: ' . $sync_date . ' ' . $sync_time;
		} else {
			return 'Order has not been synced to TaxJar.';
		}
	}
}
