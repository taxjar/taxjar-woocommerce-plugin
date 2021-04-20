<?php

class Test_TaxJar_Cache extends WP_UnitTestCase {

	public function test_set_and_read_with_hashed_key() {
		$key_data = array( 'test' => 'test' );
		$value_to_set = array( 'value' => 'test' );

		$cache = new TaxJar_Cache( 60, 'prefix_' );
		$cache->set_with_hashed_key( $key_data, $value_to_set );
		$retrieved_value = $cache->read_hashed_value( $key_data );

		$this->assertEquals( $value_to_set, $retrieved_value );
	}

	public function test_set_and_get_with_mismatching_hashed_key() {
		$initial_key_data = array( 'test' => '1' );
		$mismatching_key_data = array( 'test' => '2' );
		$value_to_set = array( 'value' => 'test' );

		$cache = new TaxJar_Cache( 60, 'prefix_' );
		$cache->set_with_hashed_key( $initial_key_data, $value_to_set );
		$retrieved_value = $cache->read_hashed_value( $mismatching_key_data );

		$this->assertFalse( $retrieved_value );
	}

	public function test_delete_cached_value() {
		$key_data = array( 'test' => '3' );
		$value_to_set = array( 'value' => 'test' );

		$cache = new TaxJar_Cache( 60, 'prefix_' );
		$cache->set_with_hashed_key( $key_data, $value_to_set );
		$retrieved_value = $cache->read_hashed_value( $key_data );

		$this->assertEquals( $value_to_set, $retrieved_value );

		$cache->delete_hashed_value( $key_data );

		$retrieved_value = $cache->read_hashed_value( $key_data );

		$this->assertFalse( $retrieved_value );
	}

	public function test_contains_cached_value() {
		$key_data = array( 'test' => '4' );
		$value_to_set = array( 'value' => 'test' );

		$cache = new TaxJar_Cache( 60, 'prefix_' );
		$cache->set_with_hashed_key( $key_data, $value_to_set );
		$this->assertTrue( $cache->contains_hashed_value( $key_data ) );

		$cache->delete_hashed_value( $key_data );
		$this->assertFalse( $cache->contains_hashed_value( $key_data ) );
	}
}
