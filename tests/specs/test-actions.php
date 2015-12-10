<?php
class TJ_WC_Actions extends WP_UnitTestCase {
  function test_use_taxjar_total() {
    global $woocommerce;
    TaxJar_Woocommerce_helper::prepare_woocommerce();
    $tj = new WC_Taxjar_Integration();

    $this->assertTrue($woocommerce->cart->tax_total == 0);

    do_action('woocommerce_calculate_totals', $woocommerce->cart);    

    $this->assertTrue($woocommerce->cart->tax_total == 0.73);
  }
}