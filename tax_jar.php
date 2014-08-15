<?php
/*
Plugin Name: TaxJar - Sales Tax Calculation for WooCommerce
Plugin URI: http://www.taxjar.com/woocommerce-sales-tax-plugin/
Description: TaxJar for WooCommerce helps you collect <strong>accurate sales tax</strong> with almost no work! Stop uploading and updating rate tables. To get started: 1) <a href="http://www.taxjar.com/api/">Sign up for a TaxJar API token</a>, and 2) Go to your <a href="admin.php?page=wc-settings&tab=integration">TaxJar settings page</a>, and save your API token and business address.
Version: 1.0.0
Author: TaxJar
Author URI: http://www.taxjar.com/

*/

if ( ! class_exists( 'WC_Taxjar' ) ) :

class WC_Taxjar {

  /**
  * Construct the plugin.
  */
  public function __construct() {
    add_action( 'plugins_loaded', array( $this, 'init' ) );
  }

  /**
  * Initialize the plugin.
  */
  public function init() {

    // Checks if WooCommerce is installed.
    if ( class_exists( 'WC_Integration' ) ) {
      // Include our integration class.
      include_once 'includes/class-wc-taxjar-integration.php';
			
      // Register the integration.
      add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
    } else {
      // throw an admin error if you like
    }
  }

  /**
   * Add a new integration to WooCommerce.
   */
  public function add_integration( $integrations ) {
    $integrations[] = 'WC_Taxjar_Integration';
    return $integrations;
  }

}

$WC_Taxjar = new WC_Taxjar( __FILE__ );

endif;
