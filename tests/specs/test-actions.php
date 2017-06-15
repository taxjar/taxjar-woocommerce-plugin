<?php
class TJ_WC_Actions extends WP_UnitTestCase {
  function test_use_taxjar_total() {
    global $woocommerce;
    TaxJar_Woocommerce_helper::prepare_woocommerce();

    $tj = new WC_Taxjar_Integration();

    do_action( 'woocommerce_calculate_totals', $woocommerce->cart );

    $this->assertTrue( $woocommerce->cart->get_taxes_total() != 0 );
  }

  function test_the_correct_taxes_are_set() {
    global $woocommerce;
    TaxJar_Woocommerce_helper::prepare_woocommerce();
    $tj = new WC_Taxjar_Integration();

    $woocommerce->shipping->shipping_total = 5;

    do_action( 'woocommerce_calculate_totals', $woocommerce->cart );

    $this->assertEquals( $woocommerce->cart->tax_total, 0.4, '', 0.001 );
    $this->assertEquals( $woocommerce->cart->shipping_tax_total, 0.2, '', 0.001 );
    $this->assertEquals( array_values( $woocommerce->cart->shipping_taxes )[0], 0.2, '', 0.001 );
    $this->assertEquals( $woocommerce->cart->get_taxes_total(), 0.6, '', 0.001 );
  }
}
