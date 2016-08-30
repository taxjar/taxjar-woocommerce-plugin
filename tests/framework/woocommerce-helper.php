<?php

class TaxJar_Woocommerce_helper {
  public static function prepare_woocommerce() {
    global $woocommerce;

    $woocommerce->product_factory = new WC_Product_Factory();
    $woocommerce->order_factory = new WC_Order_Factory();
    $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
    $woocommerce->session  = new $session_class();
    $woocommerce->cart = new WC_Cart();

    $woocommerce->customer = TaxJar_Customer_helper::get_test_customer();
    $woocommerce->cart->add_to_cart(TaxJar_Helper_Product::get_test_product()->id);
  }
}
