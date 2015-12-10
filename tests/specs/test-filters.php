<?php
class TJ_WC_Filters extends WP_UnitTestCase {
  public function setUp() {
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  }

  function test_append_base_address_to_customer_taxable_address() {
    global $woocommerce;
    TaxJar_Woocommerce_helper::prepare_woocommerce();
    $tj = new WC_Taxjar_Integration();
    $woocommerce->session->set('chosen_shipping_methods', array('local_pickup'));

    $address = array('US', 'CO', '81210', 'Denver');
    $address = apply_filters('woocommerce_customer_taxable_address', $address);

    $this->assertTrue($address[2] == $tj->settings['store_zip']);
    $this->assertTrue($address[3] == $tj->settings['store_city']);
  }

  function test_woocommerce_ajax_calc_line_taxes() {
    TaxJar_Woocommerce_helper::prepare_woocommerce();
    $order = wc_create_order();
    $order->add_product( TaxJar_Helper_Product::get_test_product() );
    $post = array(
      'country'     => 'US',
      'state'       => 'CO',
      'postalcode'  => '80111',
      'city'        => 'Greenwood Village'
    );

    apply_filters( 'woocommerce_ajax_calc_line_taxes', $order->items, $order->id, 'US', $post );
  
    // TODO assert tax_total and shipping_tax have been updated
  }
}