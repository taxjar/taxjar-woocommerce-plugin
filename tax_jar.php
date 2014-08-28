<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Plugin URI: http://taxjar.com/woocommerce
 * Description: TaxJar for WooCommerce helps you collect <strong>accurate sales tax</strong> with almost no work! Stop uploading and updating rate tables. To get started: 1) <a href="http://www.taxjar.com/api/">Sign up for a TaxJar API token</a>, and 2) Go to your <a href="admin.php?page=wc-settings&tab=integration">TaxJar settings page</a>, and save your API token and business address.
 * Author: TaxJar
 * Author URI: http://taxjar.com
 * Version: 1.0.4
 *
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

/**
 * Adds settings link to the plugins page
 */
function plugin_settings_link($links) { 
 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration">Settings</a>'; 
  	array_unshift($links, $settings_link); 
  	return $links; 
}

add_filter('plugin_action_links_'. plugin_basename(__FILE__), 'plugin_settings_link');

$WC_Taxjar = new WC_Taxjar( __FILE__ );

endif;
