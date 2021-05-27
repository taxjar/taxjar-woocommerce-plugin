<?php
/**
 * Cache Interface
 *
 * @package TaxJar\Interface
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

interface Cache_Interface {

	/**
	 * Enters value into cache using dynamically created key.
	 *
	 * @param mixed $key_data - Value used to dynamically create cache key.
	 * @param mixed $value - Value to cache.
	 */
	public function set_with_hashed_key( $key_data, $value );

	/**
	 * Inserts a values into the cache using specified key.
	 *
	 * @param string $key - Cache key.
	 * @param mixed  $data - Value to store in cache.
	 */
	public function set( $key, $data );

	/**
	 * Gets value from cache using dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 *
	 * @return mixed|boolean - Value in cache, or false if not found.
	 */
	public function read_hashed_value( $key_data );

	/**
	 * Gets a value form the cache.
	 *
	 * @param string $key - Cache key.
	 *
	 * @return mixed|boolean - Value in cache, or false if not found.
	 */
	public function read( $key );

	/**
	 * Deletes value in cache using dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 */
	public function delete_hashed_value( $key_data );

	/**
	 * Deletes value in cache.
	 *
	 * @param string $key - Cache key.
	 */
	public function delete( $key );

	/**
	 * Checks if cache contains value for dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 *
	 * @return bool - True if cache contains key.
	 */
	public function contains_hashed_value( $key_data );

	/**
	 * Checks if cache contains key.
	 *
	 * @param string $key - Cache key.
	 *
	 * @return bool - True if cache contains key.
	 */
	public function contains( $key );
}
