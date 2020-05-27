<?php

/**
 * Class TJ_WC_Tests_API_Calculation
 */
class TJ_WC_Tests_API_Calculation extends WP_UnitTestCase {

	public $tj;

	/**
	 * Runs before each test to setup data
	 */
	function setUp() {
		parent::setUp();

		TaxJar_Woocommerce_Helper::prepare_woocommerce();
		$this->tj = TaxJar();

		// Reset shipping origin
		TaxJar_Woocommerce_Helper::set_shipping_origin( $this->tj, array(
			'store_country'  => 'US',
			'store_state'    => 'CO',
			'store_street'   => '6060 S Quebec St',
			'store_postcode' => '80111',
			'store_city'     => 'Greenwood Village',
		) );

		update_option( 'woocommerce_currency', 'USD' );
	}

	/**
	 * Runs after each test to clean up
	 */
	function tearDown() {
		$this->update_taxjar_settings( array( 'api_calcs_enabled' => 'yes' ) );
		parent::tearDown();
	}

	/**
	 * Updates TaxJar integration settings
	 * @param array $opts - array containing new settings
	 */
	function update_taxjar_settings( $opts = array() ) {
		$current_settings = get_option( 'woocommerce_taxjar-integration_settings' );
		$new_settings = array_replace_recursive( $current_settings, $opts );
		update_option( 'woocommerce_taxjar-integration_settings', $new_settings );
		$this->tj->init_settings();
	}

	function test_is_api_calculation_enabled() {
		$this->assertTrue( $this->tj->api_calculation->is_api_calculation_enabled() );

		$this->update_taxjar_settings( array( 'api_calcs_enabled' => 'no' ) );
		$this->assertFalse( $this->tj->api_calculation->is_api_calculation_enabled() );

		$this->update_taxjar_settings( array( 'api_calcs_enabled' => null ) );
		$this->assertFalse( $this->tj->api_calculation->is_api_calculation_enabled() );

		$this->update_taxjar_settings( array( 'api_calcs_enabled' => '' ) );
		$this->assertFalse( $this->tj->api_calculation->is_api_calculation_enabled() );

		$this->update_taxjar_settings( array( 'api_calcs_enabled' => 'yes' ) );
		$this->assertTrue( $this->tj->api_calculation->is_api_calculation_enabled() );

	}

	function test_api_order_needs_tax_calculated() {
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, null, true ) );
		$this->assertFalse( $this->tj->api_calculation->api_order_needs_tax_calculated( null, null, false ) );

		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'billing' => 1 ), true ) );
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'shipping' => 1 ), true ) );
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'line_items' => 1 ), true ) );
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'shipping_lines' => 1 ), true ) );
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'fee_lines' => 1 ), true ) );
		$this->assertTrue( $this->tj->api_calculation->api_order_needs_tax_calculated( null, array( 'coupon_lines' => 1 ), true ) );
	}

}

