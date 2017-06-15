<?php
class TJ_WC_Class_Nexus extends WP_UnitTestCase {

  function setUp() {
    $this->tj = new WC_Taxjar_Integration();
    $this->tj_nexus = new WC_Taxjar_Nexus( $this->tj );
    parent::setUp();
  }

  function test_get_or_update_cached_nexus() {
    delete_transient( 'wc_taxjar_nexus_list' );
    $this->assertEquals( get_transient( 'wc_taxjar_nexus_list' ), false );
    $this->tj_nexus->get_or_update_cached_nexus();
    $this->assertTrue( count( get_transient( 'wc_taxjar_nexus_list' ) ) > 0 );
  }

  function test_get_or_update_cached_nexus_uses_correct_timeout() {
    delete_transient( 'wc_taxjar_nexus_list' );
    $this->assertEquals( get_transient( 'wc_taxjar_nexus_list' ), false );
    $this->tj_nexus->get_or_update_cached_nexus();
    $transient = get_transient( 'timeout_wc_taxjar_nexus_list' );
    $this->assertEquals( $transient, time() + 0.5 * DAY_IN_SECONDS );
  }

  function test_or_get_update_cached_nexus_expiration() {
    delete_transient( 'wc_taxjar_nexus_list' );
    $this->assertEquals( get_transient( 'wc_taxjar_nexus_list' ), false );
    set_transient( 'wc_taxjar_nexus_list', array(), -0.5 * DAY_IN_SECONDS );
    $this->tj_nexus->get_or_update_cached_nexus();
    $transient = get_transient( 'timeout_wc_taxjar_nexus_list' );
    $this->assertEquals( $transient, time() + 0.5 * DAY_IN_SECONDS );
    $this->assertTrue( count( get_transient( 'wc_taxjar_nexus_list' ) ) > 0 );
  }

  function test_has_nexus_check_uses_base_address() {
    update_option( 'woocommerce_default_country', 'US:XO' );
    $this->assertTrue( $this->tj_nexus->has_nexus_check( 'US', 'XO' ) );
  }

  function test_has_nexus_check_uses_nexus_list() {
    $this->assertTrue( $this->tj_nexus->has_nexus_check( 'US', 'CO' ) );
  }

  function test_works_when_only_using_counry() {
    update_option( 'woocommerce_default_country', 'DE:' );
    $this->assertTrue( $this->tj_nexus->has_nexus_check( 'DE' ) );
  }

  function test_returns_false_if_not_shipping_to_nexus_area() {
    update_option( 'woocommerce_default_country', 'US:CO' );
    $this->assertFalse( $this->tj_nexus->has_nexus_check( 'US', 'XO' ) );
  }
}
