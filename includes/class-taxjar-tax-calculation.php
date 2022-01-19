<?php
/**
 * TaxJar Tax Calculations
 *
 * The TaxJar Tax Calculations class is responsible for all tax calculations
 *
 * @package TaxJar/Classes
 */

use TaxJar\Tax_Calculator_Builder;
use TaxJar\WooCommerce\TaxCalculation\Block_Flag;

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
			Block_Flag::init_hooks();
			$this->update_tax_options();
		}
	}

	public function init_hooks() {
		// Calculate Taxes at Cart / Checkout
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'maybe_calculate_cart_taxes' ), 20 );

		// Calculate taxes for WooCommerce Subscriptions renewal orders
		add_filter( 'wcs_new_order_created', array( $this, 'calculate_renewal_order_totals' ), 10, 3 );

		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'maybe_calculate_order_taxes' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'persist_cart_calculation_results_to_order'), 10, 1 );
		add_action( 'woocommerce_checkout_create_subscription', array( $this, 'persist_recurring_cart_calculation_results_to_subscription'), 10, 4 );
	}

	public function persist_cart_calculation_results_to_order( $order ) {
		$calculation_results = WC()->cart->tax_calculation_results;
		$order->update_meta_data( '_taxjar_tax_result', $calculation_results );
	}

	public function persist_recurring_cart_calculation_results_to_subscription(  $subscription, $posted_data, $order, $recurring_cart ) {
		$calculation_results = $recurring_cart->tax_calculation_results;
		$subscription->update_meta_data( '_taxjar_tax_result', $calculation_results );
	}

	public function maybe_calculate_order_taxes( $and_taxes, $order ) {
		$calculator_builder = new Tax_Calculator_Builder();
		$calculator = $calculator_builder->build_order_calculator( $and_taxes, $order );

		if ( $calculator !== false ) {
			$calculator->maybe_calculate_and_apply_tax();
		}
	}

	public function maybe_calculate_cart_taxes( WC_Cart $cart ) {
		if ( $this->should_calculate_cart_tax( $cart ) ) {
			$calculator_builder = new Tax_Calculator_Builder();
			$calculator = $calculator_builder->build_cart_calculator( $cart );
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
	 * Determines whether TaxJar should calculate tax on the cart
	 *
	 * @param WC_Cart $wc_cart_object
	 * @return bool - whether or not TaxJar should calculate tax
	 */
	public function should_calculate_cart_tax( $wc_cart_object ) {
		$should_calculate = true;

		if ( ! $this->should_calculate_on_page() ) {
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
	 * Check if on a page that requires tax calculation.
	 *
	 * @return bool
	 */
	private function should_calculate_on_page() {
		return ( $this->is_cart_or_checkout_page() && ! $this->is_mini_cart() ) || $this->is_cart_or_checkout_block();
	}

	/**
	 * Check if on cart or checkout page.
	 *
	 * @return bool
	 */
	private function is_cart_or_checkout_page() {
		return is_cart() || is_checkout();
	}

	/**
	 * Check if in the mini cart.
	 *
	 * @return bool
	 */
	private function is_mini_cart() {
		return is_cart() && wp_doing_ajax();
	}

	/**
	 * Check if in the cart or checkout block.
	 *
	 * @return bool
	 */
	private function is_cart_or_checkout_block() {
		return Block_Flag::was_block_initialized();
	}

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

		if ( ! preg_match( '/^\d{5}$|^\d+[a-zA-Z]\d+$/', $tax_code ) ) {
			$tax_code = '';
		}

		$tax_code = strtoupper( $tax_code );
		return apply_filters( 'taxjar_get_product_tax_code', $tax_code, $tax_class );
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

		$calculator_builder = new Tax_Calculator_Builder();
		$subscription_calculator = $calculator_builder->build_subscription_order_calculator( $subscription );

		if ( $subscription_calculator !== false ) {
			$subscription_calculator->maybe_calculate_and_apply_tax();
		}

		$calculator_builder = new Tax_Calculator_Builder();
		$renewal_calculator = $calculator_builder->build_renewal_order_calculator( $order );

		if ( $renewal_calculator !== false ) {
			$renewal_calculator->maybe_calculate_and_apply_tax();
		}

		return $order;
	}
}
