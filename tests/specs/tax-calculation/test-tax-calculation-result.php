<?php

namespace TaxJar;

use WP_UnitTestCase;

class Test_Tax_Calculation_Result extends WP_UnitTestCase {

	public function test_correct_data_when_instantiated_from_json() {
		$context = 'test_context';
		$request = 'test_request';
		$response = 'test_response';
		$error_message = 'error';
		$success = true;
		$result = new Tax_Calculation_Result();
		$result->set_context( $context );
		$result->set_error_message( $error_message );
		$result->set_raw_response( $response );
		$result->set_raw_request( $request );
		$result->set_success( $success );
		$result_json = $result->to_json();

		$new_result = Tax_Calculation_Result::from_json_string( $result_json );

		$this->assertEquals( $context, $new_result->get_context() );
		$this->assertEquals( $request, $new_result->get_raw_request() );
		$this->assertEquals( $response, $new_result->get_raw_response() );
		$this->assertEquals( $error_message, $new_result->get_error_message() );
		$this->assertEquals( $success, $new_result->get_success() );
	}

	public function test_data_when_instantiated_from_invalid_json() {
		$json = wp_json_encode( [] );

		$result = Tax_Calculation_Result::from_json_string( $json );

		$this->assertEquals( '', $result->get_context() );
		$this->assertEquals( '', $result->get_raw_request() );
		$this->assertEquals( '', $result->get_raw_response() );
		$this->assertEquals( '', $result->get_error_message() );
		$this->assertEquals( false, $result->get_success() );
	}



}
