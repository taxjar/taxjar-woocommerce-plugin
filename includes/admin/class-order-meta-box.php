<?php
/**
 * Order Meta Box
 *
 * Outputs the contents of the meta box containing TaxJar calculation and sync metadata.
 *
 * @package TaxJar
 */

namespace TaxJar;

use TaxJar_Order_Record;
use TaxJar_Refund_Record;

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
	 * @param array $additional_data Additional data.
	 */
	public static function output( $post, $additional_data ) {
		$order = $additional_data['args']['order'];

		$metadata = self::get_order_tax_calculation_metadata( $order );
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
	private static function get_order_tax_calculation_metadata( \WC_Order $order ): array {
		$metadata               = array();
		$raw_calculation_result = $order->get_meta( '_taxjar_tax_result' );

		if ( ! empty( $raw_calculation_result ) ) {
			$result                                     = Tax_Calculation_Result::from_json_string( $raw_calculation_result );
			$metadata['calculation_status']             = self::get_calculation_status( $result );
			$metadata['calculation_status_description'] = self::get_calculation_status_description( $result );
		} else {
			$metadata['calculation_status']             = 'unknown';
			$metadata['calculation_status_description'] = 'No TaxJar calculation data is present on the order. This may indicate that TaxJar was not enabled when this order was placed, that tax calculation has not yet occurred (if creating the order manually through admin) or that the tax was calculated prior to TaxJar version 4.1.0 which introduced this status feature.';
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
	 * Get the sync status of the order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string
	 */
	public static function get_order_sync_accordion_content( \WC_Order $order ): string {
		$last_sync_timestamp = $order->get_meta( '_taxjar_last_sync' );
		$last_error          = $order->get_meta( '_taxjar_sync_last_error' );

		if ( empty( $last_sync_timestamp ) ) {
			if ( empty( $last_error ) ) {
				$queue_id = TaxJar_Order_Record::find_active_in_queue( $order->get_id() );

				if ( $queue_id ) {
					return 'Order is currently in the sync queue. ' . self::get_sync_queue_link( $order );
				}

				$record = new TaxJar_Order_Record( $order->get_id(), true );
				$record->load_object( $order );
				$can_sync = $record->should_sync();

				if ( $can_sync ) {
					return 'Order is ready to sync to TaxJar but has not yet been added to the sync queue.';
				} else {
					return 'Order cannot sync to TaxJar. ' . $record->get_error()['message'];
				}
			} else {
				return 'Order failed to sync to TaxJar. ' . $last_error;
			}
		} else {
			$sync_date = wp_date( wc_date_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			$sync_time = wp_date( wc_time_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			return 'Order successfully synced to TaxJar.<br>Last Synced on: ' . $sync_date . ' ' . $sync_time;
		}
	}

	/**
	 * Get accordion content for a refund.
	 *
	 * @param \WC_Order_Refund $refund Refund.
	 *
	 * @return string
	 */
	public static function get_refund_sync_accordion_content( \WC_Order_Refund $refund ): string {
		$last_sync_timestamp = $refund->get_meta( '_taxjar_last_sync' );
		$last_error          = $refund->get_meta( '_taxjar_sync_last_error' );

		if ( empty( $last_sync_timestamp ) ) {
			if ( empty( $last_error ) ) {
				$queue_id = TaxJar_Refund_Record::find_active_in_queue( $refund->get_id() );

				if ( $queue_id ) {
					return 'Refund is currently in the sync queue. ' . self::get_sync_queue_link( $refund );
				}

				$record = new TaxJar_Refund_Record( $refund->get_id(), true );
				$record->load_object();
				$can_sync = $record->should_sync();

				if ( $can_sync ) {
					return 'Refund is ready to sync to TaxJar but has not yet been added to the sync queue.';
				} else {
					return 'Refund cannot sync to TaxJar. ' . $record->get_error()['message'];
				}
			} else {
				return 'Refund failed to sync to TaxJar. ' . $last_error;
			}
		} else {
			$sync_date = wp_date( wc_date_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			$sync_time = wp_date( wc_time_format(), wc_string_to_timestamp( $last_sync_timestamp ) );
			return 'Refund successfully synced to TaxJar.<br>Last Synced on: ' . $sync_date . ' ' . $sync_time;
		}
	}

	/**
	 * Get the link to the sync queue search for a particular order.
	 *
	 * @param \WC_Abstract_Order $order Order or Refund to get sync queue link for.
	 *
	 * @return string
	 */
	private static function get_sync_queue_link( \WC_Abstract_Order $order ): string {
		$link = add_query_arg(
			array(
				'page'    => 'wc-settings',
				'tab'     => 'taxjar-integration',
				'section' => 'sync_queue',
				's'       => $order->get_id(),
				'paged'   => 1,
			),
			admin_url( 'admin.php' )
		);

		return '<a href="' . esc_url( $link ) . '" >View Progress</a>';
	}

	/**
	 * Get the sync status of an order or refund.
	 *
	 * @param \WC_Abstract_Order $order Order or Refund.
	 *
	 * @return string
	 */
	public static function get_sync_status( \WC_Abstract_Order $order ): string {
		$last_sync_timestamp = $order->get_meta( '_taxjar_last_sync' );
		$last_error          = $order->get_meta( '_taxjar_sync_last_error' );

		if ( empty( $last_sync_timestamp ) ) {
			if ( empty( $last_error ) ) {
				return 'not-synced';
			} else {
				return 'failed';
			}
		} else {
			return 'synced';
		}
	}

	/**
	 * Get the text description of an order or refund sync status.
	 *
	 * @param string $status Sync status of order or refund.
	 * @param string $type Type, either order or refund.
	 *
	 * @return string
	 */
	public static function get_sync_status_tip( string $status, string $type ): string {
		$tips = array(
			'order-synced'      => __( 'Order successfully synced to TaxJar.', 'taxjar' ),
			'order-not-synced'  => __( 'Order has not been synced to TaxJar.', 'taxjar' ),
			'order-failed'      => __( 'Order failed to sync to TaxJar.', 'taxjar' ),
			'refund-synced'     => __( 'Refund successfully synced to TaxJar.', 'taxjar' ),
			'refund-not-synced' => __( 'Refund has not been synced to TaxJar.', 'taxjar' ),
			'refund-failed'     => __( 'Refund failed to sync to TaxJar.', 'taxjar' ),
		);

		return $tips[ $type . '-' . $status ];
	}
}
