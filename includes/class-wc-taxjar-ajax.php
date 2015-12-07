<?php

/**
 * TaxJar AJAX
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */

if ( ! defined( 'ABSPATH' ) )  {
  exit; // Prevent direct access to script
}

class WC_Taxjar_AJAX {

  public function __construct( ) {
    add_action( 'wp_ajax_wc_taxjar_delete_wc_taxjar_keys', array( $this, 'delete_wc_taxjar_keys' ) );
  }

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

    die();
  }

} // WC_Taxjar_AJAX

new WC_Taxjar_AJAX();