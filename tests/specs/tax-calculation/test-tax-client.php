<?php

namespace TaxJar;

use WP_UnitTestCase;
use WP_Error;
use Exception;

class Test_Tax_Client extends WP_UnitTestCase {

	private $response;

	public function setUp(): void {
		parent::setUp();
		$this->response = false;
		add_filter( 'pre_http_request', array( $this, 'override_http_response' ), 10, 3 );
	}

	public function tearDown(): void {
		parent::tearDown();
		remove_filter( 'pre_http_request', array( $this, 'override_http_response' ), 10 );
	}

	public function override_http_response( $preempt, $request, $url ) {
		return $this->response;
	}

	public function test_wp_error_response() {
		$this->response = new WP_Error();
		$request_body   = $this->build_tax_request_body();
		$tax_client     = new Tax_Client();

		$this->expectException( Exception::class );
		$tax_details = $tax_client->get_taxes( $request_body );
	}

	public function test_non_okay_response() {
		$this->response = array(
			'response' => array(
				'code' => 500,
			),
		);
		$request_body   = $this->build_tax_request_body();
		$tax_client     = new Tax_Client();

		$this->expectException( Exception::class );
		$tax_details = $tax_client->get_taxes( $request_body );
	}

	public function test_ok_response() {
		$taxjar_response = array(
			'response' => array(
				'code' => 200,
			),
			'body'     => wp_json_encode(
				(object) array(
					'tax' => (object) array(
						'amount_to_collect' => 0,
						'breakdown'         => (object) array(
							'combined_tax_rate' => 0,
						),
						'freight_taxable'   => true,
						'has_nexus'         => true,
					),
				)
			),
		);
		$this->response  = $taxjar_response;
		$request_body    = $this->build_tax_request_body();
		$tax_client      = new Tax_Client();
		$tax_details     = $tax_client->get_taxes( $request_body );

		$this->assertInstanceOf( Tax_Details::class, $tax_details );
	}

	private function build_tax_request_body() {
		$request_body = new Tax_Request_Body();
		$request_body->set_customer_id( 1 );
		$request_body->set_to_country( 'US' );
		$request_body->set_to_state( 'UT' );
		$request_body->set_to_zip( '84651' );
		$request_body->set_to_street( '123 Main St' );
		$request_body->set_to_city( 'Payson' );
		$request_body->set_from_country( 'US' );
		$request_body->set_from_state( 'UT' );
		$request_body->set_from_zip( '84651' );
		$request_body->set_from_street( '123 Main St' );
		$request_body->set_from_city( 'Payson' );
		$request_body->set_shipping_amount( 10 );
		$request_body->add_line_item(
			array(
				'id'               => '1-1',
				'quantity'         => 1,
				'product_tax_code' => '',
				'unit_price'       => 100,
				'discount'         => 0,
			)
		);

		return $request_body;
	}
}
