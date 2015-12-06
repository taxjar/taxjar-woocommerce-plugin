<?php
class TJ_WC_Actions extends WP_UnitTestCase {
  function test_use_taxjar_total() {
    global $woocommerce;
    $woocommerce->product_factory = new WC_Product_Factory();
    $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
    $woocommerce->session  = new $session_class();
    $bootstrap = TaxJar_WC_Unit_Tests_Bootstrap::instance();
    $product = $bootstrap->get_test_product();


    $customer = new WC_Customer();
    $customer->set_shipping_location('US', 'CO', '80111', 'Greenwood Village');
    $woocommerce->customer = $customer;

    $cart = new WC_Cart();
    $cart->add_to_cart($product->id);

    $woocommerce->cart = $cart;

    $this->assertTrue($woocommerce->cart->tax_total == 0);
    
    $tj = new WC_Taxjar_Integration();
    $tj->use_taxjar_total($woocommerce->cart);

    $this->assertTrue($woocommerce->cart->tax_total == 0.73);
  }
}