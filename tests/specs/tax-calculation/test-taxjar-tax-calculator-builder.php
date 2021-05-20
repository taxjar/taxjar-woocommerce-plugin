<?php

namespace TaxJar;
use WP_UnitTestCase;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_TaxJar_Tax_Calculator_Builder extends WP_UnitTestCase {

	private $order;
	private $builder;

	public function setUp() {
		$this->order = $this->createMock( WC_Order::class );
		$this->builder = new TaxJar_Tax_Calculator_Builder();
		add_filter( 'wp_doing_ajax', array( $this, 'override_doing_ajax' ) );
	}

	public function tearDown() {
		unset( $_POST['action'] );
		unset( $_REQUEST['security'] );
		remove_filter( 'wp_doing_ajax', array( $this, 'override_doing_ajax' ) );
		Constants_Manager::clear_constants();
	}

	public function override_doing_ajax() {
		return true;
	}

	public function test_build_order_calculator() {
		$should_calculate_tax = true;
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'order', $calculator->get_context() );
	}

	public function test_build_order_calculator_when_no_calculation_required() {
		$should_calculate_tax = false;
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertFalse( $calculator );
	}

	public function test_woocommerce_calc_line_taxes_ajax() {
		$should_calculate_tax = false;
		$this->create_action_nonce( 'calc-totals' );
		$_POST['action'] = 'woocommerce_calc_line_taxes';
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'admin_order', $calculator->get_context() );
	}

	public function test_woocommerce_add_coupon_discount_ajax() {
		$should_calculate_tax = false;
		$this->create_action_nonce( 'order-item' );
		$_POST['action'] = 'woocommerce_add_coupon_discount';
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'admin_order', $calculator->get_context() );
	}

	public function test_woocommerce_remove_order_coupon_ajax() {
		$should_calculate_tax = false;
		$this->create_action_nonce( 'order-item' );
		$_POST['action'] = 'woocommerce_remove_order_coupon';
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'admin_order', $calculator->get_context() );
	}

	public function test_woocommerce_remove_order_item_ajax() {
		$should_calculate_tax = false;
		$this->create_action_nonce( 'order-item' );
		$_POST['action'] = 'woocommerce_remove_order_item';
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'admin_order', $calculator->get_context() );
	}

	public function test_woocommerce_add_order_fee_ajax() {
		$should_calculate_tax = false;
		$this->create_action_nonce( 'order-item' );
		$_POST['action'] = 'woocommerce_add_order_fee';
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertNotFalse( $calculator );
		$this->assertEquals( 'admin_order', $calculator->get_context() );
	}

	public function test_api_request() {
		$should_calculate_tax = true;
		Constants_Manager::set_constant( 'REST_REQUEST', true );
		$calculator = $this->builder->build_order_calculator( $should_calculate_tax, $this->order );
		$this->assertEquals( 'api_order', $calculator->get_context() );
	}

	private function create_action_nonce( $key ) {
		$nonce = wp_create_nonce( $key );
		$_REQUEST['security'] = $nonce;
	}




}