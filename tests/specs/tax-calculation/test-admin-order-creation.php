<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Admin_Order_Creation extends WP_UnitTestCase {

	private $test_order;
	private $post_values;
	private $item_parameter;

	public function setUp() {
		TaxJar_Woocommerce_Helper::delete_existing_tax_rates();

		$this->post_values =  array(
			'country' => 'US',
			'state' => 'UT',
			'postcode' => '84651',
			'city' => 'Payson',
			'customer_user' => '1',
			'street' => '123 Main St'
		);

		$this->item_parameter = array(
			'order_item_id' => array(),
			'order_item_tax_class' => array(),
			'order_item_qty' => array(),
			'refund_order_item_qty' => array(),
			'line_subtotal' => array(),
			'line_total' => array(),
			'refund_line_total' => array(),
		);

		$current_user = wp_get_current_user();
		$current_user->add_cap( 'edit_shop_orders' );

		$this->test_order = wc_create_order();
		$this->post_values['order_id'] = $this->test_order->get_id();
	}

	public function tearDown() {
		$this->remove_post_values();
		unset( $_REQUEST['security'] );
	}

	public function test_order_with_single_line_item() {
		$this->setup_basic_order();
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 7.25 );
		$this->assert_correct_totals( $order, 107.25, 7.25 );
	}

	public function test_order_with_taxable_shiping() {
		$this->setup_basic_order();
		$this->add_shipping_to_order();
		$this->change_location( 'NY', '10001', 'New York City');

		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_shipping( $order, 10, 0.89 );
		$this->assert_correct_line_tax( $order, 8.88 );
		$this->assert_correct_totals( $order, 119.77, 9.77 );
	}

	public function test_order_with_fee() {
		$this->add_fee_to_order( 100 );
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_totals( $order, 107.25, 7.25 );
	}

	public function test_order_with_product_tax_code() {
		WC_Tax::create_tax_class( 'Gift Card - 14111803A0001' );
		$exempt_product = TaxJar_Product_Helper::create_product( 'simple', array(
			'price' => '100',
			'tax_class' => 'gift-card-14111803A0001',
		) );
		$quantity = 1;
		$this->add_products_to_order(
			array(
				array(
					'product' => $exempt_product,
					'quantity' => $quantity
				)
			)
		);
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 0 );
		$this->assert_correct_totals( $order, 100, 0 );
	}

	public function test_order_with_vat_exempt_customer() {
		$this->setup_basic_order();
		$customer = TaxJar_Customer_Helper::create_vat_exempt_customer();
		$this->post_values['customer_user'] = $customer->get_id();
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 0 );
		$this->assert_correct_totals( $order, 100, 0 );
	}

	public function test_vat_exempt_order() {
		$this->setup_basic_order();
		update_post_meta( $this->test_order->get_id(), 'is_vat_exempt', 'yes' );
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 0 );
		$this->assert_correct_totals( $order, 100, 0 );
	}

	public function test_order_with_discount() {
		$this->setup_basic_order();
		$coupon = TaxJar_Coupon_Helper::create_coupon();
		$this->test_order->apply_coupon( 'HIRO' );
		$this->test_order->calculate_totals( false );
		$this->update_item_parameter();
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );

		$this->assert_correct_line_tax( $order, 6.53 );
		$this->assert_correct_totals( $order, 96.53, 6.53 );
	}

	public function test_order_without_nexus() {
		$this->post_values['state'] = 'WY';
		$this->post_values['postcode'] = '82001';
		$this->post_values['city'] = 'Cheyenne';

		$this->setup_basic_order();
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 0 );
		$this->assert_correct_totals( $order, 100, 0 );
	}

	public function test_order_without_address() {
		$this->post_values['street'] = '';
		$this->post_values['country'] = '';
		$this->post_values['state'] = '';
		$this->post_values['postcode'] = '';
		$this->post_values['city'] = '';

		$this->setup_basic_order();
		$this->call_wc_ajax_calc_line_taxes();

		$order = wc_get_order( $this->test_order->get_id() );
		$this->assert_correct_line_tax( $order, 0 );
		$this->assert_correct_totals( $order, 100, 0 );
	}

	private function setup_basic_order() {
		$quantity = 1;
		$product = TaxJar_Product_Helper::create_product( 'simple', array( 'price' => 100 ) );
		$this->add_products_to_order(
			array(
				array(
					'product' => $product,
					'quantity' => $quantity
				)
			)
		);
	}

	private function update_item_parameter() {
		foreach ( $this->test_order->get_items() as $item ) {
			$item_id = $item->get_id();
			array_push( $this->item_parameter['order_item_id'], $item_id );
			$this->item_parameter['order_item_tax_class'][ $item_id ] = $item->get_tax_class();
			$this->item_parameter['order_item_qty'][ $item_id ] = $item->get_quantity();
			$this->item_parameter['refund_order_item_qty'][ $item_id ] = '';
			$this->item_parameter['line_subtotal'][ $item_id ] = $item->get_subtotal();
			$this->item_parameter['line_total'][ $item_id ] = $item->get_total();
			$this->item_parameter['refund_line_total'][ $item_id ] = '';
		}
	}

	private function add_products_to_order( $products ) {
		foreach ( $products as $product ) {
			$item_id = $this->test_order->add_product( $product['product'], $product['quantity'] );
			$item = $this->test_order->get_item( $item_id );
			array_push( $this->item_parameter['order_item_id'], $item_id );
			$this->item_parameter['order_item_tax_class'][ $item_id ] = $item->get_tax_class();
			$this->item_parameter['order_item_qty'][ $item_id ] = $product['quantity'];
			$this->item_parameter['refund_order_item_qty'][ $item_id ] = '';
			$this->item_parameter['line_subtotal'][ $item_id ] = $item->get_subtotal();
			$this->item_parameter['line_total'][ $item_id ] = $item->get_total();
			$this->item_parameter['refund_line_total'][ $item_id ] = '';
		}
	}

	private function add_shipping_to_order() {
		$item = new WC_Order_Item_Shipping();
		$item->set_shipping_rate( new WC_Shipping_Rate() );
		$item->set_order_id( $this->test_order->get_id() );
		$item_id = $item->save();

		$this->item_parameter['shipping_method_id'] = array( $item_id );
		$this->item_parameter['shipping_method_title'][ $item_id ] = 'Shipping';
		$this->item_parameter['shipping_method'][ $item_id ] = '';
		$this->item_parameter['shipping_cost'][ $item_id ] = 10;
	}

	private function add_fee_to_order( $amount ) {
		$fee = new WC_Order_Item_Fee();
		$fee->set_amount( $amount );
		$fee->set_total( $amount );
		$fee->set_name( 'Fee' );
		$this->test_order->add_item( $fee );
		$this->test_order->calculate_totals( false );
		$this->test_order->save();

		$item_id = $fee->get_id();

		array_push( $this->item_parameter['order_item_id'], $item_id );
		$this->item_parameter['order_item_tax_class'][ $item_id ] = '';
		$this->item_parameter['line_total'][ $item_id ] = $amount;
		$this->item_parameter['refund_line_total'][ $item_id ] = '';
		$this->item_parameter['order_item_name'][ $item_id ] = 'Fee';
	}

	private function call_wc_ajax_calc_line_taxes() {
		$this->set_up_global_post();
		ob_start();
		WC_AJAX::calc_line_taxes();
		ob_get_clean();
	}

	private function set_up_global_post() {
		$this->post_values['items'] = http_build_query( $this->item_parameter, 'flags_' );
		$this->build_security_nonce();

		foreach( $this->post_values as $key => $value ) {
			$_POST[ $key ] = $value;
		}
	}

	private function build_security_nonce() {
		$nonce = wp_create_nonce( 'calc-totals' );
		$this->post_values['security'] = $nonce;
		$_REQUEST['security'] = $nonce;
	}

	private function remove_post_values() {
		foreach( $this->post_values as $key => $value ) {
			unset( $_POST[ $key ] );
		}
	}

	private function assert_correct_line_tax( $order, $expected_line_tax ) {
		foreach( $order->get_items() as $item ) {
			$this->assertEquals( $expected_line_tax, $item->get_total_tax() );
		}
	}

	private function assert_correct_totals( $order, $expected_total, $expected_tax_total ) {
		$this->assertEquals( $expected_tax_total, $order->get_total_tax() );
		$this->assertEquals( $expected_total, $order->get_total() );
	}

	private function assert_correct_shipping( $order, $expected_shipping_total, $expected_shipping_tax ) {
		$this->assertEquals( $expected_shipping_tax, $order->get_shipping_tax() );
		$this->assertEquals( $expected_shipping_total, $order->get_shipping_total() );
	}

	private function change_location( $state, $postcode, $city ) {
		$this->post_values['state'] = $state;
		$this->post_values['postcode'] = $postcode;
		$this->post_values['city'] = $city;
	}


}