<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Calculator_Builder {

	private $calculator;

	public function __construct() {
		$this->calculator = new TaxJar_Tax_Calculator();
		$this->set_tax_cache();
		$this->set_tax_client();
	}

	public function build_order_calculator( $should_calculate, $order ) {
		if ( $should_calculate ) {
			if ( $this->is_rest_request() ) {
				$this->maybe_setup_api_tax_calculator( $order );
			} else {
				$this->setup_order_calculator( $order );
			}
		} else {
			$this->maybe_setup_admin_order_calculator( $order );
		}

		return $this->calculator;
	}

	private function is_rest_request() {
		return Constants_Manager::is_true( 'REST_REQUEST' );
	}

	private function maybe_setup_api_tax_calculator( $order ) {
		if ( ! $this->is_api_tax_calculation_enabled() ) {
			$this->calculator = false;
		} else {
			$this->setup_api_tax_calculator( $order );
		}
	}

	private function is_api_tax_calculation_enabled() {
		$settings = TaxJar_Settings::get_taxjar_settings();
		return isset( $settings['api_calcs_enabled'] ) && 'yes' === $settings['api_calcs_enabled'];
	}

	private function setup_api_tax_calculator( $order ) {
		$this->set_order_logger( $order );
		$this->set_order_tax_request_body_factory( $order );
		$this->set_order_applicator( $order );
		$this->set_order_validator( $order );
		$this->set_context( 'api_order' );
	}

	private function setup_order_calculator( $order ) {
		$this->set_order_logger( $order );
		$this->set_order_tax_request_body_factory( $order );
		$this->set_order_applicator( $order );
		$this->set_order_validator( $order );
		$this->set_context( 'order' );
	}

	private function set_order_logger( $order ) {
		$wc_logger = wc_get_logger();
		$this->calculator->set_logger( new TaxJar_Order_Calculation_Logger( $wc_logger, $order ) );
	}

	private function set_tax_cache() {
		$this->calculator->set_cache( new TaxJar_Cache( HOUR_IN_SECONDS, 'tj_tax_' ) );
	}

	private function set_order_tax_request_body_factory( $order ) {
		$this->calculator->set_request_body_factory( new TaxJar_Order_Tax_Request_Body_Factory( $order ) );
	}

	private function set_tax_client() {
		$this->calculator->set_tax_client( new TaxJar_Tax_Client() );
	}

	private function set_order_applicator( $order ) {
		$this->calculator->set_applicator( new TaxJar_Order_Tax_Applicator( $order ) );
	}

	private function set_order_validator( $order ) {
		$nexus = new WC_Taxjar_Nexus();
		$this->calculator->set_validator( new TaxJar_Order_Tax_Calculation_Validator( $order, $nexus ) );
	}

	private function set_context( $context ) {
		$this->calculator->set_context( $context );
	}

	private function maybe_setup_admin_order_calculator( $order ) {
		if ( $this->is_doing_ajax_method_that_needs_tax_calculation() ) {
			$this->setup_admin_order_calculator( $order );
		} else {
			$this->calculator = false;
		}
	}

	private function is_doing_ajax_method_that_needs_tax_calculation() {
		return wp_doing_ajax() && $this->should_calculate_tax_for_action();
	}

	private function should_calculate_tax_for_action() {
		if ( empty( $_POST['action'] ) ) {
			return false;
		}

		if ( $this->action_needs_calculation() && $this->is_nonce_valid() ) {
			return true;
		}

		return false;
	}

	private function action_needs_calculation() {
		return array_key_exists( $_POST['action'], $this->get_actions_that_need_calculation() );
	}

	private function get_actions_that_need_calculation() {
		return array(
			'woocommerce_add_order_fee' => 'order-item',
			'woocommerce_add_coupon_discount' => 'order-item',
			'woocommerce_remove_order_coupon' => 'order-item',
			'woocommerce_remove_order_item' => 'order-item',
			'woocommerce_calc_line_taxes' => 'calc-totals',
		);
	}

	private function is_nonce_valid() {
		$actions = $this->get_actions_that_need_calculation();
		$action_nonce_key = $actions[ $_POST['action'] ];
		return check_ajax_referer( $action_nonce_key, 'security', false );
	}

	private function setup_admin_order_calculator( $order ) {
		$this->set_order_logger( $order );
		$this->set_admin_order_tax_request_body_factory( $order );
		$this->set_order_applicator( $order );
		$this->set_order_validator( $order );
		$this->set_context( 'admin_order' );
	}

	private function set_admin_order_tax_request_body_factory( $order ) {
		$this->calculator->set_request_body_factory( new TaxJar_Admin_Order_Tax_Request_Body_Factory( $order ) );
	}
}


