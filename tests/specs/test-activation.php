<?php
class TJ_WC_Activation extends WP_UnitTestCase {

	function test_objects_are_accessable() {
		global $woocommerce;

		$this->assertTrue( null != $woocommerce );
		$this->assertTrue( class_exists( 'WC_Taxjar' ) );
	}

}
