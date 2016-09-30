<?php
class TJ_WC_Class_Nexus extends WP_UnitTestCase {

  function test_get_or_update_cached_nexus() {
    $tj = new WC_Taxjar_Integration();
    $tj_nexus = new WC_Taxjar_Nexus($tj);

    delete_transient('wc_taxjar_nexus_list');
    $this->assertEquals(get_transient('wc_taxjar_nexus_list'), false);
    $tj_nexus->get_or_update_cached_nexus();
    $this->assertTrue(count(get_transient('wc_taxjar_nexus_list')) > 0);
  }

  function test_has_nexus_check_uses_base_address() {
    update_option('woocommerce_default_country', 'US:XO');
    $tj = new WC_Taxjar_Integration();
    $tj_nexus = new WC_Taxjar_Nexus($tj);
    $this->assertTrue($tj_nexus->has_nexus_check('US', 'XO'));
  }

  function test_has_nexus_check_uses_nexus_list() {
    $tj = new WC_Taxjar_Integration();
    $tj_nexus = new WC_Taxjar_Nexus($tj);
    $this->assertTrue($tj_nexus->has_nexus_check('US', 'CO'));
  }

  function test_works_when_only_using_counry() {
    update_option('woocommerce_default_country', 'DE:');
    $tj = new WC_Taxjar_Integration();
    $tj_nexus = new WC_Taxjar_Nexus($tj);
    $this->assertTrue($tj_nexus->has_nexus_check('DE'));
  }

  function test_returns_false_if_not_shipping_to_nexus_area() {
    update_option('woocommerce_default_country', 'US:CO');
    $tj = new WC_Taxjar_Integration();
    $tj_nexus = new WC_Taxjar_Nexus($tj);
    $this->assertFalse($tj_nexus->has_nexus_check('US', 'XO'));
  }
}
