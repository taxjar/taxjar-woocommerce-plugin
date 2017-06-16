<?php
class TJ_WC_Settings extends WP_UnitTestCase {

	function test_taxjar_settings() {
		$tj = new WC_Taxjar_Integration();
		$this->assertNotNull( $tj->api_token );
		$this->assertTrue( 'yes' == $tj->enabled );
		$this->assertTrue( 'yes' == $tj->download_orders->taxjar_download );
	}

}
