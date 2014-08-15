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
  public function __construct() {
    global $woocommerce;

    $this->id                 = 'taxjar-integration';
    $this->method_title       = __( 'TaxJar Integration', 'wc-taxjar' );
    $this->method_description = __( 'TaxJar is the easiest to use tax reporting and calculation engine for small business owners and sales tax professionals. Enter your API token (<a href="http://www.taxjar.com/api/" target="_blank">Click here to get token</a>) to configure your TaxJar for Woocommerce installation. For help, email support@taxjar.com or reach out to us via live chat at <a href="http://taxjar.com">TaxJar.com</a>.', 'wc-taxjar' );
    $this->uri                = 'http://api.taxjar.com/';
		
    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables.
    $this->api_token        = $this->get_option( 'api_token' );
    $this->store_zip        = $this->get_option( 'store_zip' );
    $this->store_city       = $this->get_option( 'store_city' );
    $this->enabled          = filter_var($this->get_option( 'enabled' ),FILTER_VALIDATE_BOOLEAN);
    $this->debug            = filter_var($this->get_option( 'debug' ),FILTER_VALIDATE_BOOLEAN);

		if ('yes' == $this->debug){
			$this->log            = new WC_Logger();
		}

    // Catch Rates for 1 hour
    $this->cache_time = HOUR_IN_SECONDS;
    $this->ua = 'TaxJarWordPressPlugin/1.0/WordPress/' . get_bloginfo( 'version' ) . '+WooCommerce/' . $woocommerce->version . '; ' . get_bloginfo( 'url' );

		// TaxJar Config Integration Tab
    add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		
		if ( ( $this->enabled ) ) {
			// Actions.
   		add_action( 'woocommerce_calculate_totals', array( $this, 'get_tax_rate_from_taxjar_and_update_total_tax' ), 15, 1 );
	
			// Settings Page
			add_action('woocommerce_sections_tax',array($this, 'output_sections_before'),9);
			add_action('woocommerce_sections_tax',array($this, 'output_sections_after'),11);
		
	    // Filters.
	    add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
	    add_filter( 'woocommerce_cart_tax_totals', array($this, 'display_taxjar_totals'), 0, 2);
	    add_filter( 'woocommerce_cart_taxes_total', array($this, 'get_taxes_total'), 0, 4);
	    add_filter( 'woocommerce_cart_get_taxes', array($this, 'display_taxjar_totals'), 0, 2);
	    add_filter( 'woocommerce_order_tax_totals', array($this, 'display_taxjar_totals'), 0, 2);
		}
  }


  /**
   * Initialize integration settings form fields.
   *
   * @return void
   */
  public function init_form_fields() {
    $default_wc_settings = explode( ':', get_option('woocommerce_default_country') );
    $this->form_fields   = array(
      'api_token' => array(
        'title'             => __( 'API Token', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( 'You can find this by logging into TaxJar and clicking "Account", then clicking "API Access". Copy and paste your API Token here.', 'wc-taxjar' ),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'store_zip' => array(
        'title'             => __( 'Store Zip Code', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( 'Enter the five digit zip code of the location of your store.', 'wc-taxjar' ),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'store_city' => array(
        'title'             => __( 'Store City', 'wc-taxjar' ),
        'type'              => 'text',
        'description'       => __( 'Enter the city where your store is located.', 'wc-taxjar' ),
        'desc_tip'          => true,
        'default'           => ''
      ),
      'store_state' => array(
        'title'             => __( 'Store State', 'wc-taxjar' ),
        'type'              => '',
        'description'       => __( 'We have automatically detected your state as being ' . $default_wc_settings[1] . '.', 'wc-taxjar' ),
        'class'             => 'input-text disabled regular-input',
        'disabled'          => 'disabled',
        'default'           => $default_wc_settings[1]
      ),
      'debug' => array(
        'title'             => __( 'Debug Log', 'wc-taxjar' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable logging', 'wc-taxjar' ),
        'default'           => 'no',
        'description'       => __( 'Log events such as API requests', 'wc-taxjar' ),
      ),
      'enabled' => array(
        'title'             => __( 'Enabled', 'wc-taxjar' ),
        'type'              => 'checkbox',
        'label'             => __( 'Enable TaxJar', 'wc-taxjar' ),
        'default'           => 'no',
        'description'       => __( 'If enabled, TaxJar will calculate all sales tax for your store.', 'wc-taxjar' ),
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
			if ( is_array( $message ) || is_object( $message ) ) {
				$this->log->add('taxjar', print_r($message,true));
			}
			else{
				$this->log->add('taxjar', $message);
			}
	  }
  }

  /**
  * Grabs the customer's shipping information from the custommer's session.
  *
  * @return void
  */
  public function get_tax_rate_from_taxjar_and_update_total_tax( $wc_cart_object ) {
	  global $woocommerce;
	  $customer = $woocommerce->customer;
	  $amount_to_collect = 0;
	  $store_settings   = $this->get_store_settings();
		if ( ! $customer->is_vat_exempt() ){
		  if ( ( $customer->get_country() == 'US' ) && ( $customer->get_state() == $store_settings['store_state_setting'] ) ) {
		    $postcode         = explode( ',' , $customer->get_postcode() );
		    $to_zip           = $postcode[0];
		    $to_city          = $customer->get_city();
		    $state            = $store_settings['store_state_setting'];
		    $from_zip         = $store_settings['taxjar_zip_code_setting'];
		    $from_city        = $store_settings['taxjar_city_setting'];
		    $amount           = $this->taxjar_taxable_amount($woocommerce->cart);
		    $shipping_amount  = $woocommerce->cart->shipping_total;
		    $url              = sprintf( $this->uri . 'sales_tax?state=%s&amount=%s&shipping=%s&from_city=%s&from_zip=%s&to_city=%s&to_zip=%s', $state, $amount, $shipping_amount, $from_city, $from_zip, $to_city, $to_zip );
		    $url              = str_replace( ' ', '%20', $url );
		    $cache_key        = hash( 'md5', $url );
				if ( false === ( $amount_to_collect = get_transient( $cache_key ) ) ) {
		      $this->_log( "Requesting: " . $url );
		      $headers  = array( 'Authorization' => 'Token token="' . $this->settings['api_token'] .'"');
		      $response = wp_remote_get( $url, array( 'headers' => $headers, 'user-agent' => $this->ua ) );
		      if ( is_wp_error( $response ) ) {
		        new WP_Error( 'request', __( "There was an error retrieving the tax rates. Please check your server configuration." ) );
		      }
		      elseif ( 200 == $response['response']['code'] ) {
		        $this->_log( "Received: " . $response['body'] );
		        $taxjar_response = json_decode( $response['body'] );
		        $amount_to_collect = $taxjar_response->amount_to_collect;
						$rate_collected = $taxjar_response->rate;
		        set_transient( $cache_key, $amount_to_collect, $this->cache_time );
		      }
		      else {
		        $this->_log( "Received: " . $response['body'] );
		      }
		    }
		    $wc_cart_object->tax_total = $amount_to_collect;
		    $this->tax_total           = $amount_to_collect;
				$this->tax_rate            = $rate_collected;
		  }
		}    
  }


  /**
  * Overrides the get_taxes_total method of class-wc-cart.php
  *
  * @return void
  */
  public function get_taxes_total( $total, $compound, $display, $wc_cart_object ) {
		if ( empty($this->tax_total) ) {
			$total = 0;
		}else{
			$total = $this->tax_total;
		}
     return $total;
  }


  /**
  * Displays tax totals on cart page and orders based on an array of taxes/tax rates for the cart.
  *
  * @return array
  */
	public function display_taxjar_totals( $taxes, $order = NULL) {   
     $tax                   = new stdClass();
     $tax->rate             = 0;
     $tax->tax_rate_id      = 'taxjar_live_rate';
     $tax->is_compound      = true;
     $tax->label            = 'Sales Tax';
     $tax->calc_tax         = 'per_order';
     $tax->formatted_amount = woocommerce_price( 0 );
     if ( $this->tax_total ) {
				$tax->rate          		= $this->tax_rate;       
				$tax->amount            = $this->tax_total;
       	$tax->formatted_amount  = woocommerce_price( $this->tax_total );
     }
     else {
       if ( method_exists( $order, get_total_tax ) ) {
         $tax->amount           = $order->get_total_tax();
         $tax->formatted_amount = woocommerce_price( $order->get_total_tax() );
       }
     }
     $tax_values = array( 'TAX' => $tax );
     $this->_log( "TAX: " . json_encode($tax_values) );
     return $tax_values;    
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
      $settings['api_token']   = strtolower( wc_clean( $settings['api_token'] ) );
      $settings['store_zip'] = wc_clean( $settings['store_zip'] );
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
    $value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];

    // check if the API token is longer than 32 characters.
    if ( isset( $value ) &&
       32 < strlen( $value ) &&
       $this->api_token_is_not_valid( $key ) ) {
      $this->errors[] = $key;
    }
    return $value;
  }

  /**
  * Gets the store's settings and returns them
  *
  * @return array
  */
  private function get_store_settings() {
    $default_wc_settings     = explode( ':', get_option('woocommerce_default_country') );
    $taxjar_zip_code_setting = $this->settings['store_zip'];
    $taxjar_city_setting     = $this->settings['store_city'];
    $store_settings          = array( 'taxjar_zip_code_setting' => $taxjar_zip_code_setting , 'store_state_setting' => $default_wc_settings[1], 'taxjar_city_setting' => $taxjar_city_setting );
    return $store_settings;
  }


 /**
 * Checks the validity of the token by making a test call. Returns true if it encouters any errors.
 *
 * @return boolean
 */
 public function api_token_is_not_valid( $key ) {
    $url   = sprintf( $this->uri . 'sales_tax?state=%s&amount=%s&shipping=%s&from_city=%s&from_zip=%s&to_city=%s&to_zip=%s', 'TX', '100', '100', 'Austin', '73301', 'Austin', '73301' );
    $token = $this->settings['api_token'] ;
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
  public function display_errors() {

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
	public function output_sections_before(  ) {	
		echo'<div class="taxjar"><h3>Tax Rates Powdered by <a href="http://www.taxjar.com" target="_blank">TaxJar</a></h3></div>';
		echo'<div style="display:none;">';		
	}
	
	/**
   * Hack to hide the tax sections for additional tax class rate tables.
   * 
   */
	public function output_sections_after(  ) {		
		echo'</div>';		
	}

	/**
   * Determine taxable amount for items in cart
   * @return float
   */
	private function taxjar_taxable_amount($wc_cart_object){
		$taxable_amount = 0;
		//$this->_log( $wc_cart_object );
		
		//$woocommerce->cart->subtotal - $woocommerce->cart->discount_total;
				
		foreach ( $wc_cart_object->cart_contents as $key => $item ) {
			$_product = $item['data'];
			if ( $_product->is_taxable() ) {
				$base_price = $_product->get_price();
				$price = $_product->get_price() * $item['quantity'];
				$discounted_price = $wc_cart_object->get_discounted_price( $item, $base_price, true );
				$taxable_amount += $discounted_price * $item['quantity'];
			}			
		}
		return $taxable_amount;
	}

}

endif;
