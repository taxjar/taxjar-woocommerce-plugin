<?php

namespace TaxJar;

use TaxJar_Tax_Calculation;
use WP_UnitTestCase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Test_Get_Tax_Code extends WP_UnitTestCase {

	/**
	 * @dataProvider tax_class_provider
	 */
	public function test_correct_ptcs_are_parsed_from_tax_class( $tax_class, $expected_tax_code ) {
		$parsed_code = TaxJar_Tax_Calculation::get_tax_code_from_class( $tax_class );

		$this->assertEquals( $expected_tax_code, $parsed_code );
	}

	public function tax_class_provider() {
		return [
			[ 'test', '' ],
			[ 'test-10001', '10001' ],
			[ 'test-111', '' ],
			[ '10122100A0000', '10122100A0000' ],
			[ 'test-10122100A0000', '10122100A0000' ],
			[ 'test-10122100a0000', '10122100A0000' ],
			[ '', '' ],
			[ '10001', '10001' ],
			[ '1111', '' ],
			[ '10122100A', '' ],
			[ '0', '' ],
		];
	}
}
