<?php
/**
 * Integration TaxJar
 *
 * @package  WC_Taxjar_Integration
 * @category Integration
 * @author   TaxJar
 */

if ( ! class_exists( 'WC_Taxjar_Integration' ) ) :

	class WC_Taxjar_Integration extends WC_Settings_API {

		protected static $_instance = null;

		public static $app_uri = 'https://app.taxjar.com/';

		/**
		 * Main TaxJar Integration Instance.
		 * Ensures only one instance of TaxJar Integration is loaded or can be loaded.
		 *
		 * @return WC_Taxjar_Integration - Main instance.
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			$this->id                 = 'taxjar-integration';
			$this->method_title       = __( 'TaxJar', 'wc-taxjar' );
			$this->method_description = apply_filters( 'taxjar_method_description', __( 'TaxJar is the easiest to use sales tax calculation and reporting engine for WooCommerce. Connect your TaxJar account and enter the city and zip code from which your store ships. Enable TaxJar calculations to automatically collect sales tax at checkout. You may also enable sales tax reporting to begin importing transactions from this store into your TaxJar account, all in one click!<br><br><b>For the fastest help, please email <a href="mailto:support@taxjar.com">support@taxjar.com</a>. We\'ll get back to you within hours.</b>', 'wc-taxjar' ) );
			$this->integration_uri    = self::$app_uri . 'account/apps/add/woo';
			$this->debug              = filter_var( $this->get_option( 'debug' ), FILTER_VALIDATE_BOOLEAN );
			$this->download_orders    = new WC_Taxjar_Download_Orders( $this );
			$this->transaction_sync   = new WC_Taxjar_Transaction_Sync( $this );
			$this->customer_sync      = new WC_Taxjar_Customer_Sync( $this );
			$this->api_calculation    = new WC_Taxjar_API_Calculation( $this );

			// Load the settings.
			TaxJar_Settings::init();

			// Cache rates for 1 hour.
			$this->cache_time = HOUR_IN_SECONDS;

			if ( $this->on_settings_page() ) {
				add_action( 'admin_enqueue_scripts', array( $this, 'load_taxjar_admin_assets' ) );
			}

			add_filter( 'woocommerce_admin_settings_sanitize_option_woocommerce_taxjar-integration_settings', array( $this, 'sanitize_settings' ), 10, 2 );

			if ( apply_filters( 'taxjar_enabled', isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ) ) {
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
				add_action( 'woocommerce_sections_tax', array( $this, 'output_sections_before' ), 9 );

				// Filters
				add_filter( 'woocommerce_calc_tax', array( $this, 'override_woocommerce_tax_rates' ), 10, 3 );
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
         * Get the regions API endpoint URI
         *
		 * @return string
		 */
		public static function get_regions_uri() {
		    return self::$app_uri . 'account#states';
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
				$postcodes[]       = $wildcard_postcode . '*';
			}
			return $postcodes;
		}

		/**
		 * Calculate sales tax using SmartCalcs
		 *
		 * @return array|boolean
		 */
		public function calculate_tax( $options = array() ) {
			$this->_log( ':::: TaxJar Plugin requested ::::' );

			// Process $options array and turn them into variables
			$options = is_array( $options ) ? $options : array();

			$calculation_data = array_replace_recursive(
				array(
					'to_country'      => null,
					'to_state'        => null,
					'to_zip'          => null,
					'to_city'         => null,
					'to_street'       => null,
					'shipping_amount' => null, // WC()->shipping->shipping_total
					'line_items'      => null,
					'customer_id'     => 0,
					'exemption_type'  => '',
				),
				$options
			);

			$taxes = array(
				'freight_taxable' => 1,
				'has_nexus'       => 0,
				'line_items'      => array(),
				'rate_ids'        => array(),
				'tax_rate'        => 0,
			);

			// Strict conditions to be met before API call can be conducted
			if (
				empty( $calculation_data['to_country'] ) ||
				empty( $calculation_data['to_zip'] ) ||
				( empty( $calculation_data['line_items'] ) && ( 0 === $calculation_data['shipping_amount'] ) )
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
			if ( ! $this->is_postal_code_valid( $calculation_data['to_country'], $calculation_data['to_state'], $calculation_data['to_zip'] ) ) {
				return false;
			}

			$taxjar_nexus = new WC_Taxjar_Nexus();

			if ( ! $taxjar_nexus->has_nexus_check( $calculation_data['to_country'], $calculation_data['to_state'] ) ) {
				$this->_log( ':::: Order not shipping to nexus area ::::' );
				return false;
			}

			$calculation_data['to_zip'] = explode( ',', $calculation_data['to_zip'] );
			$calculation_data['to_zip'] = array_shift( $calculation_data['to_zip'] );

			$store_settings                      = TaxJar_Settings::get_store_settings();
			$from_country                        = $store_settings['country'];
			$from_state                          = $store_settings['state'];
			$from_zip                            = $store_settings['postcode'];
			$from_city                           = $store_settings['city'];
			$from_street                         = $store_settings['street'];
			$calculation_data['shipping_amount'] = is_null( $calculation_data['shipping_amount'] ) ? 0.0 : $calculation_data['shipping_amount'];

			$this->_log( ':::: TaxJar API called ::::' );

			$body = array(
				'from_country' => $from_country,
				'from_state'   => $from_state,
				'from_zip'     => $from_zip,
				'from_city'    => $from_city,
				'from_street'  => $from_street,
				'to_country'   => $calculation_data['to_country'],
				'to_state'     => $calculation_data['to_state'],
				'to_zip'       => $calculation_data['to_zip'],
				'to_city'      => $calculation_data['to_city'],
				'to_street'    => $calculation_data['to_street'],
				'shipping'     => $calculation_data['shipping_amount'],
				'plugin'       => 'woo',
			);

			if ( is_int( $calculation_data['customer_id'] ) ) {
				if ( $calculation_data['customer_id'] > 0 ) {
					$body['customer_id'] = $calculation_data['customer_id'];
				}
			} else {
				if ( ! empty( $calculation_data['customer_id'] ) ) {
					$body['customer_id'] = $calculation_data['customer_id'];
				}
			}

			if ( ! empty( $calculation_data['exemption_type'] ) ) {
				if ( self::is_valid_exemption_type( $calculation_data['exemption_type'] ) ) {
					$body['exemption_type'] = $calculation_data['exemption_type'];
				}
			}

			// Either `amount` or `line_items` parameters are required to perform tax calculations.
			if ( empty( $calculation_data['line_items'] ) ) {
				$body['amount'] = 0.0;
			} else {
				$body['line_items'] = $calculation_data['line_items'];
			}

			$response = $this->smartcalcs_cache_request( wp_json_encode( $body ) );

			if ( isset( $response ) ) {
				// Log the response
				$this->_log( 'Received: ' . $response['body'] );

				// Decode Response
				$taxjar_response = json_decode( $response['body'] );
				$taxjar_response = $taxjar_response->tax;

				// Update Properties based on Response
				$taxes['freight_taxable'] = (int) $taxjar_response->freight_taxable;
				$taxes['has_nexus']       = (int) $taxjar_response->has_nexus;
				$taxes['shipping_rate']   = $taxjar_response->rate;

				if ( ! empty( $taxjar_response->breakdown ) ) {

					if ( ! empty( $taxjar_response->breakdown->shipping ) ) {
						$taxes['shipping_rate'] = $taxjar_response->breakdown->shipping->combined_tax_rate;
					}

					if ( ! empty( $taxjar_response->breakdown->line_items ) ) {
						$calculation_data['line_items'] = array();
						foreach ( $taxjar_response->breakdown->line_items as $line_item ) {
							$calculation_data['line_items'][ $line_item->id ] = $line_item;
						}
						$taxes['line_items'] = $calculation_data['line_items'];
					}
				}
			}

			// Remove taxes if they are set somehow and customer is exempt
			if ( is_object( WC()->customer ) && WC()->customer->is_vat_exempt() ) {
				WC()->cart->remove_taxes(); // Woo < 3.2
			} elseif ( $taxes['has_nexus'] ) {
				// Use Woo core to find matching rates for taxable address
				$location = array(
					'to_country' => $calculation_data['to_country'],
					'to_state'   => $calculation_data['to_state'],
					'to_zip'     => $calculation_data['to_zip'],
					'to_city'    => $calculation_data['to_city'],
				);

				// Add line item tax rates
				foreach ( $taxes['line_items'] as $line_item_key => $line_item ) {
					$line_item_key_chunks = explode( '-', $line_item_key );
					$product_id           = $line_item_key_chunks[0];
					$product              = wc_get_product( $product_id );

					if ( $product ) {
						$tax_class = $product->get_tax_class();
					} else {
						if ( isset( $this->backend_tax_classes[ $product_id ] ) ) {
							$tax_class = $this->backend_tax_classes[ $product_id ];
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
					$taxes['shipping_rate'] * 100,
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
				'tax_rate_country'  => $location['to_country'],
				'tax_rate_state'    => $location['to_state'],
				'tax_rate_name'     => sprintf( '%s Tax', $location['to_state'] ),
				'tax_rate_priority' => 1,
				'tax_rate_compound' => false,
				'tax_rate_shipping' => $freight_taxable,
				'tax_rate'          => $rate,
				'tax_rate_class'    => $tax_class,
			);

			$rate_lookup = array(
				'country'   => $location['to_country'],
				'state'     => $location['to_state'],
				'postcode'  => $location['to_zip'],
				'city'      => $location['to_city'],
				'tax_class' => $tax_class,
			);

			if ( version_compare( WC()->version, '3.2.0', '>=' ) ) {
				$rate_lookup['state'] = sanitize_key( $location['to_state'] );
			}

			$wc_rate = WC_Tax::find_rates( $rate_lookup );

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
			    $request = new TaxJar_API_Request( 'taxes', $json );
				$this->_log( 'Requesting: ' . $request->get_full_url() . ' - ' . $json );
			    $response = $request->send_request();
			}

			if ( is_wp_error( $response ) ) {
				new WP_Error( 'request', __( 'There was an error retrieving the tax rates. Please check your server configuration.' ) );
			} elseif ( 200 === $response['response']['code'] ) {
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

				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
					set_transient( $cache_key, $response, $this->cache_time );
				}
			}

			return $response;
		}

		/**
         * Determines whether TaxJar should calculate tax on the cart
         *
		 * @param WC_Cart $wc_cart_object
		 * @return bool - whether or not TaxJar should calculate tax
		 */
		public function should_calculate_cart_tax( $wc_cart_object ) {
		    $should_calculate = true;

			// If outside of cart and checkout page or within mini-cart, skip calculations
			if ( ( ! is_cart() && ! is_checkout() ) || ( is_cart() && is_ajax() ) ) {
				$should_calculate = false;
			}

			// prevent unnecessary calls to API during add to cart process
			if ( doing_action( 'woocommerce_add_to_cart' ) ) {
				$should_calculate = false;
			}

			if ( floatval( $wc_cart_object->get_total( null ) ) === 0.0 ) {
				$should_calculate = false;
            }

		    return apply_filters( 'taxjar_should_calculate_cart_tax', $should_calculate, $wc_cart_object );
        }

		/**
		 * Calculate tax / totals using TaxJar at checkout
		 *
		 * @return void
		 */
		public function calculate_totals( $wc_cart_object ) {

		    if ( ! $this->should_calculate_cart_tax( $wc_cart_object ) ) {
		        return false;
            }

			$cart_taxes     = array();
			$cart_tax_total = 0;

			foreach ( $wc_cart_object->coupons as $coupon ) {
				if ( method_exists( $coupon, 'get_id' ) ) { // Woo 3.0+
					$limit_usage_qty = get_post_meta( $coupon->get_id(), 'limit_usage_to_x_items', true );

					if ( $limit_usage_qty ) {
						$coupon->set_limit_usage_to_x_items( $limit_usage_qty );
					}
				}
			}

			$address        = $this->get_address( $wc_cart_object );
			$line_items     = $this->get_line_items( $wc_cart_object );
			$shipping_total = $wc_cart_object->get_shipping_total();

			$customer_id = 0;
			if ( is_object( WC()->customer ) ) {
				$customer_id = apply_filters( 'taxjar_get_customer_id', WC()->customer->get_id(), WC()->customer );
			}

			$exemption_type = apply_filters( 'taxjar_cart_exemption_type', '', $wc_cart_object );

			$taxes = $this->calculate_tax(
				array(
					'to_country'      => $address['to_country'],
					'to_zip'          => $address['to_zip'],
					'to_state'        => $address['to_state'],
					'to_city'         => $address['to_city'],
					'to_street'       => $address['to_street'],
					'shipping_amount' => $shipping_total,
					'line_items'      => $line_items,
					'customer_id'     => $customer_id,
					'exemption_type'  => $exemption_type,
				)
			);

			if ( false === $taxes ) {
				return;
			}

			$this->response_rate_ids   = $taxes['rate_ids'];
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
				$product       = $cart_item['data'];
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
			if ( ! $taxes['freight_taxable'] ) {
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
			$order      = wc_get_order( $order_id );

			if ( ! $this->should_calculate_order_tax( $order ) ) {
			    return;
            }

			$address    = $this->get_backend_address();
			$line_items = $this->get_backend_line_items( $order );

			if ( method_exists( $order, 'get_shipping_total' ) ) {
				$shipping = $order->get_shipping_total(); // Woo 3.0+
			} else {
				$shipping = $order->get_total_shipping(); // Woo 2.6
			}

			$customer_id = apply_filters( 'taxjar_get_customer_id', isset( $_POST['customer_user'] ) ? wc_clean( $_POST['customer_user'] ) : 0 );

			$exemption_type = apply_filters( 'taxjar_order_calculation_exemption_type', '', $order );

			$taxes = $this->calculate_tax(
				array(
					'to_country'      => $address['to_country'],
					'to_state'        => $address['to_state'],
					'to_zip'          => $address['to_zip'],
					'to_city'         => $address['to_city'],
					'to_street'       => $address['to_street'],
					'shipping_amount' => $shipping,
					'line_items'      => $line_items,
					'customer_id'     => $customer_id,
					'exemption_type'  => $exemption_type,
				)
			);

			if ( false === $taxes ) {
				return;
			}

			if ( class_exists( 'WC_Order_Item_Tax' ) ) { // Add tax rates manually for Woo 3.0+
				foreach ( $order->get_items() as $item_key => $item ) {
					$product_id    = $item->get_product_id();
					$line_item_key = $product_id . '-' . $item_key;

					if ( isset( $taxes['rate_ids'][ $line_item_key ] ) ) {
						$rate_id  = $taxes['rate_ids'][ $line_item_key ];
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

			if ( ! $this->should_calculate_order_tax( $order ) ) {
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
		 * Determines whether or not TaxJar should calculate tax on an order
		 * @param $order
		 * @return bool
		 */
		public function should_calculate_order_tax( $order ) {
			$should_calculate = true;
			$total = 0;

			foreach( $order->get_items( array( 'line_item', 'fee', 'shipping' ) ) as $item ) {
				$total += floatval( $item->get_total() );
			}

			if ( $total <= 0 ) {
				$should_calculate = false;
			}

			return apply_filters( 'taxjar_should_calculate_order_tax', $should_calculate, $order );
		}

		/**
		 * Calculate tax on an order
		 *
		 * @return null
		 */
		public function calculate_order_tax( $order ) {
			$address    = $this->get_address_from_order( $order );
			$line_items = $this->get_backend_line_items( $order );
			$shipping   = $order->get_shipping_total(); // Woo 3.0+

			$customer_id = apply_filters( 'taxjar_get_customer_id', $order->get_customer_id() );

			$exemption_type = apply_filters( 'taxjar_order_calculation_exemption_type', '', $order );

			$taxes = $this->calculate_tax(
				array(
					'to_country'      => $address['to_country'],
					'to_state'        => $address['to_state'],
					'to_zip'          => $address['to_zip'],
					'to_city'         => $address['to_city'],
					'to_street'       => $address['to_street'],
					'shipping_amount' => $shipping,
					'line_items'      => $line_items,
					'customer_id'     => $customer_id,
					'exemption_type'  => $exemption_type,
				)
			);

			if ( false === $taxes ) {
				return;
			}

			foreach ( $order->get_items() as $item_key => $item ) {
				$product_id    = $item->get_product_id();
				$line_item_key = $product_id . '-' . $item_key;

				if ( isset( $taxes['rate_ids'][ $line_item_key ] ) ) {
					$rate_id  = $taxes['rate_ids'][ $line_item_key ];
					$item_tax = new WC_Order_Item_Tax();
					$item_tax->set_rate( $rate_id );
					$item_tax->set_order_id( $order->get_id() );
					$item_tax->save();
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
			$to_state   = isset( $taxable_address[1] ) && ! empty( $taxable_address[1] ) ? $taxable_address[1] : false;
			$to_zip     = isset( $taxable_address[2] ) && ! empty( $taxable_address[2] ) ? $taxable_address[2] : false;
			$to_city    = isset( $taxable_address[3] ) && ! empty( $taxable_address[3] ) ? $taxable_address[3] : false;
			$to_street  = isset( $taxable_address[4] ) && ! empty( $taxable_address[4] ) ? $taxable_address[4] : false;

			return array(
				'to_country' => $to_country,
				'to_state'   => $to_state,
				'to_zip'     => $to_zip,
				'to_city'    => $to_city,
				'to_street'  => $to_street,
			);
		}

		/**
		 * Get address details of customer for backend orders
		 *
		 * @return array
		 */
		protected function get_backend_address() {
			$to_country = isset( $_POST['country'] ) ? strtoupper( wc_clean( $_POST['country'] ) ) : false;
			$to_state   = isset( $_POST['state'] ) ? strtoupper( wc_clean( $_POST['state'] ) ) : false;
			$to_zip     = isset( $_POST['postcode'] ) ? strtoupper( wc_clean( $_POST['postcode'] ) ) : false;
			$to_city    = isset( $_POST['city'] ) ? strtoupper( wc_clean( $_POST['city'] ) ) : false;
			$to_street  = isset( $_POST['street'] ) ? strtoupper( wc_clean( $_POST['street'] ) ) : false;

			return array(
				'to_country' => $to_country,
				'to_state'   => $to_state,
				'to_zip'     => $to_zip,
				'to_city'    => $to_city,
				'to_street'  => $to_street,
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
				'to_country' => $address['country'],
				'to_state'   => $address['state'],
				'to_zip'     => $address['postcode'],
				'to_city'    => $address['city'],
				'to_street'  => $address['address_1'],
			);
		}

		/**
		 * Get line items at checkout
		 *
		 * @return array
		 */
		public function get_line_items( $wc_cart_object ) {
			$line_items = array();

			foreach ( $wc_cart_object->get_cart() as $cart_item_key => $cart_item ) {
				$id            = $cart_item['data']->get_id();
				$product       = wc_get_product( $id );
				$quantity      = $cart_item['quantity'];
				$unit_price    = wc_format_decimal( $product->get_price() );
				$line_subtotal = wc_format_decimal( $cart_item['line_subtotal'] );
				$discount      = wc_format_decimal( $cart_item['line_subtotal'] - $cart_item['line_total'] );
				$tax_code      = self::get_tax_code_from_class( $product->get_tax_class() );

				if ( ! $product->is_taxable() || 'zero-rate' === sanitize_title( $product->get_tax_class() ) ) {
					$tax_code = '99999';
				}

				// Get WC Subscription sign-up fees for calculations
				if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
					if ( 'none' === WC_Subscriptions_Cart::get_calculation_type() ) {
						$unit_price = WC_Subscriptions_Cart::set_subscription_prices_for_calculation( $unit_price, $product );
					}
				}

				if ( $unit_price && $line_subtotal ) {
					array_push(
						$line_items,
						array(
							'id'               => $id . '-' . $cart_item_key,
							'quantity'         => $quantity,
							'product_tax_code' => $tax_code,
							'unit_price'       => $unit_price,
							'discount'         => $discount,
						)
					);
				}
			}

			return apply_filters( 'taxjar_cart_get_line_items', $line_items, $wc_cart_object, $this );
		}

		/**
		 * Get line items for backend orders
		 *
		 * @return array
		 */
		protected function get_backend_line_items( $order ) {
			$line_items                = array();
			$this->backend_tax_classes = array();

			foreach ( $order->get_items() as $item_key => $item ) {
				if ( is_object( $item ) ) { // Woo 3.0+
					$id             = $item->get_product_id();
					$quantity       = $item->get_quantity();
					$unit_price     = wc_format_decimal( $item->get_subtotal() / $quantity );
					$discount       = wc_format_decimal( $item->get_subtotal() - $item->get_total() );
					$tax_class_name = $item->get_tax_class();
					$tax_status     = $item->get_tax_status();
				}

				$this->backend_tax_classes[ $id ] = $tax_class_name;
				$tax_code                         = self::get_tax_code_from_class( $tax_class_name );

				if ( 'taxable' !== $tax_status ) {
					$tax_code = '99999';
				}

				if ( $unit_price ) {
					array_push(
						$line_items,
						array(
							'id'               => $id . '-' . $item_key,
							'quantity'         => $quantity,
							'product_tax_code' => $tax_code,
							'unit_price'       => $unit_price,
							'discount'         => $discount,
						)
					);
				}
			}

			return apply_filters( 'taxjar_order_calculation_get_line_items', $line_items, $order );
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
				$keys        = array_keys( $taxes );
				$tax_rate_id = $keys[0];
				$line_items  = array();

				// Map line items using rate ID
				foreach ( $this->response_rate_ids as $line_item_key => $rate_id ) {
					if ( $rate_id === $tax_rate_id ) {
						$line_items[] = $line_item_key;
					}
				}

				// Remove number precision if Woo 3.2+
				if ( function_exists( 'wc_remove_number_precision' ) ) {
					$price = wc_remove_number_precision( $price );
				}

				foreach ( $this->response_line_items as $line_item_key => $line_item ) {
					// If line item belongs to rate and matches the price, manually set the tax
					if ( in_array( $line_item_key, $line_items, true ) && round( $price, 2 ) === round( $line_item->line_total, 2 ) ) {
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

			if ( 'base' === $tax_based_on ) {
				$store_settings = TaxJar_Settings::get_store_settings();
				$postcode       = $store_settings['postcode'];
				$city           = strtoupper( $store_settings['city'] );
				$street         = $store_settings['street'];
			}

			if ( '' !== $street ) {
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

				$matched_tax_rates = WC_Tax::find_rates(
					array(
						'country'   => $country,
						'state'     => $state,
						'postcode'  => $postcode,
						'city'      => $city,
						'tax_class' => $tax_class,
					)
				);
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
				$store_settings = TaxJar_Settings::get_store_settings();
				$country        = $store_settings['country'];
				$state          = $store_settings['state'];
				$postcode       = $store_settings['postcode'];
				$city           = $store_settings['city'];
				$street         = $store_settings['street'];
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
					if ( 'US' === $to_country ) {
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
			$data     = wp_parse_args( $data, $defaults );
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
		 * Sanitize our settings
		 */
		public function sanitize_settings( $value, $option ) {
			parse_str( $option['id'], $option_name_array );
			$option_name  = current( array_keys( $option_name_array ) );
			$setting_name = key( $option_name_array[ $option_name ] );

			if ( in_array( $setting_name, array( 'store_postcode', 'store_city', 'store_street' ), true ) ) {
				return wc_clean( $value );
			}

			if ( 'api_token' === $setting_name ) {
				return strtolower( wc_clean( $value ) );
			}

			if ( 'taxjar_download' === $setting_name ) {
				return $this->download_orders->validate_taxjar_download_field( $setting_name );
			}

			return $value;
		}

		/**
		 * Gets the value for a seting from POST given a key or returns false if box not checked
		 *
		 * @param mixed $key
		 * @return mixed $value
		 */
		public function get_value_from_post( $key ) {
			if ( isset( $_POST[ $this->plugin_id . $this->id . '_settings' ][ $key ] ) ) {
				return $_POST[ $this->plugin_id . $this->id . '_settings' ][ $key ];
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
			return ( in_array( $pagenow, array( 'post-new.php' ), true ) && isset( $_GET['post_type'] ) && 'shop_order' === $_GET['post_type'] );
		}

		/**
		 * Checks if currently on the TaxJar settings page
		 *
		 * @return boolean
		 */
		public function on_settings_page() {
			return ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['tab'] ) && 'taxjar-integration' === $_GET['tab'] );
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
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'transaction_backfill_nonce' => wp_create_nonce( 'taxjar-transaction-backfill' ),
					'update_nexus_nonce'         => wp_create_nonce( 'taxjar-update-nexus' ),
					'current_user'               => get_current_user_id(),
					'integration_uri'            => $this->integration_uri,
					'connect_url'                => $this->get_connect_url(),
					'app_url'                    => untrailingslashit( self::$app_uri ),
				)
			);

			wp_enqueue_script( 'wc-taxjar-admin', array( 'jquery' ) );

			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui-datepicker' );
		}

		/**
		 * Generates TaxJar connect popup url
		 *
		 * @return string - TaxJar connect popup url
		 */
		public function get_connect_url() {
			$connect_url  = self::$app_uri . 'smartcalcs/connect/woo/?store=' . urlencode( get_bloginfo( 'url' ) );
			$connect_url .= '&plugin=woo&version=' . WC_Taxjar::$version;
			return esc_url( $connect_url );
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
			wp_enqueue_script( 'wc-taxjar-order', array( 'jquery' ) );
		}

		static function is_valid_exemption_type( $exemption_type ) {
			$valid_types = array( 'wholesale', 'government', 'other', 'non_exempt' );
			return in_array( $exemption_type, $valid_types, true );
		}

		/**
		 * Parse tax code from product
		 *
		 * @return string - tax code
		 */
		static function get_tax_code_from_class( $tax_class ) {
			$tax_class = explode( '-', $tax_class );
			$tax_code  = '';

			if ( isset( $tax_class ) ) {
				$tax_code = end( $tax_class );
			}

			return strtoupper( $tax_code );
		}

	}

endif;
