<?php
/**
 * TaxJar Tax Calculations
 *
 * The TaxJar Tax Calculations class is responsible for all tax calculations
 *
 * @package TaxJar/Classes
 */

use TaxJar\TaxJar_Tax_Calculator_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TaxJar_Tax_Calculations
 */
class TaxJar_Tax_Calculation {

	public function __construct() {
		// Cache rates for 1 hour.
		$this->cache_time = HOUR_IN_SECONDS;


		if ( TaxJar_Settings::is_tax_calculation_enabled() ) {
			$this->init_hooks();
			$this->update_tax_options();
		}
	}

	public function init_hooks() {
		// Calculate Taxes at Cart / Checkout
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'calculate_totals' ), 20 );

		// Calculate taxes for WooCommerce Subscriptions renewal orders
		add_filter( 'wcs_new_order_created', array( $this, 'calculate_renewal_order_totals' ), 10, 3 );

		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'maybe_calculate_order_taxes' ), 10, 2 );

		// Filters
		add_filter( 'woocommerce_calc_tax', array( $this, 'override_woocommerce_tax_rates' ), 10, 3 );
		add_filter( 'woocommerce_customer_taxable_address', array( $this, 'append_base_address_to_customer_taxable_address' ), 10, 1 );
		add_filter( 'woocommerce_matched_rates', array( $this, 'allow_street_address_for_matched_rates' ), 10, 2 );
	}

	public function maybe_calculate_order_taxes( $and_taxes, $order ) {
		$calculator_builder = new TaxJar_Tax_Calculator_Builder();
		$calculator = $calculator_builder->build_order_calculator( $and_taxes, $order );

		if ( $calculator !== false ) {
			$calculator->maybe_calculate_and_apply_tax();
		}
	}

	public function update_tax_options() {
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
	 * Calculate sales tax using SmartCalcs
	 *
	 * @return array|boolean
	 */
	public function calculate_tax( $options = array() ) {
		TaxJar()->_log( ':::: TaxJar Plugin requested ::::' );

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
		if ( ! self::is_postal_code_valid( $calculation_data['to_country'], $calculation_data['to_state'], $calculation_data['to_zip'] ) ) {
			return false;
		}

		$taxjar_nexus = new WC_Taxjar_Nexus();

		if ( ! $taxjar_nexus->has_nexus_check( $calculation_data['to_country'], $calculation_data['to_state'] ) ) {
			TaxJar()->_log( ':::: Order not shipping to nexus area ::::' );
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

		TaxJar()->_log( ':::: TaxJar API called ::::' );

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
			TaxJar()->_log( 'Received: ' . $response['body'] );

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
					$taxes['rate_ids'][ $line_item_key ] = self::create_or_update_tax_rate(
						$location,
						$line_item->combined_tax_rate * 100,
						$tax_class,
						$taxes['freight_taxable']
					);
				}
			}

			// Add shipping tax rate
			$taxes['rate_ids']['shipping'] = self::create_or_update_tax_rate(
				$location,
				$taxes['shipping_rate'] * 100,
				'',
				$taxes['freight_taxable']
			);
		} // End if().

		return $taxes;
	} // End calculate_tax().

	public static function is_postal_code_valid( $to_country, $to_state, $to_zip ) {
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
				TaxJar()->_log( ':::: Postal code ' . $to_zip . ' is invalid for country ' . $to_country . ', API request stopped. ::::' );
				return false;
			}
		}

		return true;
	}

	static function is_valid_exemption_type( $exemption_type ) {
		$valid_types = array( 'wholesale', 'government', 'other', 'non_exempt' );
		return in_array( $exemption_type, $valid_types, true );
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
	 * Add or update a native WooCommerce tax rate
	 *
	 * @return void
	 */
	public static function create_or_update_tax_rate( $location, $rate, $tax_class = '', $freight_taxable = 1 ) {
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
			TaxJar()->_log( ':: Tax Rate Found ::' );
			TaxJar()->_log( $wc_rate );

			// Get the existing ID
			$rate_id = key( $wc_rate );

			// Update Tax Rates with TaxJar rates ( rates might be coming from a cached taxjar rate )
			TaxJar()->_log( ':: Updating Tax Rate To ::' );
			TaxJar()->_log( $tax_rate );

			WC_Tax::_update_tax_rate( $rate_id, $tax_rate );
		} else {
			// Insert a rate if we did not find one
			TaxJar()->_log( ':: Adding New Tax Rate ::' );
			TaxJar()->_log( $tax_rate );
			$rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
			WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $location['to_zip'] ) );
			WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $location['to_city'] ) );
		}

		TaxJar()->_log( 'Tax Rate ID Set for ' . $rate_id );
		return $rate_id;
	}

	public function smartcalcs_request( $json ) {
		$response = apply_filters( 'taxjar_smartcalcs_request', false, $json );

		if ( ! $response ) {
			$request = new TaxJar_API_Request( 'taxes', $json );
			TaxJar()->_log( 'Requesting: ' . $request->get_full_url() . ' - ' . $json );
			$response = $request->send_request();
		}

		if ( is_wp_error( $response ) ) {
			new WP_Error( 'request', __( 'There was an error retrieving the tax rates. Please check your server configuration.' ) );
		} elseif ( 200 === $response['response']['code'] ) {
			return $response;
		} else {
			TaxJar()->_log( 'Received (' . $response['response']['code'] . '): ' . $response['body'] );
		}
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

		$calculator_builder = new TaxJar_Tax_Calculator_Builder();
		$subscription_calculator = $calculator_builder->build_subscription_order_calculator( $subscription );

		if ( $subscription_calculator !== false ) {
			$subscription_calculator->maybe_calculate_and_apply_tax();
		}

		$calculator_builder = new TaxJar_Tax_Calculator_Builder();
		$renewal_calculator = $calculator_builder->build_renewal_order_calculator( $order );

		if ( $renewal_calculator !== false ) {
			$renewal_calculator->maybe_calculate_and_apply_tax();
		}

		return $order;
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



	protected function get_line_item( $id, $line_items ) {
		foreach ( $line_items as $line_item ) {
			if ( $line_item['id'] === $id ) {
				return $line_item;
			}
		}

		return null;
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
}
