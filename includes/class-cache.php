<?php
/**
 * TaxJar Cache
 *
 * Caches using WordPress transients.
 *
 * @package TaxJar/Classes
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TaxJar_Cache
 */
class Cache implements Cache_Interface {

	/**
	 * Prefix to cache key, used when dynamically creating key.
	 *
	 * @var string
	 */
	private $key_prefix;

	/**
	 * Number of seconds to cache expiration.
	 *
	 * @var integer
	 */
	private $seconds_to_expiration;

	/**
	 * TaxJar_Cache constructor.
	 *
	 * @param integer $seconds_to_expiration - Number of seconds to cache expiration.
	 * @param string  $key_prefix - Cache key prefix.
	 */
	public function __construct( $seconds_to_expiration, $key_prefix = '' ) {
		$this->key_prefix            = $key_prefix;
		$this->seconds_to_expiration = $seconds_to_expiration;
	}

	/**
	 * Enters value into cache using dynamically created key.
	 *
	 * @param mixed $key_data - Value used to dynamically create cache key.
	 * @param mixed $value - Value to cache.
	 */
	public function set_with_hashed_key( $key_data, $value ) {
		$key = $this->create_hash_key( $key_data );
		$this->set( $key, $value );
	}

	/**
	 * Dynamically create cache key.
	 *
	 * @param mixed $data - Value used to dynamically create the key.
	 *
	 * @return string - Cache key.
	 */
	private function create_hash_key( $data ) {
		$json_data = wp_json_encode( $data );
		return $this->key_prefix . hash( 'md5', $json_data );
	}

	/**
	 * Inserts a values into the cache using specified key.
	 *
	 * @param string $key - Cache key.
	 * @param mixed  $data - Value to store in cache.
	 */
	public function set( $key, $data ) {
		set_transient( $key, $data, $this->seconds_to_expiration );
	}

	/**
	 * Gets value from cache using dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 *
	 * @return mixed|boolean - Value in cache, or false if not found.
	 */
	public function read_hashed_value( $key_data ) {
		$key = $this->create_hash_key( $key_data );
		return $this->read( $key );
	}

	/**
	 * Gets a value form the cache.
	 *
	 * @param string $key - Cache key.
	 *
	 * @return mixed|boolean - Value in cache, or false if not found.
	 */
	public function read( $key ) {
		return get_transient( $key );
	}

	/**
	 * Deletes value in cache using dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 */
	public function delete_hashed_value( $key_data ) {
		$key = $this->create_hash_key( $key_data );
		$this->delete( $key );
	}

	/**
	 * Deletes value in cache.
	 *
	 * @param string $key - Cache key.
	 */
	public function delete( $key ) {
		delete_transient( $key );
	}

	/**
	 * Checks if cache contains value for dynamically generated key.
	 *
	 * @param mixed $key_data - Value used to dynamically generate the key.
	 *
	 * @return bool - True if cache contains key.
	 */
	public function contains_hashed_value( $key_data ) {
		$key = $this->create_hash_key( $key_data );
		return $this->contains( $key );
	}

	/**
	 * Checks if cache contains key.
	 *
	 * @param string $key - Cache key.
	 *
	 * @return bool - True if cache contains key.
	 */
	public function contains( $key ) {
		$cache_value = $this->read( $key );
		return false !== $cache_value;
	}
}
