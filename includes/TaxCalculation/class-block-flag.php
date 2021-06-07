<?php
/**
 * Block Flag
 *
 * Used to determine if currently executing a request to the Woo REST API that requires tax calculation.
 *
 * @package TaxJar\WooCommerce\TaxCalculation
 */

namespace TaxJar\WooCommerce\TaxCalculation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block_Flag
 */
class Block_Flag {

	/**
	 * Stores whether request that requires calculation is being executed.
	 *
	 * @var bool
	 */
	private static $doing_calculation_request;

	/**
	 * Add filters.
	 */
	public static function init_hooks() {
		add_filter( 'rest_dispatch_request', 'TaxJar\WooCommerce\TaxCalculation\Block_Flag::maybe_set_block_flag', 10, 3 );
	}

	/**
	 * Set the flag if performing API request that requires calculation.
	 *
	 * @param mixed           $dispatch_result Dispatch result.
	 * @param WP_REST_Request $request         Request used to generate the response.
	 * @param string          $route           Route of the request.
	 *
	 * @return mixed
	 */
	public static function maybe_set_block_flag( $dispatch_result, $request, $route ) {
		if ( self::is_doing_request_that_requires_calculation( $route ) ) {
			self::doing_request_that_requires_calculation();
		} else {
			self::clear_flag();
		}

		return $dispatch_result;
	}

	/**
	 * Check if currently executing a request that requires calculation.
	 *
	 * @param string $route Request route.
	 *
	 * @return bool
	 */
	private static function is_doing_request_that_requires_calculation( $route ) {
		foreach ( self::get_routes_that_require_calculation() as $calculation_route ) {
			if ( strpos( $route, $calculation_route ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the routes the require tax calculation.
	 *
	 * @return array
	 */
	private static function get_routes_that_require_calculation() {
		return array(
			'/wc/store/cart',
			'/wc/store/checkout',
		);
	}

	/**
	 * Set the flag.
	 */
	public static function doing_request_that_requires_calculation() {
		self::$doing_calculation_request = true;
	}

	/**
	 * Check if flag is set.
	 *
	 * @return bool
	 */
	public static function was_block_initialized() {
		return true === self::$doing_calculation_request;
	}

	/**
	 * Clear the flag.
	 */
	public static function clear_flag() {
		self::$doing_calculation_request = false;
	}

}
