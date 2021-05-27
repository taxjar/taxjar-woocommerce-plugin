<?php

namespace TaxJar;

use WP_UnitTestCase;
use WC_Tax;
use TaxJar_Woocommerce_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Rate_Manager extends WP_UnitTestCase {

	private $rate;
	private $tax_class;
	private $freight_taxable;
	private $location;

	public function setUp() {
		TaxJar_Woocommerce_Helper::delete_existing_tax_rates();
		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );

		$this->rate            = 10;
		$this->tax_class       = 'clothing-rate-20010';
		$this->freight_taxable = true;
		$this->location        = array(
			'country' => 'US',
			'state'   => 'UT',
			'city'    => 'Test',
			'zip'     => '11111',
		);
	}

	public function test_rate_creation() {
		$rate              = $this->build_rate();
		$rates_in_database = $this->lookup_rates();

		$this->assertEquals( $this->rate, $rates_in_database[ $rate['id'] ]['rate'] );
		$this->assertEquals( 'yes', $rates_in_database[ $rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['compound'] );
	}

	public function test_rate_update() {
		$initial_rate      = $this->build_rate();
		$this->rate        = 20;
		$updated_rate      = $this->build_rate();
		$rates_in_database = $this->lookup_rates();

		$this->assertEquals( $initial_rate['id'], $updated_rate['id'] );
		$this->assertEquals( $this->rate, $rates_in_database[ $initial_rate['id'] ]['rate'] );
		$this->assertEquals( 'yes', $rates_in_database[ $initial_rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $initial_rate['id'] ]['compound'] );
	}

	public function test_rate_with_nontaxable_shipping() {
		$this->freight_taxable = false;
		$rate                  = $this->build_rate();
		$rates_in_database     = $this->lookup_rates();

		$this->assertEquals( $this->rate, $rates_in_database[ $rate['id'] ]['rate'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['compound'] );
	}

	private function build_rate() {
		return Rate_Manager::add_rate(
			$this->rate,
			$this->tax_class,
			$this->freight_taxable,
			$this->location
		);
	}

	private function lookup_rates() {
		$rate_lookup = array(
			'country'   => $this->location['country'],
			'state'     => sanitize_key( $this->location['state'] ),
			'postcode'  => $this->location['zip'],
			'city'      => $this->location['city'],
			'tax_class' => $this->tax_class,
		);

		return WC_Tax::find_rates( $rate_lookup );
	}
}
