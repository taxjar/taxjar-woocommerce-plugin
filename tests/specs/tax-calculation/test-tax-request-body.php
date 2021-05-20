<?php

namespace TaxJar;
use WP_UnitTestCase;

class Test_TaxJar_Tax_Request_Body extends WP_UnitTestCase {

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_validate() {
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( '11111' );
		$request_body->set_to_country( 'US' );
		$request_body->add_line_item( 1 );
		$request_body->set_shipping_amount( 1 );

		$request_body->validate();
	}

	public function test_validate_with_no_country() {
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( '11111' );
		$request_body->add_line_item( 1 );
		$request_body->set_shipping_amount( 1 );

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );

		$request_body->validate();
	}

	public function test_validate_with_no_zip() {
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_country( 'US' );
		$request_body->add_line_item( 1 );
		$request_body->set_shipping_amount( 1 );

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );

		$request_body->validate();
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_validate_with_shipping_amount() {
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( '11111' );
		$request_body->set_to_country( 'US' );
		$request_body->set_shipping_amount( 1 );

		$request_body->validate();
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_validate_with_line_items() {
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( '11111' );
		$request_body->set_to_country( 'US' );
		$request_body->add_line_item( 1 );

		$request_body->validate();
	}

	public function test_validate_with_no_line_items_and_zero_shipping_amount(){
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( '11111' );
		$request_body->set_to_country( 'US' );
		$request_body->set_shipping_amount( 0 );

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );

		$request_body->validate();
	}

	public function test_validate_with_bad_zip(){
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_to_zip( 'AAAAA' );
		$request_body->set_to_country( 'US' );
		$request_body->add_line_item( 1 );
		$request_body->set_shipping_amount( 1 );

		$this->expectException( TaxJar_Tax_Calculation_Exception::class );

		$request_body->validate();
	}

	public function test_to_array() {
		$expected_array = array(
			'from_country' => 'US',
			'from_state'   => 'NY',
			'from_zip'     => '11111',
			'from_city'    => 'Test From City',
			'from_street'  => 'Test From Street',
			'to_country'   => 'US',
			'to_state'     => 'UT',
			'to_zip'       => '84651',
			'to_city'      => 'Test To City',
			'to_street'    => 'Test To Street',
			'shipping'     => 10,
			'plugin'       => 'woo',
			'amount'       => 0.0
		);

		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_from_country( $expected_array[ 'from_country' ] );
		$request_body->set_from_state( $expected_array[ 'from_state' ] );
		$request_body->set_from_zip( $expected_array[ 'from_zip' ] );
		$request_body->set_from_city( $expected_array[ 'from_city' ] );
		$request_body->set_from_street( $expected_array[ 'from_street' ] );
		$request_body->set_to_country( $expected_array[ 'to_country' ] );
		$request_body->set_to_state( $expected_array[ 'to_state' ] );
		$request_body->set_to_zip( $expected_array[ 'to_zip' ] );
		$request_body->set_to_city( $expected_array[ 'to_city' ] );
		$request_body->set_to_street( $expected_array[ 'to_street' ] );
		$request_body->set_shipping_amount( $expected_array[ 'shipping' ] );

		$this->assertEquals( $expected_array, $request_body->to_array() );
	}

	public function test_to_array_with_exemption_type() {
		$exemption = 'wholesale';
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_exemption_type( $exemption );
		$request_body_array = $request_body->to_array();

		$this->assertEquals( $exemption, $request_body_array[ 'exemption_type' ] );
	}

	public function test_to_array_with_customer_id() {
		$customer_id = 1;
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_customer_id( $customer_id );
		$request_body_array = $request_body->to_array();

		$this->assertEquals( $customer_id, $request_body_array[ 'customer_id' ] );
	}

	public function test_to_array_with_customer_id_zero() {
		$customer_id = 0;
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->set_customer_id( $customer_id );
		$request_body_array = $request_body->to_array();

		$this->assertArrayNotHasKey( 'customer_id',  $request_body_array );
	}

	public function test_to_array_with_line_item() {
		$line_item = 1;
		$request_body = new TaxJar_Tax_Request_Body();
		$request_body->add_line_item( $line_item );
		$request_body_array = $request_body->to_array();

		$this->assertEquals( array( $line_item ),  $request_body_array[ 'line_items' ] );
	}
}