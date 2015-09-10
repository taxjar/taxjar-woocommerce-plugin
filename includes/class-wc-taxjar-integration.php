<?php
/**
 * Integration TaxJar
 *
 * @package  WC_Taxjar_Integration
 * @category Integration
 * @author   TaxJar
 */


if ( ! class_exists( 'WC_Taxjar_Integration' ) ) :

class WC_Taxjar_Integration extends WC_Integration {

  /**
   * Init and hook in the integration.
   */
  public function __construct( ) {
    global $woocommerce;

    $this->id                 = 'taxjar-integration';
    $this->method_title       = __( 'TaxJar Integration', 'wc-taxjar' );
    $this->method_description = __( 'TaxJar is the easiest to use tax reporting and calculation engine for small business owners and sales tax professionals. Enter your API token (<a href="https://app.taxjar.com/api_sign_up/" target="_blank">click here to get a token</a>), the city, and the zip code from which your store ships to configure your TaxJar for Woocommerce installation.  You may also enable "Order Downloads" to immediately allow access to import the transactions from this store into your TaxJar account, all in one click! For help, email support@taxjar.com or reach out to us via live chat at <a href="http://taxjar.com">TaxJar.com</a>.', 'wc-taxjar' );
    $this->integration_uri  = 'https://app.taxjar.com/account/apps/add/woo';
    $this->app_uri          = 'https://app.taxjar.com/';
    $this->uri              = 'https://api.taxjar.com/v2/';

    // Load the settings.
    $this->init_settings();

    // Define user set variables.
    $this->api_token        = $this->get_option( 'api_token'  );
    $this->store_zip        = $this->get_option( 'store_zip'  );
    $this->store_city       = $this->get_option( 'store_city' );
    $this->enabled          = filter_var( $this->get_option( 'enabled' ),         FILTER_VALIDATE_BOOLEAN );
    $this->debug            = filter_var( $this->get_option( 'debug' ),           FILTER_VALIDATE_BOOLEAN );
    $this->taxjar_download  = filter_var( $this->get_option( 'taxjar_download' ), FILTER_VALIDATE_BOOLEAN );

    // Build the Admin Form
    $this->init_form_fields();

    // Catch Rates for 1 hour
    $this->cache_time = HOUR_IN_SECONDS;

    // User Agent for WP_Remote
    $this->ua = 'TaxJarWordPressPlugin/1.1.3/WordPress/' . get_bloginfo( 'version' ) . '+WooCommerce/' . $woocommerce->version . '; ' . get_bloginfo( 'url' );

    // TaxJar Config Integration Tab
    add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
    add_action( 'admin_menu', array( $this, 'taxjar_admin_menu' ),  15);

    if ( ( $this->settings['enabled'] == 'yes' ) ) {

      // Calculate Taxes
      add_action( 'woocommerce_calculate_totals', array( $this, 'use_taxjar_total' ), 20 );

      add_filter( 'woocommerce_ajax_calc_line_taxes', array( $this, 'admin_ajax_calculate_taxes' ), 1, 4 );

      // Settings Page
      add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_before' ),  9 );

      add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_after'  ),  11);

      // Filters.
      add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

      add_filter( 'woocommerce_customer_taxable_address', array( $this, 'append_base_address_to_customer_taxable_address' ), 10, 1 );

      // WP Hooks
      add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_assets' ) );

      // If TaxJar is enabled and a user disables taxes we renable them
      update_option( 'woocommerce_calc_taxes', 'yes' );

      // Users can set either billing or shipping address for tax rates but not shop
      update_option( 'woocommerce_tax_based_on', "shipping" );

      // Rate calculations assume tax not inlcuded
      update_option( 'woocommerce_prices_include_tax', 'no' );

      // Don't ever set a default customer address
      update_option( 'woocommerce_default_customer_address', '' );

      // Use no special handling on shipping taxes, our API handles that
      update_option( 'woocommerce_shipping_tax_class', '' );

      // API handles rounding precision
      update_option( 'woocommerce_tax_round_at_subtotal', 'no' );

      // Rates are calculated in the cart assuming tax not included
      update_option( 'woocommerce_tax_display_shop', 'excl' );

      // TaxJar returns one total amount, not line item amounts
      update_option( 'woocommerce_tax_display_cart', 'excl' );

      // TaxJar returns one total amount, not line item amounts
      update_option( 'woocommerce_tax_total_display', 'single' );

    }

    // Anytime TaxJar Download is off, disable the TaxJar user
    if ( $this->taxjar_download == 0 ) {
      $this->disable_taxjar_user();
    }

    // Add script that reloads integration page if we have set taxjar_download
    if ( isset( $_POST['woocommerce_taxjar-integration_taxjar_download'] ) ) {
      add_action( 'admin_enqueue_scripts', array( $this, 'reload_page' ) );
      // Enable the WooCommerce API for downloads if it is not enabled
      update_option( 'woocommerce_api_enabled', 'yes' );
    }

  }

  /**
   * Initialize integration settings form fields.
   *
   * @return void
   */
  // fix undefined offset for country not set...
  public function init_form_fields( ) {
    global $woocommerce;

    $default_wc_settings = explode( ':', get_option('woocommerce_default_country') );
    if ( empty( $default_wc_settings[1] ) ){
      $default_wc_settings[1] = "N/A";
    }
    // Check for the TaxJar user
    $user = $this->api_user_query();

    // Display keys if we can
    if ( ( $this->settings['taxjar_download'] == 'yes' ) && isset( $user ) ) {
      $this->api_user = get_userdata( $user->ID );
      if ( ( $this->api_user ) && ( $this->settings['taxjar_download'] == 'yes' ) && ( $woo_key = $this->api_user->woocommerce_api_consumer_key ) && ( $woo_secret = $this->api_user->woocommerce_api_consumer_secret ) ) {
        $key = hash( 'md5', $woo_key.$woo_secret.get_bloginfo( 'url' ).$this->settings['api_token'] );
        // Check to see if we are working with WooCommerce version 2.4 or greater
        if ( version_compare( $woocommerce->version, '2.4.0', '>=' ) )  {
          // Check if there is a key with either a description that is like TaxJar or a user that is like TaxJar
          if( $this-> existing_api_key() ) {
            $description_for_order_download = sprintf( "A TaxJar API Key has been created. It can be managed <a href='%s'>here</a>.", admin_url('admin.php?page=wc-settings&tab=api&section=keys'));
          } else {
            $description_for_order_download = "
              <div class='taxjar-generate-api-key-wrap'>
                <div class='generate-new-api-key'>
                  <p class='description'>
                    You have not yet granted access to TaxJar for order downloads, <a href='#' class='js-taxjar-generate-api-key'>Generate WooCommerce API Key</a> now.
                    <br />Further <a href='http://support.taxjar.com/knowledge_base/topics/how-to-generate-woocommerce-2-dot-4-api-keys' target='_blank'>instructions</a>.
                  </p>
                </div>
                <p class='taxjar-generate-api-content description'></p>
              </div>";
          }
        }
        // Check to see if we have recorded sending these the keys in the last 30 days
        elseif ( false === ( $cache_value = get_transient( $key ) ) ) {
          $description_for_order_download = sprintf( 'Consumer Key: <code>%s</code><br/>Consumer Secret: <code>%s</code><br/>Visit our <a href="%s" target="_blank">WooCommerce Integration</a> page to complete our easy setup!', $woo_key, $woo_secret, $this->integration_uri );
        }
        else {
          $description_for_order_download = sprintf( 'Consumer Key: <code>%s</code><br/>Consumer Secret: <code>%s</code><br>Your Store and TaxJar account has been linked.<br>Enroll in AutoFile, see sales tax reports and more on <a target="_blank" href="%sdashboard">your dashboard</a>', $woo_key, $woo_secret, $this->app_uri );
        }
      } else {
        $description_for_order_download = "There was an error retrieving your keys. Please disable and reenable Order Downloads.";
      }
    }
    else {
      if ( version_compare( $woocommerce->version, '2.4.0', '>=' ) )  {
        $description_for_order_download = "If enabled, you can then generate secure tokens to grant TaxJar access to your store to download your order history.";
      } else {
        $description_for_order_download = "If enabled, a TaxJar user will be created on your store for WooCommerce Order downloads.<br>We then generate secure tokens to access your store and enable the WooCommerce REST API if it is disabled.";
      }
    }

    // Build the form array
    $this->form_fields   = array(
      'enabled' => array(
        'title'             => __( 'Sales Tax Calculation', 'wc-taxjar' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable TaxJar Calculations', 'wc-taxjar' ),
        'default'           => 'no',
        'description'       => __( 'If enabled, TaxJar will calculate all sales tax for your store.', 'wc-taxjar' ),
      ),
      'taxjar_download' => array(
        'title'             => __( 'Sales Tax Reporting', 'wc-taxjar' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable order downloads to TaxJar', 'wc-taxjar' ),
        'default'           => 'no',
        'disabled'          => 'disabled',
        'description'       => __( $description_for_order_download, 'wc-taxjar' ),
      ),
      'api_token' => array(
        'title'             => __( 'API Token', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( '<a href="'.$this->app_uri.'account#api-access" target="_blank">Click here</a> to get your API token.', 'wc-taxjar' ),
        'desc_tip'          => false,
        'default'           => ''
      ),
      'store_zip' => array(
        'title'             => __( 'Ship From Zip Code', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( 'Enter the zip code from which your store ships products.', 'wc-taxjar' ),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'store_city' => array(
        'title'             => __( 'Ship From City', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( 'Enter the city where your store ships from.', 'wc-taxjar' ),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'store_state' => array(
        'title'             => __( 'Ship From State', 'wc-taxjar' ),
        'type'              => 'hidden',
        'description'       => __( 'We have automatically detected your ship from state as being ' . $default_wc_settings[1] . '.', 'wc-taxjar' ),
        'class'             => 'input-text disabled regular-input',
        'disabled'          => 'disabled',
      ),
      'store_country' => array(
        'title'             => __( 'Ship From Country', 'wc-taxjar' ),
        'type'              => 'hidden',
        'description'       => __( 'We have automatically detected your ship from country as being ' . $default_wc_settings[0] . '.', 'wc-taxjar' ),
        'class'             => 'input-text disabled regular-input',
        'disabled'          => 'disabled'
      ),
      'taxjar_addresses' => array(
        'title'             => __( 'Other Addresses', 'wc-taxjar' ),
        'type'              => 'hidden',
        'description'       => __( 'We automatically perform multi-state tax rate determination using all available addresses in your TaxJar account. These addresses are checked in addition to your automatically detected settings above. <a href="'.$this->app_uri.'account#api-access">Click here</a> to see and configure these additional addresses.', 'wc-taxjar' ),
        'class'             => 'input-text disabled regular-input',
        'disabled'          => 'disabled'
      ),
      'debug' => array(
        'title'             => __( 'Debug Log', 'wc-taxjar' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable logging', 'wc-taxjar' ),
        'default'           => 'no',
        'description'       => __( 'Log events such as API requests.', 'wc-taxjar' ),
      )
    );
  }

  /**
  * Prints information to wp-content/debug.log
  *
  * @return void
  */
  private function _log( $message ) {
    if ( WP_DEBUG === true ) {
      if ( is_array( $message ) || is_object( $message ) ) {
        error_log( print_r( $message, true ) );
      }
      else {
        error_log( $message );
      }
    }
    if ( $this->debug ) {
      $this->log = new WC_Logger();
      if ( is_array( $message ) || is_object( $message ) ) {
        $this->log->add('taxjar', print_r($message,true));
      }
      else{
        $this->log->add('taxjar', $message);
      }
    }
  }

  /**
  * Load WC-TaxJar.js
  *
  * @return void
  */
  public function reload_page( ){
    wp_enqueue_script("wc-taxjar", plugin_dir_url( __FILE__ ) . '/js/wc-taxjar.js', array( 'jquery' ));
  }

  /**
  * Direct copy and paste of private function in WC_Tax class file
  */
  public function _get_wildcard_postcodes( $postcode ) {
		$postcodes         = array( '*', strtoupper( $postcode ) );
		$postcode_length   = strlen( $postcode );
		$wildcard_postcode = strtoupper( $postcode );

		for ( $i = 0; $i < $postcode_length; $i ++ ) {
			$wildcard_postcode = substr( $wildcard_postcode, 0, -1 );
			$postcodes[] = $wildcard_postcode . '*';
		}
		return $postcodes;
	}


  /**
  * TaxJar API call
  *
  * @return void
  */
  public function taxjar_api_call( $options = array() ) {

    global $woocommerce;

    $this->_log(':::: TaxJar Plugin requested ::::');

    // Process $options array and turn them into varibables
    $options = is_array($options) ? $options : array();
    extract( array_replace_recursive(array(
        'to_country' =>       null,
        'to_state' =>         null,
        'to_zip' =>           null,
        'to_city' =>          null,
        'amount' =>           null, // $this->taxjar_taxable_amount($woocommerce->cart);
        'shipping_amount' =>  null // $woocommerce->shipping->shipping_total
    ), $options) );


     // Initalize some variables & properties
    $store_settings           = $this->get_store_settings();
    $customer                 = $woocommerce->customer;

    $this->tax_rate           = 0;
    $this->amount_to_collect  = 0;
    $this->item_collectable   = 0;
    $this->shipping_collectable  = 0;
    $this->freight_taxable    = 1;
    $this->has_nexus          = 0;
    $this->tax_source         = 'origin';
    $this->rate_id            = null;

    // Strict conditions to be met before API call can be conducted
    if(
         empty( $to_state )
      || empty( $to_country )
      || empty( $to_zip )
      || $customer->is_vat_exempt()
    ) return false;

    // Setup Vars for API call
    $to_zip           = explode( ',' , $to_zip );
    $to_zip           = array_shift( $to_zip );

    $from_country     = $store_settings[ 'store_country_setting' ];
    $from_state       = $store_settings[ 'store_state_setting' ];
    $from_zip         = $store_settings[ 'taxjar_zip_code_setting' ];
    $from_city        = $store_settings[ 'taxjar_city_setting' ];

    $this->_log(':::: TaxJar API called ::::');

    $url              = $this->uri . 'taxes';
    $body_string      = sprintf('plugin=woo&to_state=%s&from_state=%s&amount=%s&shipping=%s&from_city=%s&from_zip=%s&to_city=%s&to_zip=%s&from_country=%s&to_country=%s&line_items[][quantity]=1&line_items[][unit_price]=%s',
                          $to_state,
                          $from_state,
                          $amount,
                          $shipping_amount,
                          $from_city,
                          $from_zip,
                          $to_city,
                          $to_zip,
                          $from_country,
                          $to_country,
                          $amount
                        );

    // Build the URL and Transient key
    $url              = str_replace( ' ', '%20', $url );
    $cache_key        = hash( 'md5', $url . '/' . $body_string  );

    // To check to see if we hit the API or not
    $taxjar_response = false;

    // Make sure we don't have a cached rate
    if ( false === ( $cache_value = get_transient( $cache_key ) ) ) {
      // Log this request
      $this->_log( "Requesting: " . $url );

      // Make API call with API token in header
      $response = wp_remote_post( $url, array(
        'headers' =>    array(
                          'Authorization' => 'Token token="' . $this->settings['api_token'] .'"',
                          'Content-Type' => 'application/x-www-form-urlencoded'
                        ),
        'user-agent' => $this->ua,
        'body' => $body_string
      ) );

      // Fail loudly if we get an error from wp_remote_post
      if ( is_wp_error( $response ) ) {
        new WP_Error( 'request', __( "There was an error retrieving the tax rates. Please check your server configuration." ) );
      } else if ( 200 == $response['response']['code'] ) {

        // Log the response
        $this->_log( "Received: " . $response['body'] );

        // Decode Response
        $taxjar_response          = json_decode( $response['body'] );
        $taxjar_response = $taxjar_response->tax;
        // Update Properties based on Response
        $this->has_nexus          = (int) $taxjar_response->has_nexus;
        $this->tax_source         = empty($taxjar_response->tax_source) ? 'origin' : $taxjar_response->tax_source;
        $this->amount_to_collect  = $taxjar_response->amount_to_collect;
        if (!empty($taxjar_response->breakdown)) {
          $this->shipping_collectable  = $taxjar_response->breakdown->shipping->tax_collectable;
          $this->item_collectable  = $this->amount_to_collect - $this->shipping_collectable;
        }
        $this->tax_rate           = $taxjar_response->rate;
        $this->freight_taxable    = (int) $taxjar_response->freight_taxable;

        // Create cache value
        $cache_value              = $this->amount_to_collect . '::' . $this->tax_rate . '::' . $this->freight_taxable. '::' . $this->has_nexus . '::' . $this->tax_source . '::' . $this->item_collectable  . '::' . $this->shipping_collectable;

        // Log the new cached value
        $this->_log( "Cache Value: ". $cache_value );

        // Set Cache
        set_transient( $cache_key, $cache_value, $this->cache_time );

      } else {
        // Log Response Error
        $this->_log( "Received (" . $response['response']['code'] . "): " . $response['body'] );
      }

    } else {

      // Read the cached value based on our delimiter
      $cache_value = explode( '::', $cache_value );

      // Set properties to the cached values
      $this->amount_to_collect  = $cache_value[0];
      $this->tax_rate           = $cache_value[1];
      $this->freight_taxable    = $cache_value[2];
      $this->has_nexus 				  = $cache_value[3];
      $this->tax_source 			  = $cache_value[4];
      $this->item_collectable 	  = $cache_value[5];
      $this->shipping_collectable = $cache_value[6];

      // Log Cached Response
      $this->_log(  "Cached Amount: ".     $this->amount_to_collect  );
      $this->_log(  "Cached Nexus: ".      $this->has_nexus          );
      $this->_log(  "Cached Source: ".     $this->tax_source         );
      $this->_log(  "Cached Rate: ".       $this->tax_rate           );
      $this->_log(  "Shipping Taxable? ".  $this->freight_taxable    );
      $this->_log(  "Item Tax to Collect: ".  $this->item_collectable    );
      $this->_log(  "Shipping Tax to Collect: ".  $this->shipping_collectable);
    }

    // Remove taxes if they are set somehow and customer is exempt
    if ( $customer->is_vat_exempt() ) {
      $wc_cart_object->remove_taxes();
    } elseif ( $this->has_nexus ) {
    //} else {

			// Use Woo core to find matching rates for taxable address
			$source_zip = $this->tax_source == 'destination' ? $to_zip : $from_zip;
			$source_city = $this->tax_source == 'destination' ? $to_city : $from_city;

      // Setup Tax Rates
      $tax_rates = array(
			  "tax_rate_country" =>   $to_country,
			  "tax_rate_state" =>     $to_state,
			  "tax_rate_name" =>      sprintf( "%s Tax", $to_state ),
			  "tax_rate_priority" =>  1,
			  "tax_rate_compound" =>  false,
			  "tax_rate_shipping" =>  $this->freight_taxable,
			  "tax_rate" =>           $this->tax_rate * 100,
			  "tax_rate_class" =>     ''
			);

			// Clear the cached rates
			delete_transient( 'wc_tax_rates_' . md5( sprintf( '%s+%s+%s+%s+%s', $to_country, $to_state, ($source_city), implode( ',', $this->_get_wildcard_postcodes( wc_clean( $source_zip ) ) ), '' ) ) );
			$this->_log( $source_city );
			$wc_rates = WC_Tax::find_rates( array(
        'country' =>        strtoupper($to_country),
        'state' =>          strtoupper($to_state),
        'postcode' =>       strtoupper($source_zip),
        'city' =>           strtoupper($source_city),
        'tax_class' =>      ''
      ) );

			// If we have rates, use those, but if no rates returned create one to link with, or use the first rate returned.
			if ( !empty( $wc_rates ) ) {

        $this->_log( '::: TAX RATES FOUND :::' );
        $this->_log( $wc_rates );

  		  // Get the existing ID
        $rate_id = key($wc_rates);

        // Update Tax Rates with TaxJar rates ( rates might be coming from a cached taxjar rate )
        $this->_log( ':: UPDATING TAX RATES TO ::' );
        $this->_log( $tax_rates );
        WC_TAX::_update_tax_rate( $rate_id, $tax_rates );

      } else {

  		  // Insert a rate if we did not find one
  		  $this->_log(':: Adding New Tax Rate ::');
				$rate_id = WC_Tax::_insert_tax_rate( $tax_rates );
				WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $source_zip ) );
				WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $source_city ) );

      }

      $this->_log('Tax Rate ID Set: '. $rate_id);

      $this->rate_id = $rate_id;
    }
  }


  /**
  * Ensure use of the TaxJar amount_to_collect and API data
  *
  * @return void
  */
  public function use_taxjar_total( $wc_cart_object ) {

    global $woocommerce;

    // Get all of the required customer params
    $taxable_address = $woocommerce->customer->get_taxable_address(); // returns unassociated array
    $taxable_address = is_array($taxable_address) ? $taxable_address : array();

    $to_country = isset($taxable_address[0]) && !empty($taxable_address[0]) ? $taxable_address[0] : false;
    $to_state = isset($taxable_address[1]) && !empty($taxable_address[1]) ? $taxable_address[1] : false;
    $to_zip = isset($taxable_address[2]) && !empty($taxable_address[2]) ? $taxable_address[2] : false;
    $to_city = isset($taxable_address[3]) && !empty($taxable_address[3]) ? $taxable_address[3] : false;

    $this->taxjar_api_call( array(
      'to_city' =>          $to_city,
      'to_state' =>         $to_state,
      'to_country' =>       $to_country,
      'to_zip' =>           $to_zip,
      'amount' =>           $this->taxjar_taxable_amount($woocommerce->cart),
      'shipping_amount' =>  $woocommerce->shipping->shipping_total,
      'customer' =>         $woocommerce->customer
    ) );

    // Store the rate ID and the amount on the cart's totals
    $wc_cart_object->tax_total = $this->item_collectable;
    $wc_cart_object->taxes = array($this->rate_id => $this->item_collectable);

  }


  public function admin_ajax_calculate_taxes( $items, $order_id, $country, $post ){

    global $woocommerce;

    $post = is_array($post) ? $post : array();

    extract( array_replace_recursive(array(
        'country' =>    null,
        'state' =>      null,
        'postcode' =>   null,
        'city' =>       null
    ), $post), EXTR_SKIP );

    if(
         empty( $country )
      || empty( $state )
      || empty( $postcode )
      || empty( $city )
    ) return false;

    $order = wc_get_order( $order_id );

    $this->taxjar_api_call( array(
      'to_city' =>          $city,
      'to_state' =>         $state,
      'to_country' =>       $country,
      'to_zip' =>           $postcode,
      'amount' =>           $order->order_total,
      'shipping_amount' =>  null
    ) );

    return $items;
  }

  /**
  * Set customer zip code and state to store if local shipping option set
  *
  * @return array
  */
  public function append_base_address_to_customer_taxable_address( $address ){
    $store_settings = $this->get_store_settings();
    list( $country, $state, $postcode, $city ) = $address;
    $tax_based_on = '';

    // See WC_Customer get_taxable_address()
    if ( apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) == true && WC()->cart->needs_shipping() && sizeof( array_intersect( WC()->session->get( 'chosen_shipping_methods', array( get_option( 'woocommerce_default_shipping_method' ) ) ), apply_filters( 'woocommerce_local_pickup_methods', array( 'local_pickup' ) ) ) ) > 0 ) {
      $tax_based_on = 'base';
    }
    if ( $tax_based_on == 'base' ) {
      $postcode  = $store_settings['taxjar_zip_code_setting'];
      $city = $store_settings['taxjar_city_setting'];
    }
    return array( $country, $state, $postcode, $city );
  }


  /**
  * Search for the row in DB with the TaxJar user
  *
  * @return OBJECT
  */
  private function api_user_query( ){
    global $wpdb;
    return $wpdb->get_row( "SELECT * FROM $wpdb->users WHERE user_login LIKE 'api_taxjar_%'" );
  }

  /**
  * Check if there is an existing WooCommerce 2.4 API Key
  *
  * @return boolean
  */

  private function existing_api_key( ) {
    global $wpdb;
    $sql = "SELECT count(key_id)
        FROM {$wpdb->prefix}woocommerce_api_keys
        LEFT JOIN $wpdb->users
        ON {$wpdb->prefix}woocommerce_api_keys.user_id={$wpdb->users}.ID
        WHERE ({$wpdb->users}.user_login LIKE '%taxjar%' OR {$wpdb->prefix}woocommerce_api_keys.description LIKE '%taxjar%');";
    return ( $wpdb->get_var( $sql ) > 0 );
  }

  /**
  * Generate Button HTML.
  */
  public function generate_button_html( $key, $data ) {
    $field    = $this->plugin_id . $this->id . '_' . $key;
    $defaults = array(
      'class'             => 'button-secondary',
      'css'               => '',
      'custom_attributes' => array(),
      'desc_tip'          => false,
      'description'       => '',
      'title'             => '',
    );
    $data = wp_parse_args( $data, $defaults );
    ob_start();
    ?>
    <tr valign="top">
      <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
        <?php echo $this->get_tooltip_html( $data ); ?>
      </th>
      <td class="forminp">
        <fieldset>
          <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
          <button class="<?php echo esc_attr( $data['class'] ); ?>" type="button" name="<?php echo esc_attr( $field ); ?>" id="<?php echo esc_attr( $field ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php echo $this->get_custom_attribute_html( $data ); ?>><?php echo wp_kses_post( $data['title'] ); ?></button>
          <?php echo $this->get_description_html( $data ); ?>
        </fieldset>
      </td>
    </tr>
    <?php
    return ob_get_clean();
  }

  /**
  * Santize our settings
  * @see process_admin_options()
  */
  public function sanitize_settings( $settings ) {
    // We're going to make the api token all lower case characters and clean input
    if ( isset( $settings ) &&
         isset( $settings['api_token'] ) &&
         isset( $settings['store_zip'] ) &&
         isset( $settings['store_city'] ) ) {
      $settings['api_token']  = strtolower( wc_clean( $settings['api_token'] ) );
      $settings['store_zip']  = wc_clean( $settings['store_zip'] );
      $settings['store_city'] = wc_clean( $settings['store_city'] );
    }
    return $settings;
  }


  /**
   * Validate the API token
   * @see validate_settings_fields()
   */
  public function validate_api_token_field( $key ) {
    // get the posted value
    $value = $this->get_value_from_post( $key );
    // check if the API token is longer than 32 characters.
    if ( isset( $value ) &&
       32 < strlen( $value ) &&
       $this->api_token_is_not_valid( $key ) ) {
      $this->errors[] = $key;
    }
    return $value;
  }

  /**
   * Validate the option to enable TaxJar order downloads
   * @see validate_settings_fields()
   */
  public function validate_taxjar_download_field( $key ) {
    // Validate the value and perform work for taxjar_download option
    $value = $this->get_value_from_post( $key );
    // Check that we can create users and we have a value set
    if ( isset( $value ) && current_user_can( 'create_users' ) ) {
      // Get the User object
      $user = $this->api_user_query();
      if ( isset( $user ) ) {
        // User found! Generate and keys
        $user_id = $user->ID;
        $this->generate_v1_api_keys( $user_id );
      }
      else {
        if ( isset( $value ) ) {

          // Unique Username with TaxJar prefix
          $username = uniqid('api_taxjar_', true);

          if ( function_exists("openssl_random_pseudo_bytes") ) {

            // Use OPENSSL_RANDOM_PSEDUO if we can for a password
            try {
              $password = openssl_random_pseudo_bytes(32);
            }
            catch ( Exception $e ) {
              $password = uniqid('', true);
            }

          }
          else {
            $password = uniqid('', true);
          }

          // User is created with role shop manager and strong password
          $user_id  = wp_insert_user( array( "user_login" => $username, "user_pass" => $password, "user_nicename" => "TaxJar API User", "user_url" => "http://taxjar.com", "nickname" => "TaxJar", "description" => "User account created by TaxJar for downloading orders.", "role" => "shop_manager" ) );
          if ( is_wp_error( $user_id ) ) {
            new WP_Error( 'general_failure', __( "There was an error creating the new user TaxJar uses to access your store. Please check your server configuration or try to create your keys <a href='profile.php'>here</a>." ) );
          }
          else {
            $this->generate_v1_api_keys( $user_id );
          }

        }
      }
    }
    else {
      new WP_Error( 'permission', __( "Sorry, it looks like you cannot create users. You must be able to create users to use this feature. You may try to try to create your keys <a href='profile.php'>here</a> if you believe this is an error." ) );
    }
    // Return out option's value
    if ( isset( $value ) && $value ) {
      return 'yes';
    }
    else {
      return 'no';
    }
  }

  /**
  * Deletes the TaxJar user and stores keys for 45 days
  *
  * @return array|void
  */
  private function disable_taxjar_user( ) {
    // If we cannot delete users, do nothing
    if ( current_user_can( 'delete_users' ) ) {
      $user = $this->api_user_query();
      if ( isset( $user ) ) {
        $key = hash( 'md5', $this->id );
        // If the keys don't exisit
        if ( false === ( $cache_value = get_transient( $key ) ) ) {
          $userdata = get_userdata( $user->ID );
          $consumer_key = $userdata->woocommerce_api_consumer_key;
          $consumer_secret = $userdata->woocommerce_api_consumer_secret;
          // Store the keys
          set_transient( $key, $consumer_key . '%' . $consumer_secret, 45 * DAY_IN_SECONDS );
          // Must include user.php to use this method
          wp_delete_user( $user->ID );
        }
        else {
          // Only delete the user if the keys are stored
          wp_delete_user( $user->ID );
        }
      }
    }
  }

  /**
  * Gets the store's settings and returns them
  *
  * @return array
  */
  private function get_store_settings( ) {
    $default_wc_settings     = explode( ':', get_option('woocommerce_default_country') );
    $taxjar_zip_code_setting = $this->settings['store_zip'];
    $taxjar_city_setting     = $this->settings['store_city'];
    $store_settings          = array( 'taxjar_zip_code_setting' => $taxjar_zip_code_setting , 'store_state_setting' => $default_wc_settings[1], 'store_country_setting' => $default_wc_settings[0], 'taxjar_city_setting' => $taxjar_city_setting );
    return $store_settings;
  }

  /**
  * Generates v1 WooCommerce API keys just as implemented in WC 2.1
  *
  * @param int
  * @return void
  */
  private function generate_v1_api_keys( $user_id ) {
    // Get userdata and hash for our transient
    $user = get_userdata( $user_id );
    $key = hash( 'md5', $this->id );

    // Check for existing < 45 day old keys
    if ( false === ( $cache_value = get_transient( $key ) ) ) {
      // Generate them if they don't exist
      $consumer_key = 'ck_' . hash( 'md5', $user->user_login . date( 'U' ) . mt_rand() );
      $consumer_secret = 'cs_' . hash( 'md5', $user->ID . date( 'U' ) . mt_rand() );
    }
    else {
      // Read them from the transient if they do exist
      $cache_value = explode( '%', $cache_value );
      $consumer_key = $cache_value[0];
      $consumer_secret = $cache_value[1];
      // Delete the transient since it served its purpose
      delete_transient( $key );
    }

    // If the user does not have keys, add them
    if ( (empty( $user->woocommerce_api_consumer_key ) ) && (empty( $user->woocommerce_api_consumer_secret ) ) ) {
      $permissions = 'read';
      update_user_meta( $user_id, 'woocommerce_api_consumer_key', $consumer_key );
      update_user_meta( $user_id, 'woocommerce_api_consumer_secret', $consumer_secret );
      update_user_meta( $user_id, 'woocommerce_api_key_permissions', $permissions );
    }
    else {
      // Set the keys if the user already has them
      $consumer_key = $user->woocommerce_api_consumer_key;
      $consumer_secret = $user->woocommerce_api_consumer_secret;
    }
  }


  /**
  * Gets the store's settings and returns them
  *
  * @return void
  */
  public function taxjar_admin_menu( ) {
    // Simple shortcut menu item under WooCommerce
    add_submenu_page( 'woocommerce', __( 'TaxJar Settings', 'woocommerce' ), __( 'TaxJar', 'woocommerce' ) , 'manage_woocommerce', 'admin.php?page=wc-settings&tab=integration&section=taxjar-integration' );
  }

  /**
  * Gets the value for a seting from POST given a key or returns false if box not checked
  *
  * @param mixed $key
  * @return mixed $value
  */
  private function get_value_from_post( $key ) {
    if ( isset( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ){
      return $_POST[ $this->plugin_id . $this->id . '_' . $key ];
    }
    else {
      return false;
    }
  }

 /**
 * Checks the validity of the token by making a test call. Returns true if it encouters any errors.
 *
 * @return boolean
 */
  public function api_token_is_not_valid( $key ) {
    $url = $this->uri . 'rates/92093';
    $token = $this->settings['api_token'];
    $this->_log( "Testing token " . hash( 'md5', substr( $token, 27 ) ) . "." );
    $headers  = array( 'Authorization' => 'Token token="' . $token .'"');
    $response = wp_remote_get( $url, array( 'headers' => $headers, 'user-agent' => $this->ua ) );
    if ( is_wp_error( $response ) ) {
      new WP_Error( 'request', __( "There was an error checking your API Token. Please check your server configuration." ) );
    }
    elseif ( 200 == $response['response']['code'] ) {
      $this->_log( "Received: " . $response['body'] );
      $this->_log( "Test passed." );
      return false;
    }
    else {
      $this->_log( "Received: " . $response['body'] );
      $this->_log( "Test failed." );
      return true;
    }
  }

  /**
  * Display errors by overriding the display_errors() method
  * @see display_errors()
  */
  public function display_errors( ) {
    // loop through each error and display it
    foreach ( $this->errors as $key => $value ) {
      ?>
      <div class="error">
        <p><?php _e( 'Looks like you might have made a mistake with the ' . $value . ' field. Make sure it isn&apos;t longer than 32 characters and that the API key is valid.', 'wc-taxjar' ); ?></p>
      </div>
      <?php
    }
  }

  /**
  * Hack to hide the tax sections for additional tax class rate tables.
  *
  */
  public function output_sections_before( ) {
    echo '<div class="taxjar"><h3>Tax Rates Powered by <a href="http://www.taxjar.com" target="_blank">TaxJar</a>. <a href="admin.php?page=wc-settings&tab=integration">Configure TaxJar</a></h3></div>';
    echo '<div style="display:none;">';
  }

  /**
  * Hack to hide the tax sections for additional tax class rate tables.
  *
  */
  public function output_sections_after( ) {
    echo '</div>';
  }

  /**
  * Determine taxable amount for items in cart
  * @return float || void
  */
  private function taxjar_taxable_amount( $wc_cart_object, $process_line_items = false ){
    // Setup variable
    $taxable_amount = 0;
    foreach ( $wc_cart_object->cart_contents as $key => $item ) {
      $_product = $item['data'];
      // Future use, If we have a tax rate to apply and we have passed in $process_line_items
      if ( $process_line_items && isset( $this->tax_rate ) ) {
        $tax_rate = $this->tax_rate * 100;
        $item['line_tax'] = round( $tax_rate * $item['line_total'] / 100, 2 );
        $item['line_subtotal_tax'] = round( $tax_rate * $item['line_subtotal'] / 100, 2 );
      }
      // If the product is taxable
      if ( $_product->is_taxable() ) {
        // Get the price
        $base_price = $_product->get_price();
        $price = $_product->get_price() * $item['quantity'];
        // Get any discounts and apply them
        $discounted_price = $wc_cart_object->get_discounted_price( $item, $base_price, false );
        // Our final taxable amount
        $taxable_amount += $discounted_price * $item['quantity'];
      }
    }
    if ( ! $process_line_items ) {
      return $taxable_amount;
    }
    else {
      return $wc_cart_object;
    }
  }

  /*
   * Admin Assets
  */
  public function load_taxjar_admin_assets( ) {
    // Add CSS that hides some elements that are known to cause problems
    wp_enqueue_style( 'taxjar-admin-style', plugin_dir_url(__FILE__) .'css/admin.css' );


    // Load Javascript for WooCommerce 2.4 API generation
    wp_register_script( 'wc-taxjar-generate-api-keys', plugin_dir_url( __FILE__ ) . '/js/wc-taxjar-generate-api-key.js' );

    wp_localize_script(
      'wc-taxjar-generate-api-keys',
      'woocommerce_taxjar_admin_api_keys',
      array(
        'ajax_url'         => admin_url( 'admin-ajax.php' ),
        'update_api_nonce' => wp_create_nonce( 'update-api-key' ),
        'current_user'     => get_current_user_id(),
        'integration_uri'  => $this->integration_uri
      )
    );

    wp_enqueue_script( 'wc-taxjar-generate-api-keys' , array( 'jquery' ) );
  }
}

endif;
