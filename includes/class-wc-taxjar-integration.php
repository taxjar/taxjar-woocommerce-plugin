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
		$this->method_description = __( 'TaxJar is the easiest to use tax reporting and calculation engine for small business owners and sales tax professionals. Enter your API token (<a href="https://app.taxjar.com/api_sign_up/" target="_blank">click here to get a token</a>), the city, and the zip code from which your store ships to configure your TaxJar for Woocommerce installation.  You may also enable "Order Downloads" to immediately allow access to import the transactions from this store into your TaxJar account, all in one click! For help, email support@taxjar.com or reach out to us via live chat at <a href="http://taxjar.com">TaxJar.com</a>.', 'wc-taxjar' );
		$this->app_uri            = 'https://app.taxjar.com/';
		$this->integration_uri    = $this->app_uri . 'account/apps/add/woo';
		$this->regions_uri        = $this->app_uri . 'account#states';
		$this->uri                = 'https://api.taxjar.com/v2/';
		$this->ua                 = 'TaxJarWordPressPlugin/1.3.0/WordPress/' . get_bloginfo( 'version' ) . '+WooCommerce/' . $woocommerce->version . '; ' . get_bloginfo( 'url' );
		$this->debug              = filter_var( $this->get_option( 'debug' ), FILTER_VALIDATE_BOOLEAN );
		$this->download_orders    = new WC_Taxjar_Download_Orders( $this );

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->api_token        = $this->get_option( 'api_token' );
		$this->store_zip        = $this->get_option( 'store_zip' );
		$this->store_city       = $this->get_option( 'store_city' );
		$this->enabled          = filter_var( $this->get_option( 'enabled' ), FILTER_VALIDATE_BOOLEAN );

		// Cache rates for 1 hour.
		$this->cache_time = HOUR_IN_SECONDS;

		// Set up form fields.
		$this->init_form_fields();

		// TaxJar Config Integration Tab
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_menu', array( $this, 'taxjar_admin_menu' ),  15 );

		if ( ( 'yes' == $this->settings['enabled'] ) ) {

			// Calculate Taxes
			add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 20 );

			// Settings Page
			add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_before' ),  9 );
			add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_after' ),  11 );

			// Filters
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
			add_filter( 'woocommerce_customer_taxable_address', array( $this, 'append_base_address_to_customer_taxable_address' ), 10, 1 );

			// If TaxJar is enabled and a user disables taxes we renable them
			update_option( 'woocommerce_calc_taxes', 'yes' );

			// Users can set either billing or shipping address for tax rates but not shop
			update_option( 'woocommerce_tax_based_on', 'shipping' );

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
		} // End if().
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	// fix undefined offset for country not set...
	public function init_form_fields() {
		if ( ! $this->on_settings_page() ) {
			return;
		}

		$default_wc_settings = explode( ':', get_option( 'woocommerce_default_country' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_assets' ) );
		$tj_connection = new WC_TaxJar_Connection( $this );

		if ( empty( $default_wc_settings[1] ) ) {
			$default_wc_settings[1] = 'N/A';
		}

		// Build the form array
		$this->form_fields = array(
			'taxjar_title_step_1' => array(
				'title'             => __( '<h3>Step 1:</h3>', 'wc-taxjar' ),
				'type'              => 'hidden',
				'description'       => '<h3>Activate your TaxJar WooCommerce Plugin</h3>',
			),
			'api_token' => array(
				'title'             => __( 'API Token', 'wc-taxjar' ),
				'type'              => 'text',
				'description'       => __( '<a href="' . $this->app_uri . 'account#api-access" target="_blank">Click here</a> to get your API token.', 'wc-taxjar' ),
				'desc_tip'          => false,
				'default'           => '',
			),
		);

		if ( ! $tj_connection->can_connect || ! $tj_connection->api_token_valid ) {
			$this->form_fields = array_merge( $this->form_fields, array(
					'taxjar_status' => $tj_connection->get_form_settings_field(),
				)
			);
		}

		if ( $this->post_or_setting( 'api_token' ) && $tj_connection->api_token_valid ) {
			$this->form_fields = array_merge( $this->form_fields,
				array(
					'taxjar_title_step_2' => array(
						'title'             => __( '<h3>Step 2:</h3>', 'wc-taxjar' ),
						'type'              => 'hidden',
						'description'       => '<h3>Configure your sales tax settings</h3>',
					),
					'enabled' => array(
						'title'             => __( 'Sales Tax Calculation', 'wc-taxjar' ),
						'type'              => 'checkbox',
						'label'             => __( 'Enable TaxJar Calculations', 'wc-taxjar' ),
						'default'           => 'no',
						'description'       => __( 'If enabled, TaxJar will calculate all sales tax for your store.', 'wc-taxjar' ),
					),
				)
			);

			if ( $this->post_or_setting( 'enabled' ) ) {
				$tj_nexus = new WC_Taxjar_Nexus( $this );
				$this->form_fields = array_merge($this->form_fields,
					array(
						'nexus' => $tj_nexus->get_form_settings_field(),
					)
				);
			}

			$this->form_fields = array_merge( $this->form_fields,
				array(
					'taxjar_download' => $this->download_orders->get_form_settings_field(),
					'store_city' => array(
						'title'             => __( 'Ship From City', 'wc-taxjar' ),
						'type'              => 'text',
						'description'       => __( 'Enter the city where your store ships from.', 'wc-taxjar' ),
						'desc_tip'          => true,
						'default'           => '',
					),
					'store_state' => array(
						'title'             => __( 'Ship From State', 'wc-taxjar' ),
						'type'              => 'hidden',
						'description'       => __( 'We have automatically detected your ship from state as being ' . $default_wc_settings[1] . '.<br>You can change this setting at <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">Woo->Settings->General->Base Location</a>', 'wc-taxjar' ),
						'class'             => 'input-text disabled regular-input',
						'disabled'          => 'disabled',
					),
					'store_zip' => array(
						'title'             => __( 'Ship From Zip Code', 'wc-taxjar' ),
						'type'              => 'text',
						'description'       => __( 'Enter the zip code from which your store ships products.', 'wc-taxjar' ),
						'desc_tip'          => true,
						'default'           => '',
					),
					'store_country' => array(
						'title'             => __( 'Ship From Country', 'wc-taxjar' ),
						'type'              => 'hidden',
						'description'       => __( 'We have automatically detected your ship from country as being ' . $default_wc_settings[0] . '.<br>You can change this setting at <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">Woo->Settings->General->Base Location</a>', 'wc-taxjar' ),
						'class'             => 'input-text disabled regular-input',
						'disabled'          => 'disabled',
					),
					'debug' => array(
						'title'             => __( 'Debug Log', 'wc-taxjar' ),
						'type'              => 'checkbox',
						'label'             => __( 'Enable logging', 'wc-taxjar' ),
						'default'           => 'no',
						'description'       => __( 'Log events such as API requests.', 'wc-taxjar' ),
					),
				)
			);
		} // End if().
	}

	/**
	 * Prints information to wp-content/debug.log
	 *
	 * @return void
	 */
	public function _log( $message ) {
		if ( WP_DEBUG === true ) {
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
		}
		if ( $this->debug ) {
			$this->log = new WC_Logger();
			if ( is_array( $message ) || is_object( $message ) ) {
				$this->log->add( 'taxjar', print_r( $message, true ) );
			} else {
				$this->log->add( 'taxjar', $message );
			}
		}
	}

	/**
	 * Slightly altered copy and paste of private function in WC_Tax class file
	 */
	public function _get_wildcard_postcodes( $postcode ) {
		global $woocommerce;

		$postcodes = array( '*', strtoupper( $postcode ) );
		if ( version_compare( $woocommerce->version, '2.4.0', '>=' ) ) {
			$postcodes = array( '*', strtoupper( $postcode ), strtoupper( $postcode ) . '*' );
		}

		$postcode_length   = strlen( $postcode );
		$wildcard_postcode = strtoupper( $postcode );

		for ( $i = 0; $i < $postcode_length; $i ++ ) {
			$wildcard_postcode = substr( $wildcard_postcode, 0, -1 );
			$postcodes[] = $wildcard_postcode . '*';
		}
		return $postcodes;
	}

	/**
	 * Calculate sales tax using SmartCalcs
	 *
	 * @return void
	 */
	public function calculate_tax( $options = array() ) {
		global $woocommerce;

		$this->_log( ':::: TaxJar Plugin requested ::::' );

		// Process $options array and turn them into variables
		$options = is_array( $options ) ? $options : array();

		extract( array_replace_recursive(array(
			'to_country' => null,
			'to_state' => null,
			'to_zip' => null,
			'to_city' => null,
			'shipping_amount' => null, // $woocommerce->shipping->shipping_total
			'line_items' => null
		), $options) );

		// Initalize some variables & properties
		$store_settings           = $this->get_store_settings();
		$customer                 = $woocommerce->customer;

		$this->tax_rate             = 0;
		$this->amount_to_collect    = 0;
		$this->item_collectable     = 0;
		$this->shipping_collectable = 0;
		$this->freight_taxable      = 1;
		$this->line_items           = array();
		$this->has_nexus            = 0;
		$this->tax_source           = 'origin';
		$this->rate_id              = null;

		// Strict conditions to be met before API call can be conducted
		if ( empty( $to_country ) || empty( $to_zip ) || $customer->is_vat_exempt() ) {
			return false;
		}

		$taxjar_nexus = new WC_Taxjar_Nexus( $this );

		if ( ! $taxjar_nexus->has_nexus_check( $to_country, $to_state ) ) {
			$this->_log( ':::: Order not shipping to nexus area ::::' );
			return false;
		}

		// Setup Vars for API call
		$to_zip           = explode( ',' , $to_zip );
		$to_zip           = array_shift( $to_zip );

		$from_country     = $store_settings['store_country_setting'];
		$from_state       = $store_settings['store_state_setting'];
		$from_zip         = $store_settings['taxjar_zip_code_setting'];
		$from_city        = $store_settings['taxjar_city_setting'];
		$shipping_amount  = is_null( $shipping_amount ) ? 0.0 : $shipping_amount;

		$this->_log( ':::: TaxJar API called ::::' );

		$url = $this->uri . 'taxes';
		$body = array(
			'from_country' => $from_country,
			'from_state' => $from_state,
			'from_city' => $from_city,
			'from_zip' => $from_zip,
			'to_country' => $to_country,
			'to_state' => $to_state,
			'to_city' => $to_city,
			'to_zip' => $to_zip,
			'shipping' => $shipping_amount,
			'line_items' => $line_items,
			'plugin' => 'woo',
		);

		$response = $this->smartcalcs_cache_request( wp_json_encode( $body ) );

		if ( isset( $response ) ) {
			// Log the response
			$this->_log( 'Received: ' . $response['body'] );

			// Decode Response
			$taxjar_response          = json_decode( $response['body'] );
			$taxjar_response          = $taxjar_response->tax;

			// Update Properties based on Response
			$this->has_nexus          = (int) $taxjar_response->has_nexus;
			$this->tax_source         = empty( $taxjar_response->tax_source ) ? 'origin' : $taxjar_response->tax_source;
			$this->amount_to_collect  = $taxjar_response->amount_to_collect;

			if ( ! empty( $taxjar_response->breakdown ) ) {
				if ( ! empty( $taxjar_response->breakdown->shipping ) ) {
					$this->shipping_collectable = $taxjar_response->breakdown->shipping->tax_collectable;
				}

				if ( ! empty( $taxjar_response->breakdown->line_items ) ) {
					$line_items = array();
					foreach ( $taxjar_response->breakdown->line_items as $line_item ) {
						$line_items[ $line_item->id ] = $line_item;
					}
					$this->line_items = $line_items;
				}
			}

			$this->item_collectable   = $this->amount_to_collect - $this->shipping_collectable;
			$this->tax_rate           = $taxjar_response->rate;
			$this->freight_taxable    = (int) $taxjar_response->freight_taxable;
		}

		// Remove taxes if they are set somehow and customer is exempt
		if ( $customer->is_vat_exempt() ) {
			$wc_cart_object->remove_taxes();
		} elseif ( $this->has_nexus ) {
			// Use Woo core to find matching rates for taxable address
			$source_zip = 'destination' == $this->tax_source  ? $to_zip : $from_zip;
			$source_city = 'destination' == $this->tax_source ? $to_city : $from_city;

			if ( strtoupper( $to_city ) == strtoupper( $from_city ) ) {
				$source_city = $to_city;
			}

			// Setup Tax Rates
			$tax_rates = array(
				'tax_rate_country' => $to_country,
				'tax_rate_state' => $to_state,
				'tax_rate_name' => sprintf( "%s Tax", $to_state ),
				'tax_rate_priority' => 1,
				'tax_rate_compound' => false,
				'tax_rate_shipping' => $this->freight_taxable,
				'tax_rate' => $this->tax_rate * 100,
				'tax_rate_class' => '',
			);

			$wc_rates = WC_Tax::find_rates( array(
				'country' => $to_country,
				'state' => $to_state,
				'postcode' => $source_zip,
				'city' => $source_city,
				'tax_class' => '',
			) );

			// If we have rates, use those, but if no rates returned create one to link with, or use the first rate returned.
			if ( ! empty( $wc_rates ) ) {
				$this->_log( '::: TAX RATES FOUND :::' );
				$this->_log( $wc_rates );

				// Get the existing ID
				$rate_id = key( $wc_rates );

				// Update Tax Rates with TaxJar rates ( rates might be coming from a cached taxjar rate )
				$this->_log( ':: UPDATING TAX RATES TO ::' );
				$this->_log( $tax_rates );

				WC_TAX::_update_tax_rate( $rate_id, $tax_rates );
			} else {
				// Insert a rate if we did not find one
				$this->_log( ':: Adding New Tax Rate ::' );
				$rate_id = WC_Tax::_insert_tax_rate( $tax_rates );
				WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $source_zip ) );
				WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $source_city ) );
			}

			$this->_log( 'Tax Rate ID Set: ' . $rate_id );
			$this->rate_id = $rate_id;
		} // End if().
	} // End calculate_tax().

	public function smartcalcs_request( $json ) {
		$url = $this->uri . 'taxes';

		$this->_log( 'Requesting: ' . $this->uri . 'taxes - ' . $json );

		$response = wp_remote_post( $url, array(
			'headers' => array(
							'Authorization' => 'Token token="' . $this->settings['api_token'] . '"',
							'Content-Type' => 'application/json',
						),
			'user-agent' => $this->ua,
			'body' => $json,
		) );

		if ( is_wp_error( $response ) ) {
			new WP_Error( 'request', __( 'There was an error retrieving the tax rates. Please check your server configuration.' ) );
		} elseif ( 200 == $response['response']['code'] ) {
			return $response;
		} else {
			$this->_log( 'Received (' . $response['response']['code'] . '): ' . $response['body'] );
		}
	}

	public function smartcalcs_cache_request( $json ) {
		return tlc_transient( __FUNCTION__ . hash( 'md5', $json ) )
				->updates_with( array( $this, 'smartcalcs_request' ), array( $json ) )
				->expires_in( $this->cache_time )
				->get();
	}

	/**
	 * Ensure use of the TaxJar amount_to_collect and API data
	 *
	 * @return void
	 */
	public function calculate_totals( $wc_cart_object ) {
		global $woocommerce;

		// Get all of the required customer params
		$taxable_address = $woocommerce->customer->get_taxable_address(); // returns unassociated array
		$taxable_address = is_array( $taxable_address ) ? $taxable_address : array();

		$to_country = isset( $taxable_address[0] ) && ! empty( $taxable_address[0] ) ? $taxable_address[0] : false;
		$to_state = isset( $taxable_address[1] ) && ! empty( $taxable_address[1] ) ? $taxable_address[1] : false;
		$to_zip = isset( $taxable_address[2] ) && ! empty( $taxable_address[2] ) ? $taxable_address[2] : false;
		$to_city = isset( $taxable_address[3] ) && ! empty( $taxable_address[3] ) ? $taxable_address[3] : false;
		$line_items = array();

		foreach ( $wc_cart_object->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			$id = $product->get_id();
			$quantity = $cart_item['quantity'];
			$unit_price = $product->get_price();
			$discount = ( $unit_price - $wc_cart_object->get_discounted_price( $cart_item, $unit_price ) ) * $quantity;
			$tax_class = explode( '-', $product->get_tax_class() );
			$tax_code = '';

			if ( ! $product->is_taxable() ) {
				$tax_code = '99999';
			}

			if ( isset( $tax_class[1] ) && is_numeric( $tax_class[1] ) ) {
				$tax_code = $tax_class[1];
			}

			if ( $unit_price ) {
				array_push($line_items, array(
					'id' => $id,
					'quantity' => $quantity,
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
				));
			}
		}

		$this->calculate_tax( array(
			'to_city' => $to_city,
			'to_state' => $to_state,
			'to_country' => $to_country,
			'to_zip' => $to_zip,
			'shipping_amount' => $woocommerce->shipping->shipping_total,
			'line_items' => $line_items,
			'customer' => $woocommerce->customer,
		) );

		// Store the rate ID and the amount on the cart's totals
		$wc_cart_object->tax_total = $this->item_collectable;
		$wc_cart_object->shipping_tax_total = $this->shipping_collectable;
		$wc_cart_object->taxes = array(
			$this->rate_id => $this->item_collectable,
		);
		$wc_cart_object->shipping_taxes = array(
			$this->rate_id => $this->shipping_collectable,
		);

		foreach ( $wc_cart_object->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			if ( isset( $this->line_items[ $product->get_id() ] ) ) {
				$wc_cart_object->cart_contents[ $cart_item_key ]['line_tax'] = $this->line_items[ $product->get_id() ]->tax_collectable;
			}
		}
	}

	/**
	 * Set customer zip code and state to store if local shipping option set
	 *
	 * @return array
	 */
	public function append_base_address_to_customer_taxable_address( $address ) {
		$store_settings = $this->get_store_settings();
		$tax_based_on = '';

		list( $country, $state, $postcode, $city ) = $address;

		// See WC_Customer get_taxable_address()
		if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( wc_get_chosen_shipping_method_ids(), apply_filters( 'woocommerce_local_pickup_methods', array( 'local_pickup' ) ) ) ) > 0 ) {
			$tax_based_on = 'base';
		}

		if ( 'base' == $tax_based_on ) {
			$postcode  = $store_settings['taxjar_zip_code_setting'];
			$city = strtoupper( $store_settings['taxjar_city_setting'] );
		}

		return array( $country, $state, $postcode, $city );
	}

	/**
	 * Return either the post value or settings value of a key
	 *
	 * @return MIXED
	 */
	public function post_or_setting( $key ) {
		$val = null;

		if ( count( $_POST ) > 0 ) {
			$val = isset( $_POST[ 'woocommerce_taxjar-integration_' . $key ] ) ? $_POST[ 'woocommerce_taxjar-integration_' . $key ] : null;
		} else {
			$val = $this->settings[ $key ];
		}

		if ( 'yes' == $val ) {
			$val = 1;
		}

		if ( 'no' == $val ) {
			$val = 0;
		}

		return $val;
	}

	/**
	 * Check if there is an existing WooCommerce 2.4 API Key
	 *
	 * @return boolean
	 */
	private function existing_api_key() {
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
	 * Validate the option to enable TaxJar order downloads
	 * @see validate_settings_fields()
	 */
	public function validate_taxjar_download_field( $key ) {
		// Validate the value and perform work for taxjar_download option
		return $value = $this->download_orders->validate_taxjar_download_field( $key );
	}

	/**
	 * Validate the API token
	 * @see validate_settings_fields()
	 */
	public function validate_api_token_field( $key ) {
		$value = $this->get_value_from_post( $key );
		if ( ! $value && '' == $value && $this->download_orders->taxjar_download ) {
			$this->download_orders->unlink_provider( site_url() );
		}

		return $value;
	}

	/**
	 * Gets the store's settings and returns them
	 *
	 * @return array
	 */
	public function get_store_settings() {
		$default_wc_settings     = explode( ':', get_option( 'woocommerce_default_country' ) );
		$taxjar_zip_code_setting = $this->settings['store_zip'];
		$taxjar_city_setting     = $this->settings['store_city'];
		$store_settings          = array(
			'taxjar_zip_code_setting' => $taxjar_zip_code_setting,
			'store_state_setting' => null,
			'store_country_setting' => $default_wc_settings[0],
			'taxjar_city_setting' => $taxjar_city_setting,
		);
		if ( isset( $default_wc_settings[1] ) ) {
			$store_settings['store_state_setting'] = $default_wc_settings[1];
		}
		return $store_settings;
	}

	/**
	 * Gets the store's settings and returns them
	 *
	 * @return void
	 */
	public function taxjar_admin_menu() {
		// Simple shortcut menu item under WooCommerce
		add_submenu_page( 'woocommerce', __( 'TaxJar Settings', 'woocommerce' ), __( 'TaxJar', 'woocommerce' ) , 'manage_woocommerce', 'admin.php?page=wc-settings&tab=integration&section=taxjar-integration' );
	}

	/**
	 * Gets the value for a seting from POST given a key or returns false if box not checked
	 *
	 * @param mixed $key
	 * @return mixed $value
	 */
	public function get_value_from_post( $key ) {
		if ( isset( $_POST[ $this->plugin_id . $this->id . '_' . $key ] ) ) {
			return $_POST[ $this->plugin_id . $this->id . '_' . $key ];
		} else {
			return false;
		}
	}

	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors() {
		$error_key_values = array(
			'shop_not_linked' => 'There was an error linking this store to your TaxJar account. Please contact support@taxjar.com',
		);

		foreach ( $this->errors as $key => $value ) {
			$message = $error_key_values[ $value ];
			echo "<div class=\"error\"><p>$message</p></div>";
		}
	}

	/**
	 * Hack to hide the tax sections for additional tax class rate tables.
	 *
	 */
	public function output_sections_before() {
		echo '<div class="taxjar"><h3>Tax Rates Powered by <a href="http://www.taxjar.com" target="_blank">TaxJar</a>. <a href="admin.php?page=wc-settings&tab=integration">Configure TaxJar</a></h3></div>';
		echo '<div style="display:none;">';
	}

	/**
	 * Hack to hide the tax sections for additional tax class rate tables.
	 *
	 */
	public function output_sections_after() {
		echo '</div>';
	}

	/**
	 * Checks if currently on the TaxJar settings page
	 *
	 * @return boolean
	 */
	public function on_settings_page() {
		return ( isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] && isset( $_GET['tab'] ) && 'integration' == $_GET['tab'] );
	}

	/**
	 * Admin Assets
	 */
	public function load_taxjar_admin_assets() {
		// Add CSS that hides some elements that are known to cause problems
		wp_enqueue_style( 'taxjar-admin-style', plugin_dir_url( __FILE__ ) . 'css/admin.css' );

		// Load Javascript for TaxJar settings page
		wp_register_script( 'wc-taxjar-admin', plugin_dir_url( __FILE__ ) . '/js/wc-taxjar-admin.js' );

		wp_localize_script(
			'wc-taxjar-admin',
			'woocommerce_taxjar_admin',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'update_api_nonce' => wp_create_nonce( 'update-api-key' ),
				'current_user'     => get_current_user_id(),
				'integration_uri'  => $this->integration_uri,
				'api_token'        => $this->post_or_setting( 'api_token' ),
			)
		);

		wp_enqueue_script( 'wc-taxjar-admin' , array( 'jquery' ) );
	}

}

endif;
