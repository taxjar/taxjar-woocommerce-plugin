<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Plugin URI: http://www.taxjar.com/woocommerce
 * Description: Save hours every month by putting your sales tax on autopilot. Automated, multi-state sales tax calculation, collection, and filing.
 * Author: TaxJar
 * Author URI: http://www.taxjar.com
 * Version: 1.0.8
 *
 */

/**
 * Prevent direct access to script
 */
if ( ! defined( 'ABSPATH' ) ) exit;


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
    global $woocommerce;

    // Checks if WooCommerce is installed.
    if ( class_exists( 'WC_Integration' ) ) {
      // Include our integration class and WP_User for wp_delete_user()
      include_once ABSPATH.'wp-admin/includes/user.php';
      include_once 'includes/class-wc-taxjar-integration.php';

      // Register the integration.
      add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ), 20 );

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
 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=taxjar-integration">Settings</a>'; 
  	array_unshift($links, $settings_link); 
  	return $links; 
}

add_filter( 'plugin_action_links_'. plugin_basename( __FILE__ ), 'plugin_settings_link' );

$WC_Taxjar = new WC_Taxjar( __FILE__ );

endif;
