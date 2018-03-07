<?php
class TJ_WC_Class_Nexus extends WP_UnitTestCase {

	function setUp() {
		TaxJar_Woocommerce_Helper::prepare_woocommerce();

		$this->tj = new WC_Taxjar_Integration();
		$this->tj_nexus = new WC_Taxjar_Nexus( $this->tj );
		$this->cache_key = 'tlc__' . md5( 'get_nexus_from_cache' );

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country' => 'US',
			'store_state' => 'CO',
			'store_zip' => '80111',
			'store_city' => 'Greenwood Village',
		) );

		parent::setUp();
	}

	function test_get_or_update_cached_nexus() {
		delete_transient( $this->cache_key );
		$this->assertEquals( get_transient( $this->cache_key ), false );
		$this->tj_nexus->get_or_update_cached_nexus();
		$this->assertTrue( count( get_transient( $this->cache_key ) ) > 0 );
	}

	function test_get_or_update_cached_nexus_uses_correct_timeout() {
		delete_transient( $this->cache_key );
		$this->assertEquals( get_transient( $this->cache_key ), false );
		$this->tj_nexus->get_or_update_cached_nexus();
		$transient = get_transient( $this->cache_key );
		$this->assertEquals( $transient[0], time() + 0.5 * DAY_IN_SECONDS );
	}

	function test_or_get_update_cached_nexus_expiration() {
		delete_transient( $this->cache_key );
		$this->assertEquals( get_transient( $this->cache_key ), false );
		set_transient( $this->cache_key, array(), -0.5 * DAY_IN_SECONDS );
		$this->tj_nexus->get_or_update_cached_nexus();
		$transient = get_transient( $this->cache_key );
		$this->assertEquals( $transient[0], time() + 0.5 * DAY_IN_SECONDS );
		$this->assertTrue( count( get_transient( $this->cache_key ) ) > 0 );
	}

	function test_or_get_update_cached_nexus_valid() {
		delete_transient( $this->cache_key );
		$nexus_list = $this->tj_nexus->get_or_update_cached_nexus();
		$this->assertTrue( count( $nexus_list ) > 0 );
	}

	function test_or_get_update_cached_nexus_unauthorized() {
		delete_transient( $this->cache_key );
		$this->tj->settings['api_token'] = 'INVALID_OR_EXPIRED_API_TOKEN';
		$nexus_list = $this->tj_nexus->get_or_update_cached_nexus();
		$this->assertTrue( count( $nexus_list ) == 0 );
	}

	function test_or_get_update_cached_nexus_stays_cached_on_unauthorized() {
		delete_transient( $this->cache_key );
		$this->tj->settings['api_token'] = 'INVALID_OR_EXPIRED_API_TOKEN';
		$nexus_list = $this->tj_nexus->get_or_update_cached_nexus();
		$transient = get_transient( $this->cache_key );
		$this->assertTrue( count( $nexus_list ) == 0 );

		// Ensure nexus response is cached on 401 / 403 errors
		// Requires manually syncing nexus addresses from admin to resolve
		for ( $x = 0; $x < 5; $x++ ) {
			$nexus_list = $this->tj_nexus->get_or_update_cached_nexus();
			$this->assertEquals( $transient[0], time() + 0.5 * DAY_IN_SECONDS );
			$this->assertTrue( count( get_transient( $this->cache_key ) ) > 0 );
		}
	}

	function test_or_get_update_cached_nexus_force_updates_on_unauthorized() {
		delete_transient( $this->cache_key );
		$original_api_token = $this->tj->settings['api_token'];
		$this->tj->settings['api_token'] = 'INVALID_OR_EXPIRED_API_TOKEN';
		$nexus_list = $this->tj_nexus->get_or_update_cached_nexus();
		$this->assertTrue( count( $nexus_list ) == 0 );
		$this->tj->settings['api_token'] = $original_api_token;
		$nexus_list = $this->tj_nexus->get_or_update_cached_nexus( true );
		$this->assertTrue( count( $nexus_list ) > 0 );
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
