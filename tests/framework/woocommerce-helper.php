<?php

class TaxJar_Woocommerce_helper {
  public static function prepare_woocommerce() {
    global $woocommerce;

    $woocommerce->product_factory = new WC_Product_Factory();
    $session_class = apply_filters( 'woocommerce_session_handler', 'WC_Session_Handler' );
    $woocommerce->session  = new $session_class();
    $woocommerce->cart = new WC_Cart();
  }
}