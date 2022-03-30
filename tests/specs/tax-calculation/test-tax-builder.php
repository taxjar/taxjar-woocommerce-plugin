<?php

namespace TaxJar;

use TaxJar_Woocommerce_Helper;
use WC_Tax;
use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Tax_Builder extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		WC_Tax::create_tax_class( 'test_class' );
	}

	public function setUp() {
		TaxJar_Woocommerce_Helper::delete_existing_tax_rates();
	}

	public function tearDown() {
		TaxJar_Woocommerce_Helper::update_taxjar_settings( array( 'save_rates' => 'no' ) );
	}

	public function test_line_tax_has_correct_format() {
		$line_key = 'test';
		$tax_collectable = 1.1;
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_detail_line_item_stub = $this->createMock( Tax_Detail_Line_Item::class );
		$tax_detail_line_item_stub->method( 'get_tax_collectable' )->willReturn( $tax_collectable );
		$tax_details_stub->method( 'get_line_item' )->with( $line_key )->willReturn( $tax_detail_line_item_stub );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$tax = $tax_builder->get_line_tax( $line_key, Tax_Builder::TAX_RATE_ID );

		$this->assertEquals( [ Tax_Builder::TAX_RATE_ID => wc_add_number_precision( $tax_collectable ) ], $tax );
	}

	public function test_correct_woocommerce_rate_is_saved() {
		TaxJar_Woocommerce_Helper::update_taxjar_settings( array( 'save_rates' => 'yes' ) );
		$tax_percent = 10;
		$tax_class = 'test_class';
		$location = [
			'country' => 'US',
			'state' => 'UT',
			'city' => 'Payson',
			'zip' => '84651'
		];
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_details_stub->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_details_stub->method( 'get_location' )->willReturn( $location );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$created_rate_id = $tax_builder->build_woocommerce_tax_rate( $tax_percent, $tax_class );

		$found_rates = WC_Tax::find_rates( [
			'country'   => $location['country'],
			'state'     => $location['state'],
			'postcode'  => $location['zip'],
			'city'      => $location['city'],
			'tax_class' => $tax_class,
		] );
		$rate = reset( $found_rates );
		$rate_id = key( $found_rates );

		$this->assertEquals( $created_rate_id, $rate_id );
		$this->assertEquals( $tax_percent, $rate['rate'] );
	}

	public function test_correct_rate_is_built_without_saving() {
		TaxJar_Woocommerce_Helper::update_taxjar_settings( array( 'save_rates' => 'no' ) );
		$tax_percent = 10;
		$tax_class = 'test_class';
		$location = [
			'country' => 'US',
			'state' => 'UT',
			'city' => 'Payson',
			'zip' => '84651'
		];
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_details_stub->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_details_stub->method( 'get_location' )->willReturn( $location );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$created_rate_id = $tax_builder->build_woocommerce_tax_rate( $tax_percent, $tax_class );

		$found_rates = WC_Tax::find_rates( [
			'country'   => $location['country'],
			'state'     => $location['state'],
			'postcode'  => $location['zip'],
			'city'      => $location['city'],
			'tax_class' => $tax_class,
		] );

		$this->assertEmpty( $found_rates );
		$this->assertEquals( Tax_Builder::TAX_RATE_ID, $created_rate_id );
	}

	public function test_build_line_tax_from_rate() {
		$rate = .1;
		$taxable_amount = 100;
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$tax = $tax_builder->build_line_tax_from_rate( $rate, $taxable_amount, Tax_Builder::TAX_RATE_ID );

		$this->assertEquals( [ Tax_Builder::TAX_RATE_ID => $rate * $taxable_amount ], $tax );
	}
}
