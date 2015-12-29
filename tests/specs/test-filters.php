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

    $this->assertEquals(strtoupper($address[2]), strtoupper($tj->settings['store_zip']));
    $this->assertEquals(strtoupper($address[3]), strtoupper($tj->settings['store_city']));
  }
}