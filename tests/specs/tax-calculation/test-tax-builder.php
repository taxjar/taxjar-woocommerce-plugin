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

		$tax = $tax_builder->get_line_tax( $line_key );

		$this->assertEquals( [ Tax_Builder::TAX_RATE_ID => wc_add_number_precision( $tax_collectable ) ], $tax );
	}

	public function test_correct_woocommerce_rate_saved_while_getting_line_tax() {
		TaxJar_Woocommerce_Helper::update_taxjar_settings( array( 'save_rates' => 'yes' ) );
		$line_key = 'test';
		$tax_collectable = 1.1;
		$tax_rate = .1;
		$location = [
			'country' => 'US',
			'state' => 'UT',
			'city' => 'Payson',
			'zip' => '84651'
		];
		$tax_class = 'test_class';
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_detail_line_item_stub = $this->createMock( Tax_Detail_Line_Item::class );
		$tax_detail_line_item_stub->method( 'get_tax_collectable' )->willReturn( $tax_collectable );
		$tax_detail_line_item_stub->method( 'get_tax_rate' )->willReturn( $tax_rate );
		$tax_details_stub->method( 'get_line_item' )->with( $line_key )->willReturn( $tax_detail_line_item_stub );
		$tax_details_stub->method( 'get_location' )->willReturn( $location );
		$tax_details_stub->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$tax = $tax_builder->get_line_tax( $line_key, $tax_class );

		$rates = WC_Tax::find_rates( [
			'country'   => $location['country'],
			'state'     => $location['state'],
			'postcode'  => $location['zip'],
			'city'      => $location['city'],
			'tax_class' => $tax_class,
		] );
		$rate = reset( $rates );
		$rate_id = key( $rates );

		$this->assertEquals( [ $rate_id => wc_add_number_precision( $tax_collectable ) ], $tax );
		$this->assertEquals( $tax_rate * 100, $rate['rate'] );
	}

	public function test_build_line_tax_from_rate() {
		$rate = .1;
		$taxable_amount = 100;
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$tax = $tax_builder->build_line_tax_from_rate( $rate, $taxable_amount );

		$this->assertEquals( [ Tax_Builder::TAX_RATE_ID => $rate * $taxable_amount ], $tax );
	}

	public function test_correct_woocommerce_rate_saved_while_building_line_tax_from_rate() {
		TaxJar_Woocommerce_Helper::update_taxjar_settings( array( 'save_rates' => 'yes' ) );
		$line_key = 'test';
		$tax_rate = .1;
		$taxable_amount = 100;
		$location = [
			'country' => 'US',
			'state' => 'UT',
			'city' => 'Payson',
			'zip' => '84651'
		];
		$tax_class = 'test_class';
		$tax_details_stub = $this->createMock( Tax_Details::class );
		$tax_detail_line_item_stub = $this->createMock( Tax_Detail_Line_Item::class );
		$tax_details_stub->method( 'get_line_item' )->with( $line_key )->willReturn( $tax_detail_line_item_stub );
		$tax_details_stub->method( 'get_location' )->willReturn( $location );
		$tax_details_stub->method( 'is_shipping_taxable' )->willReturn( false );
		$tax_builder = new Tax_Builder( $tax_details_stub );

		$tax = $tax_builder->build_line_tax_from_rate( $tax_rate, $taxable_amount, $tax_class );

		$rates = WC_Tax::find_rates( [
			'country'   => $location['country'],
			'state'     => $location['state'],
			'postcode'  => $location['zip'],
			'city'      => $location['city'],
			'tax_class' => $tax_class,
		] );
		$rate = reset( $rates );
		$rate_id = key( $rates );

		$this->assertEquals( [ $rate_id => $tax_rate * $taxable_amount ], $tax );
		$this->assertEquals( $tax_rate * 100, $rate['rate'] );
	}
}
