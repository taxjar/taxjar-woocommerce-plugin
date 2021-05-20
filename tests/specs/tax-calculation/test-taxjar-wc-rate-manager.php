<?php

namespace TaxJar;

use WP_UnitTestCase;
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_TaxJar_WC_Rate_Manager extends WP_UnitTestCase {

	public function setUp() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rates' );
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rate_locations' );
		wp_cache_init();

		WC_Tax::create_tax_class( 'Clothing Rate - 20010' );
	}

	private static $default_rate = array(
		'rate' => 10,
		'tax_class' => 'clothing-rate-20010',
		'freight_taxable' => true,
		'location' => array(
			'country' => 'US',
			'state' => 'UT',
			'city' => 'Test',
			'zip' => '11111'
		)
	);

	public function test_rate_creation() {
		$rate = TaxJar_WC_Rate_Manager::add_rate(
			self::$default_rate['rate'],
			self::$default_rate['tax_class'],
			self::$default_rate['freight_taxable'],
			self::$default_rate['location']
		);

		$rate_lookup = array(
			'country'   => self::$default_rate['location'] ['country'],
			'state'     => sanitize_key( self::$default_rate['location'] ['state'] ),
			'postcode'  => self::$default_rate['location'] ['zip'],
			'city'      => self::$default_rate['location'] ['city'],
			'tax_class' => self::$default_rate['tax_class']
		);

		$rates_in_database = WC_Tax::find_rates( $rate_lookup );

		$this->assertEquals( self::$default_rate['rate'], $rates_in_database[ $rate['id'] ]['rate'] );
		$this->assertEquals( 'yes', $rates_in_database[ $rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['compound'] );
	}

	public function test_rate_update() {
		$initial_rate = TaxJar_WC_Rate_Manager::add_rate(
			self::$default_rate['rate'],
			self::$default_rate['tax_class'],
			self::$default_rate['freight_taxable'],
			self::$default_rate['location']
		);

		$new_tax_rate = 20;
		$updated_rate = TaxJar_WC_Rate_Manager::add_rate(
			$new_tax_rate,
			self::$default_rate['tax_class'],
			self::$default_rate['freight_taxable'],
			self::$default_rate['location']
		);

		$rate_lookup = array(
			'country'   => self::$default_rate['location'] ['country'],
			'state'     => sanitize_key( self::$default_rate['location']['state'] ),
			'postcode'  => self::$default_rate['location'] ['zip'],
			'city'      => self::$default_rate['location'] ['city'],
			'tax_class' => self::$default_rate['tax_class']
		);

		$rates_in_database = WC_Tax::find_rates( $rate_lookup );

		$this->assertEquals( $initial_rate['id'], $updated_rate['id'] );
		$this->assertEquals( $new_tax_rate, $rates_in_database[ $initial_rate['id'] ]['rate'] );
		$this->assertEquals( 'yes', $rates_in_database[ $initial_rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $initial_rate['id'] ]['compound'] );
	}

	public function test_rate_with_nontaxable_shipping() {
		$freight_taxable = false;
		$rate = TaxJar_WC_Rate_Manager::add_rate(
			self::$default_rate['rate'],
			self::$default_rate['tax_class'],
			$freight_taxable,
			self::$default_rate['location']
		);

		$rate_lookup = array(
			'country'   => self::$default_rate['location'] ['country'],
			'state'     => sanitize_key( self::$default_rate['location']['state'] ),
			'postcode'  => self::$default_rate['location'] ['zip'],
			'city'      => self::$default_rate['location'] ['city'],
			'tax_class' => self::$default_rate['tax_class']
		);

		$rates_in_database = WC_Tax::find_rates( $rate_lookup );

		$this->assertEquals( self::$default_rate['rate'], $rates_in_database[ $rate['id'] ]['rate'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['shipping'] );
		$this->assertEquals( 'no', $rates_in_database[ $rate['id'] ]['compound'] );
	}
}
