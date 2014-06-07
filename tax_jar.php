<?php
/*
Plugin Name: TaxJar (Sales Tax Calculation for WooCommerce)
Plugin URI: http://www.taxjar.com/woocommerce-sales-tax-plugin/
Description: TaxJar for WooCommerce helps you collect <strong>accurate sales tax</strong> with almost no work! Stop uploading and updating rate tables. To get started: 1) <a href="http://www.taxjar.com/api/">Sign up for a TaxJar API token</a>, and 2) Go to your <a href="options-general.php?page=sv_taxjar_plugin">TaxJar settings page</a>, and save your API token and business address.
Version: 0.5
Author: Sean Voss
Author URI: http://blog.seanvoss.com/

*/

/*
 * Title   : TaxJar for WooCommerce
 * Author  : Sean Voss
 * Url     : http://seanvoss.com/cloudy
 * License : http://seanvoss.com/cloudy/legal
 */

function sv_taxjar_init() 
{

    session_start();
    include_once('sv_taxjar.php');
}

add_action('plugins_loaded', 'sv_taxjar_init', 0);

// Add settings link on plugin page
function sv_taxjar_settings_link($links) { 
  $settings_link = '<a href="options-general.php?page=sv_taxjar_plugin">Settings</a>'; 
  array_unshift($links, $settings_link); 
  return $links; 
}
 
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'sv_taxjar_settings_link' );
