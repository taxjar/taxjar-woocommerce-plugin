<?php
/**
 * Constants Manager
 *
 * Constants accessed through manager allow easier testing.
 * In tests constants can be set using the set_constant method and cleared using the clear_constants method.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Constants_Manager
 */
class Constants_Manager {

	/**
	 * A container for all defined constants.
	 *
	 * @var array.
	 */
	public static $set_constants = array();

	/**
	 * Checks if constant is true.
	 * First checks within manager and then globally.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool
	 */
	public static function is_true( $name ) {
		return self::is_defined( $name ) && self::get_constant( $name );
	}

	/**
	 * Checks if a constant has been defined.
	 * First checks within the manager and then globally.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool
	 */
	public static function is_defined( $name ) {
		return array_key_exists( $name, self::$set_constants ) ? true : defined( $name );
	}

	/**
	 * Gets the value of a constant.
	 * First gets the value from within the manager, and then if not found gets the global constant value.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return mixed null The value of the constant or null if it does not exist.
	 */
	public static function get_constant( $name ) {
		if ( array_key_exists( $name, self::$set_constants ) ) {
			return self::$set_constants[ $name ];
		}

		if ( defined( $name ) ) {
			return constant( $name );
		}

		return null;
	}

	/**
	 * Sets the value of a constant within the manager.
	 *
	 * @param string $name The name of the constant.
	 * @param string $value The value of the constant.
	 */
	public static function set_constant( $name, $value ) {
		self::$set_constants[ $name ] = $value;
	}

	/**
	 * Removes a single constant that has been set in the manager.
	 *
	 * @param string $name The name of the constant.
	 *
	 * @return bool Whether the constant was removed.
	 */
	public static function clear_single_constant( $name ) {
		if ( ! array_key_exists( $name, self::$set_constants ) ) {
			return false;
		}

		unset( self::$set_constants[ $name ] );

		return true;
	}

	/**
	 * Clears all constants set within the manager.
	 */
	public static function clear_constants() {
		self::$set_constants = array();
	}
}
