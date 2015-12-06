<?php
class TJ_WC_Actions extends WP_UnitTestCase {
  function test_use_taxjar_total() {
    global $woocommerce;

    TaxJar_Woocommerce_helper::prepare_woocommerce();
    $product = TaxJar_Helper_Product::get_test_product();
    $woocommerce->customer = TaxJar_Customer_helper::get_test_customer();
    $tj = new WC_Taxjar_Integration();

    $woocommerce->cart->add_to_cart($product->id);

    $this->assertTrue($woocommerce->cart->tax_total == 0);
    
    $tj->use_taxjar_total($woocommerce->cart);

    $this->assertTrue($woocommerce->cart->tax_total == 0.73);
  }
}