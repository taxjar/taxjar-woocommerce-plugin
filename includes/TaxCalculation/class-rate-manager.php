<?php
/**
 * Rate Manager
 *
 * Creates and inserts WooCommerce tax rates into database.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use TaxJar_Settings;
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rate_Manager
 */
class Rate_Manager {

	/**
	 * Adds a WooCommerce tax rate to the database.
	 *
	 * @param integer $rate Tax rate value.
	 * @param string  $tax_class Product tax class.
	 * @param int     $freight_taxable Is shipping taxable.
	 * @param array   $location Location fields.
	 *
	 * @return array
	 */
	public static function add_rate( $rate, $tax_class, $freight_taxable = 1, $location = array() ) {
		$new_tax_rate       = self::prepare_tax_rate( $rate, $tax_class, $freight_taxable, $location );
		$existing_tax_rate  = self::get_existing_rate( $tax_class, $location );
		$rate_id            = self::create_or_update_tax_rate( $existing_tax_rate, $new_tax_rate, $location );
		$new_tax_rate['id'] = $rate_id;
		return $new_tax_rate;
	}

	/**
	 * Prepares tax rate for to be inserted/updated.
	 *
	 * @param integer $rate Tax rate value.
	 * @param string  $tax_class Product tax class.
	 * @param int     $freight_taxable Is shipping taxable.
	 * @param array   $location Location fields.
	 *
	 * @return array
	 */
	private static function prepare_tax_rate( $rate, $tax_class, $freight_taxable, $location ) {
		return array(
			'tax_rate_country'  => $location['country'],
			'tax_rate_state'    => $location['state'],
			'tax_rate_name'     => sprintf( '%s Tax', $location['state'] ),
			'tax_rate_priority' => 1,
			'tax_rate_compound' => false,
			'tax_rate_shipping' => $freight_taxable,
			'tax_rate'          => $rate,
			'tax_rate_class'    => $tax_class,
		);
	}

	/**
	 * Retrieves a rate matching location and tax class if it exists.
	 *
	 * @param string $tax_class Product tax class.
	 * @param array  $location Location fields.
	 *
	 * @return array
	 */
	private static function get_existing_rate( $tax_class, $location ) {
		$rate_lookup = self::prepare_rate_lookup( $tax_class, $location );
		$wc_rate     = WC_Tax::find_rates( $rate_lookup );
		return $wc_rate;
	}

	/**
	 * Prepares rate lookup fields.
	 *
	 * @param string $tax_class Product tax class.
	 * @param array  $location Location fields.
	 *
	 * @return array
	 */
	private static function prepare_rate_lookup( $tax_class, $location ) {
		return array(
			'country'   => $location['country'],
			'state'     => sanitize_key( $location['state'] ),
			'postcode'  => $location['zip'],
			'city'      => $location['city'],
			'tax_class' => $tax_class,
		);
	}

	/**
	 * Creates a new tax rate if one doesn't already exist, otherwise updates an existing rate.
	 *
	 * @param array $existing_tax_rate Existing rate matching location and tax class.
	 * @param array $new_tax_rate Tax rate containing data to be inserted or updated.
	 * @param array $location Location fields.
	 *
	 * @return int|mixed
	 */
	private static function create_or_update_tax_rate( $existing_tax_rate, $new_tax_rate, $location ) {
		if ( self::wc_rate_already_exists( $existing_tax_rate ) ) {
			$rate_id = self::update_tax_rate( key( $existing_tax_rate ), $new_tax_rate );
		} else {
			$rate_id = self::create_tax_rate( $new_tax_rate, $location );
		}
		return $rate_id;
	}

	/**
	 * Checks if rate that was retrieved exists.
	 *
	 * @param array $wc_rate Rate.
	 *
	 * @return bool
	 */
	private static function wc_rate_already_exists( $wc_rate ) {
		return ! empty( $wc_rate );
	}

	/**
	 * Updates tax rate in database.
	 *
	 * @param integer $rate_id Rate ID.
	 * @param array   $tax_rate Tax rate fields.
	 *
	 * @return mixed
	 */
	private static function update_tax_rate( $rate_id, $tax_rate ) {
		WC_Tax::_update_tax_rate( $rate_id, $tax_rate );
		return $rate_id;
	}

	/**
	 * Inserts tax rate in database.
	 *
	 * @param array $tax_rate Tax rate fields.
	 * @param array $location location fields.
	 *
	 * @return int Rate ID.
	 */
	private static function create_tax_rate( $tax_rate, $location ) {
		$rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
		WC_Tax::_update_tax_rate_postcodes( $rate_id, wc_clean( $location['zip'] ) );
		WC_Tax::_update_tax_rate_cities( $rate_id, wc_clean( $location['city'] ) );
		return $rate_id;
	}
}
