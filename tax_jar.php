<?php
/*
Plugin Name: TaxJar (Tax Jar integration for WooCommerce)
Plugin URI: http://seanvoss.com/taxjar
Description: TaxJar integration to lookup rates.
Version: 0.2
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
