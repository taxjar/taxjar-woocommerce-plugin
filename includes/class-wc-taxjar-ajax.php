<?php
/**
 * TaxJar AJAX
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main TaxJar WooCommerce Class.
 *
 * @class WC_Taxjar
 * @version	1.3.0
 */
class WC_Taxjar_AJAX {

	public function __construct() {
		add_action( 'wp_ajax_wc_taxjar_update_nexus_cache', array( $this, 'wc_taxjar_update_nexus_cache' ) );
		add_action( 'wp_ajax_wc_taxjar_run_transaction_backfill', array( $this, 'wc_taxjar_run_transaction_backfill' ) );
	}

	public function wc_taxjar_update_nexus_cache() {
		check_admin_referer( 'taxjar-update-nexus', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die();
		}

		$taxjar_nexus = new WC_Taxjar_Nexus();
		$taxjar_nexus->get_or_update_cached_nexus( true );

		$response = array(
			'success' => 1
		);

		wp_send_json( $response );
	}

	public function wc_taxjar_run_transaction_backfill() {
		check_admin_referer( 'taxjar-transaction-backfill', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die();
		}

		$date_format = 'Y-m-d';

		$start_date = current_time( $date_format );
		if ( isset( $_POST[ 'start_date' ] ) ) {
			$start_datetime = DateTime::createFromFormat( $date_format, $_POST[ 'start_date' ] );
			if ( $start_datetime ) {
				$start_date = $start_datetime->format( $date_format ) . ' 00:00:00';
			} else {
				$start_date = $start_date . ' 00:00:00';
			}
		}

		$end_date = date( $date_format, strtotime( '+1 day', current_time( 'timestamp' ) ) );
		if ( isset( $_POST[ 'end_date' ] ) ) {
			$end_datetime = DateTime::createFromFormat( $date_format, $_POST[ 'end_date' ] );
			if ( $end_datetime ) {
				$end_date = date( $date_format, strtotime( $end_datetime->format( $date_format ) . ' + 1 day' ) ) .' 00:00:00';
			} else {
				$end_date = $end_date . ' 00:00:00';
			}
		}

		$force_sync = false;
		if ( isset( $_POST[ 'force_sync' ] ) && $_POST[ 'force_sync' ] === 'true' ) {
			$force_sync = true;
		}

		$integration = TaxJar();
		if ( isset( $integration->settings['taxjar_download'] ) && 'yes' == $integration->settings['taxjar_download'] ) {
			$result   = $integration->transaction_sync->transaction_backfill( $start_date, $end_date, $force_sync );

			if ( is_int( $result ) ) {
				$response = array(
					'records_updated' => $result
				);
			} else {
				$response = array(
					'error' => $result
				);
			}

		} else {
			$response = array(
				'error' => 'transaction sync disabled'
			);
		}

		wp_send_json( $response );
	}

} // End WC_Taxjar_AJAX.

new WC_Taxjar_AJAX();
