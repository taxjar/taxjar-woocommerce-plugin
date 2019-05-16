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
		$this->id                 = 'taxjar-integration';
		$this->method_title       = __( 'TaxJar', 'wc-taxjar' );
		$this->method_description = apply_filters( 'taxjar_method_description', __( 'TaxJar is the easiest to use sales tax calculation and reporting engine for WooCommerce. Enter your API token (<a href="https://app.taxjar.com/api_sign_up/" target="_blank">click here to get a token</a>), city, and zip code from which your store ships. Enable TaxJar calculations to automatically collect sales tax at checkout. You may also enable order downloads to begin importing transactions from this store into your TaxJar account, all in one click!<br><br><b>For the fastest help, please email <a href="mailto:support@taxjar.com">support@taxjar.com</a>. We\'ll get back to you within hours.</b>', 'wc-taxjar' ) );
		$this->app_uri            = 'https://app.taxjar.com/';
		$this->integration_uri    = $this->app_uri . 'account/apps/add/woo';
		$this->regions_uri        = $this->app_uri . 'account#states';
		$this->uri                = 'https://api.taxjar.com/v2/';
		$this->ua                 = 'TaxJarWordPressPlugin/2.3.0/WordPress/' . get_bloginfo( 'version' ) . '+WooCommerce/' . WC()->version . '; ' . get_bloginfo( 'url' );
		$this->debug              = filter_var( $this->get_option( 'debug' ), FILTER_VALIDATE_BOOLEAN );
		$this->download_orders    = new WC_Taxjar_Download_Orders( $this );

		// Load the settings.
		$this->init_settings();

		// Cache rates for 1 hour.
		$this->cache_time = HOUR_IN_SECONDS;

		// Set up form fields.
		$this->init_form_fields();

		// TaxJar Config Integration Tab
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_menu', array( $this, 'taxjar_admin_menu' ),  15 );

		if ( apply_filters( 'taxjar_enabled', isset( $this->settings['enabled'] ) && 'yes' == $this->settings['enabled'] ) ) {
			// Calculate Taxes at Cart / Checkout
			if ( class_exists( 'WC_Cart_Totals' ) ) { // Woo 3.2+
				add_action( 'woocommerce_after_calculate_totals', array( $this, 'calculate_totals' ), 20 );
			} else {
				add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 20 );
			}

			// Calculate Taxes for Backend Orders (Woo 2.6+)
			add_action( 'woocommerce_before_save_order_items', array( $this, 'calculate_backend_totals' ), 20 );

			// Calculate taxes for WooCommerce Subscriptions renewal orders
            add_filter( 'wcs_new_order_created', array( $this, 'calculate_renewal_order_totals' ), 10, 3 );

			// Settings Page
			add_action( 'woocommerce_sections_tax',  array( $this, 'output_sections_before' ),  9 );

			// Filters
			add_filter( 'woocommerce_calc_tax', array( $this, 'override_woocommerce_tax_rates' ), 10, 3 );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
			add_filter( 'woocommerce_customer_taxable_address', array( $this, 'append_base_address_to_customer_taxable_address' ), 10, 1 );
			add_filter( 'woocommerce_matched_rates', array( $this, 'allow_street_address_for_matched_rates' ), 10, 2 );

			// Scripts / Stylesheets
			add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_new_order_assets' ) );

			// If TaxJar is enabled and user disables taxes we re-enable them
			update_option( 'woocommerce_calc_taxes', 'yes' );

			// Users can set either billing or shipping address for tax rates but not shop
			update_option( 'woocommerce_tax_based_on', 'shipping' );

			// Rate calculations assume tax not included
			update_option( 'woocommerce_prices_include_tax', 'no' );

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
	public function init_form_fields() {
		if ( ! $this->on_settings_page() ) {
			return;
		}

		$store_settings = $this->get_store_settings();
		$tj_connection = new WC_TaxJar_Connection( $this );

		add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_assets' ) );

		if ( empty( $store_settings['state'] ) ) {
			$store_settings['state'] = 'N/A';
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

		$api_token_valid = apply_filters( 'taxjar_api_token_valid', $this->post_or_setting( 'api_token' ) && $tj_connection->api_token_valid );
		if ( $api_token_valid ) {
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

			if ( get_option( 'woocommerce_store_address' ) || get_option( 'woocommerce_store_city' ) || get_option( 'woocommerce_store_postcode' ) ) {
				$store_address_fields = array(
					'store_address' => array(
						'title'             => __( 'Ship From Address', 'wc-taxjar' ),
						'type'              => 'hidden',
						'description'       => __( 'We have automatically detected your ship from address:<br><br>' . $store_settings['street'] . '<br>' . $store_settings['city'] . ', ' . $store_settings['state'] . ' ' . $store_settings['postcode'] . '<br>' . WC()->countries->countries[ $store_settings['country'] ] . '<br><br>You can change this setting at:<br><a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Store Address</a>', 'wc-taxjar' ),
						'class'             => 'input-text disabled regular-input',
						'disabled'          => 'disabled',
					),
				);
			} else {
				$store_address_fields = array(
					'store_street' => array(
						'title'             => __( 'Ship From Address', 'wc-taxjar' ),
						'type'              => 'text',
						'description'       => __( 'Enter the street address where your store ships from.', 'wc-taxjar' ),
						'desc_tip'          => true,
						'default'           => '',
					),
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
						'description'       => __( 'We have automatically detected your ship from state as being ' . $store_settings['state'] . '.<br>You can change this setting at <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>', 'wc-taxjar' ),
						'class'             => 'input-text disabled regular-input',
						'disabled'          => 'disabled',
					),
					'store_postcode' => array(
						'title'             => __( 'Ship From Postcode / ZIP', 'wc-taxjar' ),
						'type'              => 'text',
						'description'       => __( 'Enter the zip code from which your store ships products.', 'wc-taxjar' ),
						'desc_tip'          => true,
						'default'           => '',
					),
					'store_country' => array(
						'title'             => __( 'Ship From Country', 'wc-taxjar' ),
						'type'              => 'hidden',
						'description'       => __( 'We have automatically detected your ship from country as being ' . $store_settings['country'] . '.<br>You can change this setting at <a href="' . get_admin_url( null, 'admin.php?page=wc-settings' ) . '">WooCommerce -> Settings -> General -> Base Location</a>', 'wc-taxjar' ),
						'class'             => 'input-text disabled regular-input',
						'disabled'          => 'disabled',
					),
				);
			}

			$this->form_fields = array_merge( $this->form_fields,
				array(
					'taxjar_download' => $this->download_orders->get_form_settings_field(),
				),
				$store_address_fields,
				array(
					'debug' => array(
						'title'             => __( 'Debug Log', 'wc-taxjar' ),
						'type'              => 'checkbox',
						'label'             => __( 'Enable logging', 'wc-taxjar' ),
						'default'           => 'no',
						'description'       => __( 'Log events such as API requests.', 'wc-taxjar' ),
					),
					'rate' => array(
						'type'              => 'title',
						'description'       => __( 'If you find TaxJar for WooCommerce useful, please rate us <a href="https://wordpress.org/support/plugin/taxjar-simplified-taxes-for-woocommerce/reviews/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>. Thank you!', 'wc-taxjar' ),
					),
				)
			);
		} // End if().

		$this->form_fields = apply_filters( 'taxjar_form_fields', $this->form_fields );
	}

	/**
	 * Prints debug info to wp-content/uploads/wc-logs/taxjar-*.log
	 *
	 * @return void
	 */
	public function _log( $message ) {
		do_action( 'taxjar_log', $message );
		if ( $this->debug ) {
			if ( ! isset( $this->log ) ) {
			    $this->log = new WC_Logger();
			}
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
		$postcodes = array( '*', strtoupper( $postcode ) );

		if ( version_compare( WC()->version, '2.4.0', '>=' ) ) {
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
		$this->_log( ':::: TaxJar Plugin requested ::::' );

		// Process $options array and turn them into variables
		$options = is_array( $options ) ? $options : array();

		extract( array_replace_recursive(array(
			'to_country' => null,
			'to_state' => null,
			'to_zip' => null,
			'to_city' => null,
			'to_street' => null,
			'shipping_amount' => null, // WC()->shipping->shipping_total
			'line_items' => null,
		), $options) );

		$taxes = array(
			'freight_taxable' => 1,
			'has_nexus' => 0,
			'line_items' => array(),
			'rate_ids' => array(),
			'tax_rate' => 0,
		);

		// Strict conditions to be met before API call can be conducted
		if (
			empty( $to_country ) ||
			empty( $to_zip ) ||
			( empty( $line_items ) && ( 0 == $shipping_amount ) )
		) {
			return false;
		}

		// validate customer exemption before sending API call
		if ( is_object( WC()->customer ) ) {
		    if ( WC()->customer->is_vat_exempt() ) {
		        return false;
            }
        }

		// Valid zip codes to prevent unnecessary API requests
        if ( ! $this->is_postal_code_valid( $to_country, $to_state, $to_zip ) ) {
            return false;
        }

		$taxjar_nexus = new WC_Taxjar_Nexus( $this );

		if ( ! $taxjar_nexus->has_nexus_check( $to_country, $to_state ) ) {
			$this->_log( ':::: Order not shipping to nexus area ::::' );
			return false;
		}

		$to_zip           = explode( ',' , $to_zip );
		$to_zip           = array_shift( $to_zip );

		$store_settings   = $this->get_store_settings();
		$from_country     = $store_settings['country'];
		$from_state       = $store_settings['state'];
		$from_zip         = $store_settings['postcode'];
		$from_city        = $store_settings['city'];
		$from_street      = $store_settings['street'];
		$shipping_amount  = is_null( $shipping_amount ) ? 0.0 : $shipping_amount;

		$this->_log( ':::: TaxJar API called ::::' );

		$url = $this->uri . 'taxes';

		$body = array(
			'from_country' => $from_country,
			'from_state' => $from_state,
			'from_zip' => $from_zip,
			'from_city' => $from_city,
			'from_street' => $from_street,
			'to_country' => $to_country,
			'to_state' => $to_state,
			'to_zip' => $to_zip,
			'to_city' => $to_city,
			'to_street' => $to_street,
			'shipping' => $shipping_amount,
			'plugin' => 'woo',
		);

		// Either `amount` or `line_items` parameters are required to perform tax calculations.
		if ( empty( $line_items ) ) {
			$body['amount'] = 0.0;
		} else {
			$body['line_items'] = $line_items;
		}

		$response = $this->smartcalcs_cache_request( wp_json_encode( $body ) );

		if ( isset( $response ) ) {
			// Log the response
			$this->_log( 'Received: ' . $response['body'] );

			// Decode Response
			$taxjar_response          = json_decode( $response['body'] );
			$taxjar_response          = $taxjar_response->tax;

			// Update Properties based on Response
			$taxes['freight_taxable']    = (int) $taxjar_response->freight_taxable;
			$taxes['has_nexus']          = (int) $taxjar_response->has_nexus;
			$taxes['tax_rate']           = $taxjar_response->rate;

			if ( ! empty( $taxjar_response->breakdown ) ) {
				if ( ! empty( $taxjar_response->breakdown->line_items ) ) {
					$line_items = array();
					foreach ( $taxjar_response->breakdown->line_items as $line_item ) {
						$line_items[ $line_item->id ] = $line_item;
					}
					$taxes['line_items'] = $line_items;
				}
			}
		}

		// Remove taxes if they are set somehow and customer is exempt
		if ( is_object( WC()->customer ) && WC()->customer->is_vat_exempt() ) {
			WC()->cart->remove_taxes(); // Woo < 3.2
		} elseif ( $taxes['has_nexus'] ) {
			// Use Woo core to find matching rates for taxable address
			$location = array(
				'to_country' => $to_country,
				'to_state' => $to_state,
				'to_zip' => $to_zip,
				'to_city' => $to_city,
			);

			// Add line item tax rates
			foreach ( $taxes['line_items'] as $line_item_key => $line_item ) {
				$line_item_key_chunks = explode( '-', $line_item_key );
				$product_id = $line_item_key_chunks[0];
				$product = wc_get_product( $product_id );

				if ( $product ) {
					$tax_class = $product->get_tax_class();
				} else {
					if ( isset( $this->backend_tax_classes[$product_id] ) ) {
						$tax_class = $this->backend_tax_classes[$product_id];
					}
				}

				if ( $line_item->combined_tax_rate ) {
					$taxes['rate_ids'][ $line_item_key ] = $this->create_or_update_tax_rate(
						$location,
						$line_item->combined_tax_rate * 100,
						$tax_class,
						$taxes['freight_taxable']
					);
				}
			}

			// Add shipping tax rate
			$taxes['rate_ids']['shipping'] = $this->create_or_update_tax_rate(
				$location,
				$taxes['tax_rate'] * 100,
				'',
				$taxes['freight_taxable']
			);
		} // End if().

		return $taxes;
	} // End calculate_tax().

	/**
	 * Add or update a native WooCommerce tax rate
	 *
	 * @return void
	 */
	public function create_or_update_tax_rate( $location, $rate, $tax_class = '', $freight_taxable = 1 ) {
		$tax_rate = array(
			'tax_rate_country' => $location['to_country'],
			'tax_rate_state' => $location['to_state'],
			'tax_rate_name' => sprintf( "%s Tax", $location['to_state'] ),
			'tax_rate_priority' => 1,
			'tax_rate_compound' => false,
			'tax_rate_shipping' => $freight_taxable,
			'tax_rate' => $rate,
			'tax_rate_class' => $tax_class,
		);

		$wc_rate = WC_Tax::find_rates( array(
			'country' => $location['to_country'],
			'state' => $location['to_state'],
			'postcode' => $location['to_zip'],
			'city' => $location['to_city'],
			'tax_class' => $tax_class,
		) );

		if ( ! empty( $wc_rate ) ) {
			$this->_log( ':: Tax Rate Found ::' );
			$this->_log( $wc_rate );

			// Get the existing ID
			$rate_id = key( $wc_rate );

			// Update Tax Rates with TaxJar rates ( rates might be coming from a cached taxjar rate )
			$this->_log( ':: Updating Tax Rate To ::' );
			$this->_log( $tax_rate );

			WC_Tax::_update_tax_rate( $rate_id, $tax_rate );
		} else {
			// Insert a rate if we did not find one
			$this->_log( ':: Adding New Tax Rate ::' );
			$this->_log( $tax_rate );
			$rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
			WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $location['to_zip'] ) );
			WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $location['to_city'] ) );
		}

		$this->_log( 'Tax Rate ID Set for ' . $rate_id );
		return $rate_id;
	}

	public function smartcalcs_request( $json ) {
		$response = apply_filters( 'taxjar_smartcalcs_request', false, $json );
		if ( ! $response ) {
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
		}

		if ( is_wp_error( $response ) ) {
			new WP_Error( 'request', __( 'There was an error retrieving the tax rates. Please check your server configuration.' ) );
		} elseif ( 200 == $response['response']['code'] ) {
			return $response;
		} else {
			$this->_log( 'Received (' . $response['response']['code'] . '): ' . $response['body'] );
		}
	}

	public function smartcalcs_cache_request( $json ) {
		$cache_key = 'tj_tax_' . hash( 'md5', $json );
		$response  = get_transient( $cache_key );

		if ( false === $response ) {
			$response = $this->smartcalcs_request( $json );

			if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
				set_transient( $cache_key, $response, $this->cache_time );
			}
		}

		return $response;
	}

	/**
	 * Calculate tax / totals using TaxJar at checkout
	 *
	 * @return void
	 */
	public function calculate_totals( $wc_cart_object ) {
		// If outside of cart and checkout page or within mini-cart, skip calculations
		if ( ( ! is_cart() && ! is_checkout() ) || ( is_cart() && is_ajax() ) ) {
			return;
		}

		// prevent unnecessary calls to API during add to cart process
        if ( doing_action( 'woocommerce_add_to_cart' ) ) {
            return;
        }

		$cart_taxes = array();
		$cart_tax_total = 0;

		foreach ( $wc_cart_object->coupons as $coupon ) {
			if ( method_exists( $coupon, 'get_id' ) ) { // Woo 3.0+
				$limit_usage_qty = get_post_meta( $coupon->get_id(), 'limit_usage_to_x_items', true );

				if ( $limit_usage_qty ) {
					$coupon->set_limit_usage_to_x_items( $limit_usage_qty );
				}
			}
		}

		$address = $this->get_address( $wc_cart_object );
		$line_items = $this->get_line_items( $wc_cart_object );

		$taxes = $this->calculate_tax( array(
			'to_country' => $address['to_country'],
			'to_zip' => $address['to_zip'],
			'to_state' => $address['to_state'],
			'to_city' => $address['to_city'],
			'to_street' => $address['to_street'],
			'shipping_amount' => WC()->shipping->shipping_total,
			'line_items' => $line_items,
		) );

		$this->response_rate_ids = $taxes['rate_ids'];
		$this->response_line_items = $taxes['line_items'];

		if ( isset( $this->response_line_items ) ) {
			foreach ( $this->response_line_items as $response_line_item_key => $response_line_item ) {
				$line_item = $this->get_line_item( $response_line_item_key, $line_items );

				if ( isset( $line_item ) ) {
					$this->response_line_items[ $response_line_item_key ]->line_total = ( $line_item['unit_price'] * $line_item['quantity'] ) - $line_item['discount'];
				}
			}
		}

		foreach ( $wc_cart_object->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			$line_item_key = $product->get_id() . '-' . $cart_item_key;
			if ( isset( $taxes['line_items'][ $line_item_key ] ) && ! $taxes['line_items'][ $line_item_key ]->combined_tax_rate ) {
				if ( method_exists( $product, 'set_tax_status' ) ) {
					$product->set_tax_status( 'none' ); // Woo 3.0+
				} else {
					$product->tax_status = 'none'; // Woo 2.6
				}
			}
		}

		// ensure fully exempt orders have no tax on shipping
		if ( ! $taxes[ 'freight_taxable' ] ) {
			foreach ( $wc_cart_object->get_shipping_packages() as $package_key => $package ) {
				$shipping_for_package = WC()->session->get( 'shipping_for_package_' . $package_key );
				if ( ! empty( $shipping_for_package['rates'] ) ) {
					foreach ( $shipping_for_package['rates'] as $shipping_rate ) {
						if ( method_exists( $shipping_rate, 'set_taxes' ) ) {
							$shipping_rate->set_taxes( array() );
						} else {
							$shipping_rate->taxes = array();
						}
						WC()->session->set( 'shipping_for_package_' . $package_key, $shipping_for_package );
					}
				}
			}
		}

		if ( class_exists( 'WC_Cart_Totals' ) ) { // Woo 3.2+
			do_action( 'woocommerce_cart_reset', $wc_cart_object, false );
			do_action( 'woocommerce_before_calculate_totals', $wc_cart_object );

			// Prevent WooCommerce Smart Coupons from removing the applied gift card amount when calculating totals the second time
			if ( WC()->cart->smart_coupon_credit_used ) {
                WC()->cart->smart_coupon_credit_used = array();
            }

			new WC_Cart_Totals( $wc_cart_object );
			remove_action( 'woocommerce_after_calculate_totals', array( $this, 'calculate_totals' ), 20 );
			do_action( 'woocommerce_after_calculate_totals', $wc_cart_object );
			add_action( 'woocommerce_after_calculate_totals', array( $this, 'calculate_totals' ), 20 );
		} else {
			remove_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 20 );
			$wc_cart_object->calculate_totals();
			add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ), 20 );
		}
	}

	/**
	 * Calculate tax / totals using TaxJar for backend orders
	 *
	 * @return void
	 */
	public function calculate_backend_totals( $order_id ) {
		$order = wc_get_order( $order_id );
		$address = $this->get_backend_address();
		$line_items = $this->get_backend_line_items( $order );

		if ( method_exists( $order, 'get_shipping_total' ) ) {
			$shipping = $order->get_shipping_total(); // Woo 3.0+
		} else {
			$shipping = $order->get_total_shipping(); // Woo 2.6
		}

		$taxes = $this->calculate_tax( array(
			'to_country' => $address['to_country'],
			'to_state' => $address['to_state'],
			'to_zip' => $address['to_zip'],
			'to_city' => $address['to_city'],
			'to_street' => $address['to_street'],
			'shipping_amount' => $shipping,
			'line_items' => $line_items,
		) );

		if ( class_exists( 'WC_Order_Item_Tax' ) ) { // Add tax rates manually for Woo 3.0+
			foreach ( $order->get_items() as $item_key => $item ) {
				$product_id = $item->get_product_id();
				$line_item_key = $product_id . '-' . $item_key;

				if ( isset( $taxes['rate_ids'][ $line_item_key ] ) ) {
					$rate_id = $taxes['rate_ids'][ $line_item_key ];
					$item_tax = new WC_Order_Item_Tax();
					$item_tax->set_rate( $rate_id );
					$item_tax->set_order_id( $order_id );
					$item_tax->save();
				}
			}
		} else { // Recalculate tax for Woo 2.6 to apply new tax rates
			if ( class_exists( 'WC_AJAX' ) ) {
				remove_action( 'woocommerce_before_save_order_items', array( $this, 'calculate_backend_totals' ), 20 );
				if ( check_ajax_referer( 'calc-totals', 'security', false ) ) {
					WC_AJAX::calc_line_taxes();
				}
				add_action( 'woocommerce_before_save_order_items', array( $this, 'calculate_backend_totals' ), 20 );
			}
		}
	}

	/**
	 * Triggers tax calculation on both renewal order and subscription when creating a new renewal order
	 *
	 * @return WC_Order
	 */
	public function calculate_renewal_order_totals( $order, $subscription, $type ) {

		if ( ! is_object( $subscription ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}

		// Ensure payment gateway allows order totals to be changed
		if ( ! $subscription->payment_method_supports( 'subscription_amount_changes' ) ) {
		    return $order;
		}

		$this->calculate_order_tax( $order );

		// must calculate tax on subscription in order for my account to properly display the correct tax
		$this->calculate_order_tax( $subscription );

		$order->calculate_totals();
		$subscription->calculate_totals();

	    return $order;
    }

	/**
	 * Calculate tax on an order
	 *
	 * @return null
	 */
    public function calculate_order_tax( $order ) {
	    $address = $this->get_address_from_order( $order );
	    $line_items = $this->get_backend_line_items( $order );

	    if ( method_exists( $order, 'get_shipping_total' ) ) {
		    $shipping = $order->get_shipping_total(); // Woo 3.0+
	    } else {
		    $shipping = $order->get_total_shipping(); // Woo 2.6
	    }

	    $taxes = $this->calculate_tax( array(
		    'to_country' => $address[ 'to_country' ],
		    'to_state' => $address[ 'to_state' ],
		    'to_zip' => $address[ 'to_zip' ],
		    'to_city' => $address[ 'to_city' ],
		    'to_street' => $address[ 'to_street' ],
		    'shipping_amount' => $shipping,
		    'line_items' => $line_items,
	    ) );

	    if ( class_exists( 'WC_Order_Item_Tax' ) ) { // Add tax rates manually for Woo 3.0+
		    foreach ( $order->get_items() as $item_key => $item ) {
			    $product_id = $item->get_product_id();
			    $line_item_key = $product_id . '-' . $item_key;

			    if ( isset( $taxes['rate_ids'][ $line_item_key ] ) ) {
				    $rate_id = $taxes['rate_ids'][ $line_item_key ];
				    $item_tax = new WC_Order_Item_Tax();
				    $item_tax->set_rate( $rate_id );
				    $item_tax->set_order_id( $order->get_id() );
				    $item_tax->save();
			    }
		    }
	    } else { // Recalculate tax for Woo 2.6 to apply new tax rates
		    if ( class_exists( 'WC_AJAX' ) ) {
			    remove_action( 'woocommerce_before_save_order_items', array( $this, 'calculate_backend_totals' ), 20 );
			    if ( check_ajax_referer( 'calc-totals', 'security', false ) ) {
				    WC_AJAX::calc_line_taxes();
			    }
			    add_action( 'woocommerce_before_save_order_items', array( $this, 'calculate_backend_totals' ), 20 );
		    }
	    }
    }

	/**
	 * Get address details of customer at checkout
	 *
	 * @return array
	 */
	protected function get_address() {
		$taxable_address = $this->get_taxable_address();
		$taxable_address = is_array( $taxable_address ) ? $taxable_address : array();

		$to_country = isset( $taxable_address[0] ) && ! empty( $taxable_address[0] ) ? $taxable_address[0] : false;
		$to_state = isset( $taxable_address[1] ) && ! empty( $taxable_address[1] ) ? $taxable_address[1] : false;
		$to_zip = isset( $taxable_address[2] ) && ! empty( $taxable_address[2] ) ? $taxable_address[2] : false;
		$to_city = isset( $taxable_address[3] ) && ! empty( $taxable_address[3] ) ? $taxable_address[3] : false;
		$to_street = isset( $taxable_address[4] ) && ! empty( $taxable_address[4] ) ? $taxable_address[4] : false;

		return array(
			'to_country' => $to_country,
			'to_state' => $to_state,
			'to_zip' => $to_zip,
			'to_city' => $to_city,
			'to_street' => $to_street,
		);
	}

	/**
	 * Get address details of customer for backend orders
	 *
	 * @return array
	 */
	protected function get_backend_address() {
		$to_country = isset( $_POST['country'] ) ? strtoupper( wc_clean( $_POST['country'] ) ) : false;
		$to_state = isset( $_POST['state'] ) ? strtoupper( wc_clean( $_POST['state'] ) ) : false;
		$to_zip = isset( $_POST['postcode'] ) ? strtoupper( wc_clean( $_POST['postcode'] ) ) : false;
		$to_city = isset( $_POST['city'] ) ? strtoupper( wc_clean( $_POST['city'] ) ) : false;
		$to_street = isset( $_POST['street'] ) ? strtoupper( wc_clean( $_POST['street'] ) ) : false;

		return array(
			'to_country' => $to_country,
			'to_state' => $to_state,
			'to_zip' => $to_zip,
			'to_city' => $to_city,
			'to_street' => $to_street,
		);
	}

	/**
	 * Get ship to address from order object
	 *
	 * @return array
	 */
	public function get_address_from_order( $order ) {
	    $address = $order->get_address( 'shipping' );
		return array(
			'to_country' => $address[ 'country' ],
			'to_state' => $address[ 'state' ],
			'to_zip' => $address[ 'postcode' ],
			'to_city' => $address[ 'city' ],
			'to_street' => $address[ 'address_1' ],
		);
    }

	/**
	 * Get line items at checkout
	 *
	 * @return array
	 */
	protected function get_line_items( $wc_cart_object ) {
		$line_items = array();

		foreach ( $wc_cart_object->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			$id = $product->get_id();
			$quantity = $cart_item['quantity'];
			$unit_price = wc_format_decimal( $product->get_price() );
			$line_subtotal = wc_format_decimal( $cart_item['line_subtotal'] );
			$discount = wc_format_decimal( $cart_item['line_subtotal'] - $cart_item['line_total'] );
			$tax_class = explode( '-', $product->get_tax_class() );
			$tax_code = '';

			if ( isset( $tax_class ) && is_numeric( end( $tax_class ) ) ) {
				$tax_code = end( $tax_class );
			}

			if ( ! $product->is_taxable() || 'zero-rate' == sanitize_title( $product->get_tax_class() ) ) {
				$tax_code = '99999';
			}

			// Get WC Subscription sign-up fees for calculations
			if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
				if ( 'none' == WC_Subscriptions_Cart::get_calculation_type() ) {
					if ( class_exists( 'WC_Subscriptions_Synchroniser' ) ) {
						WC_Subscriptions_Synchroniser::maybe_set_free_trial();
					}
					$unit_price = WC_Subscriptions_Cart::set_subscription_prices_for_calculation( $unit_price, $product );
				}
			}

			if ( $unit_price && $line_subtotal ) {
				array_push($line_items, array(
					'id' => $id . '-' . $cart_item_key,
					'quantity' => $quantity,
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
				));
			}
		}

		return $line_items;
	}

	/**
	 * Get line items for backend orders
	 *
	 * @return array
	 */
	protected function get_backend_line_items( $order ) {
		$line_items = array();
		$this->backend_tax_classes = array();

		foreach ( $order->get_items() as $item_key => $item ) {
			if ( is_object( $item ) ) { // Woo 3.0+
				$id = $item->get_product_id();
				$quantity = $item->get_quantity();
				$unit_price = wc_format_decimal( $item->get_subtotal() / $quantity );
				$discount = wc_format_decimal( $item->get_subtotal() - $item->get_total() );
				$tax_class_name = $item->get_tax_class();
				$tax_status = $item->get_tax_status();
			} else { // Woo 2.6
				$id = $item['product_id'];
				$quantity = $item['qty'];
				$unit_price = wc_format_decimal( $item['line_subtotal'] / $quantity );
				$discount = wc_format_decimal( $item['line_subtotal'] - $item['line_total'] );
				$tax_class_name = $item['tax_class'];
				$product = $order->get_product_from_item( $item );
				$tax_status = $product ? $product->get_tax_status() : 'taxable';
			}

			$this->backend_tax_classes[$id] = $tax_class_name;

			$tax_class = explode( '-', $tax_class_name );
			$tax_code = '';

            if ( isset( $tax_class ) && is_numeric( end( $tax_class ) ) ) {
                $tax_code = end( $tax_class );
            }

			if ( 'taxable' !== $tax_status ) {
				$tax_code = '99999';
			}

			if ( $unit_price ) {
				array_push($line_items, array(
					'id' => $id . '-' . $item_key,
					'quantity' => $quantity,
					'product_tax_code' => $tax_code,
					'unit_price' => $unit_price,
					'discount' => $discount,
				));
			}
		}

		return $line_items;
	}

	protected function get_line_item( $id, $line_items ) {
		foreach ( $line_items as $line_item ) {
			if ( $line_item['id'] === $id ) {
				return $line_item;
			}
		}

		return null;
	}

	/**
	 * Override Woo's native tax rates to handle multiple line items with the same tax rate
	 * within the same tax class with different rates due to exemption thresholds
	 *
	 * @return array
	 */
	public function override_woocommerce_tax_rates( $taxes, $price, $rates ) {
		if ( isset( $this->response_line_items ) && array_values( $rates ) ) {
			// Get tax rate ID for current item
			$keys = array_keys( $taxes );
			$tax_rate_id = $keys[0];
			$line_items = array();

			// Map line items using rate ID
			foreach ( $this->response_rate_ids as $line_item_key => $rate_id ) {
				if ( $rate_id == $tax_rate_id ) {
					$line_items[] = $line_item_key;
				}
			}

			// Remove number precision if Woo 3.2+
			if ( function_exists( 'wc_remove_number_precision' ) ) {
				$price = wc_remove_number_precision( $price );
			}

			foreach ( $this->response_line_items as $line_item_key => $line_item ) {
				// If line item belongs to rate and matches the price, manually set the tax
				if ( in_array( $line_item_key, $line_items ) && round( $price, 2 ) == round( $line_item->line_total, 2 ) ) {
					if ( function_exists( 'wc_add_number_precision' ) ) {
						$taxes[ $tax_rate_id ] = wc_add_number_precision( $line_item->tax_collectable );
					} else {
						$taxes[ $tax_rate_id ] = $line_item->tax_collectable;
					}
				}
			}
		}

		return $taxes;
	}

	/**
	 * Set customer zip code and state to store if local shipping option set
	 *
	 * @return array
	 */
	public function append_base_address_to_customer_taxable_address( $address ) {
		$tax_based_on = '';

		list( $country, $state, $postcode, $city, $street ) = array_pad( $address, 5, '' );

		// See WC_Customer get_taxable_address()
		// wc_get_chosen_shipping_method_ids() available since Woo 2.6.2+
		if ( function_exists( 'wc_get_chosen_shipping_method_ids' ) ) {
			if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( wc_get_chosen_shipping_method_ids(), apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 ) {
				$tax_based_on = 'base';
			}
		} else {
			if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( WC()->session->get( 'chosen_shipping_methods', array() ), apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 ) {
				$tax_based_on = 'base';
			}
		}

		if ( 'base' == $tax_based_on ) {
			$store_settings = $this->get_store_settings();
			$postcode = $store_settings['postcode'];
			$city = strtoupper( $store_settings['city'] );
			$street = $store_settings['street'];
		}

		if ( '' != $street ) {
			return array( $country, $state, $postcode, $city, $street );
		} else {
			return array( $country, $state, $postcode, $city );
		}
	}

	/**
	 * Allow street address to be passed when finding rates
	 *
	 * @param array $matched_tax_rates
	 * @param string $tax_class
	 * @return array
	 */
	public function allow_street_address_for_matched_rates( $matched_tax_rates, $tax_class = '' ) {
		$tax_class         = sanitize_title( $tax_class );
		$location          = WC_Tax::get_tax_location( $tax_class );
		$matched_tax_rates = array();

		if ( sizeof( $location ) >= 4 ) {
			list( $country, $state, $postcode, $city, $street ) = array_pad( $location, 5, '' );

			$matched_tax_rates = WC_Tax::find_rates( array(
				'country' 	=> $country,
				'state' 	=> $state,
				'postcode' 	=> $postcode,
				'city' 		=> $city,
				'tax_class' => $tax_class,
			) );
		}

		return $matched_tax_rates;
	}

	/**
	 * Get taxable address.
	 * @return array
	 */
	public function get_taxable_address() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Check shipping method at this point to see if we need special handling
		// See WC_Customer get_taxable_address()
		// wc_get_chosen_shipping_method_ids() available since Woo 2.6.2+
		if ( function_exists( 'wc_get_chosen_shipping_method_ids' ) ) {
			if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( wc_get_chosen_shipping_method_ids(), apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 ) {
				$tax_based_on = 'base';
			}
		} else {
			if ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( WC()->session->get( 'chosen_shipping_methods', array() ), apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 ) {
				$tax_based_on = 'base';
			}
		}

		if ( 'base' === $tax_based_on ) {
			$store_settings = $this->get_store_settings();
			$country  = $store_settings['country'];
			$state    = $store_settings['state'];
			$postcode = $store_settings['postcode'];
			$city     = $store_settings['city'];
			$street   = $store_settings['street'];
		} elseif ( 'billing' === $tax_based_on ) {
			$country  = WC()->customer->get_billing_country();
			$state    = WC()->customer->get_billing_state();
			$postcode = WC()->customer->get_billing_postcode();
			$city     = WC()->customer->get_billing_city();
			$street   = WC()->customer->get_billing_address();
		} else {
			$country  = WC()->customer->get_shipping_country();
			$state    = WC()->customer->get_shipping_state();
			$postcode = WC()->customer->get_shipping_postcode();
			$city     = WC()->customer->get_shipping_city();
			$street   = WC()->customer->get_shipping_address();
		}

		return apply_filters( 'woocommerce_customer_taxable_address', array( $country, $state, $postcode, $city, $street ) );
	}

	public function is_postal_code_valid( $to_country, $to_state, $to_zip ) {
	    $postal_regexes = array(
            'US' => '/^\d{5}([ \-]\d{4})?$/',
            'CA' => '/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ ]?\d[ABCEGHJ-NPRSTV-Z]\d$/',
            'UK' => '/^GIR[ ]?0AA|((AB|AL|B|BA|BB|BD|BH|BL|BN|BR|BS|BT|CA|CB|CF|CH|CM|CO|CR|CT|CV|CW|DA|DD|DE|DG|DH|DL|DN|DT|DY|E|EC|EH|EN|EX|FK|FY|G|GL|GY|GU|HA|HD|HG|HP|HR|HS|HU|HX|IG|IM|IP|IV|JE|KA|KT|KW|KY|L|LA|LD|LE|LL|LN|LS|LU|M|ME|MK|ML|N|NE|NG|NN|NP|NR|NW|OL|OX|PA|PE|PH|PL|PO|PR|RG|RH|RM|S|SA|SE|SG|SK|SL|SM|SN|SO|SP|SR|SS|ST|SW|SY|TA|TD|TF|TN|TQ|TR|TS|TW|UB|W|WA|WC|WD|WF|WN|WR|WS|WV|YO|ZE)(\d[\dA-Z]?[ ]?\d[ABD-HJLN-UW-Z]{2}))|BFPO[ ]?\d{1,4}$/',
            'FR' => '/^\d{2}[ ]?\d{3}$/',
            'IT' => '/^\d{5}$/',
            'DE' => '/^\d{5}$/',
            'NL' => '/^\d{4}[ ]?[A-Z]{2}$/',
            'ES' => '/^\d{5}$/',
            'DK' => '/^\d{4}$/',
            'SE' => '/^\d{3}[ ]?\d{2}$/',
            'BE' => '/^\d{4}$/',
            'IN' => '/^\d{6}$/',
            'AU' => '/^\d{4}$/',
        );

	    if ( isset( $postal_regexes[ $to_country ] ) ) {
	        // SmartCalcs api allows requests with no zip codes outside of the US, mark them as valid
	        if ( empty( $to_zip ) ) {
	            if ( $to_country == 'US' ) {
	                return false;
                } else {
	                return true;
                }
            }

	        if ( preg_match( $postal_regexes[ $to_country ], $to_zip ) === 0 ) {
                $this->_log( ':::: Postal code ' . $to_zip . ' is invalid for country ' . $to_country . ', API request stopped. ::::' );
	            return false;
            }
        }

	    return true;
    }

	/**
	 * Return either the post value or settings value of a key
	 *
	 * @return MIXED
	 */
	public function post_or_setting( $key ) {
		$val = null;

        if ( isset( $_POST[ 'woocommerce_taxjar-integration_' . $key ] ) ) {
            $val = $_POST[ 'woocommerce_taxjar-integration_' . $key ];
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
				isset( $settings['store_postcode'] ) &&
				isset( $settings['store_city'] ) &&
				isset( $settings['store_street'] ) ) {
			$settings['api_token']  = strtolower( wc_clean( $settings['api_token'] ) );
			$settings['store_postcode']  = wc_clean( $settings['store_postcode'] );
			$settings['store_city'] = wc_clean( $settings['store_city'] );
			$settings['store_street'] = wc_clean( $settings['store_street'] );
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
		$store_address = get_option( 'woocommerce_store_address' ) ? get_option( 'woocommerce_store_address' ) : $this->settings['store_street'];
		$store_city = get_option( 'woocommerce_store_city' ) ? get_option( 'woocommerce_store_city' ) : $this->settings['store_city'];
		$store_country = explode( ':', get_option( 'woocommerce_default_country' ) );
		$store_postcode = get_option( 'woocommerce_store_postcode' ) ? get_option( 'woocommerce_store_postcode' ) : $this->settings['store_postcode'];

		$store_settings = array(
			'street' => $store_address,
			'city' => $store_city,
			'state' => null,
			'country' => $store_country[0],
			'postcode' => $store_postcode,
		);

		if ( isset( $store_country[1] ) ) {
			$store_settings['state'] = $store_country[1];
		}

		return apply_filters( 'taxjar_store_settings', $store_settings, $this->settings );
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
	 * Output TaxJar message above tax configuration screen
	 */
	public function output_sections_before() {
		echo '<div class="updated taxjar-notice"><p><b>Powered by <a href="https://www.taxjar.com" target="_blank">TaxJar</a></b> ― Your tax rates and settings are automatically configured below.</p><p><a href="admin.php?page=wc-settings&tab=integration&section=taxjar-integration" class="button-primary">Configure TaxJar</a> &nbsp; <a href="https://www.taxjar.com/contact/" class="button" target="_blank">Help &amp; Support</a></p></div>';
	}

	/**
	 * Checks if currently on the WooCommerce new order page
	 *
	 * @return boolean
	 */
	public function on_order_page() {
		global $pagenow;
		return ( in_array( $pagenow, array( 'post-new.php' ) ) && isset( $_GET['post_type'] ) && 'shop_order' == $_GET['post_type'] );
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

	/**
	 * Admin New Order Assets
	 */
	public function load_taxjar_admin_new_order_assets() {
		if ( ! $this->on_order_page() ) {
			return;
		}

		// Load Javascript for WooCommerce new order page
		wp_register_script( 'wc-taxjar-order', plugin_dir_url( __FILE__ ) . '/js/wc-taxjar-order.js' );
		wp_enqueue_script( 'wc-taxjar-order' , array( 'jquery' ) );
	}

}

endif;
