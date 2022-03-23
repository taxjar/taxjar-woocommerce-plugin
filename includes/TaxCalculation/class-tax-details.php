<?php
/**
 * Tax Details
 *
 * Contains all the necessary tax rates to be applied.
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Tax_Details
 */
class Tax_Details {

	/**
	 * Stores all the tax details of line items.
	 *
	 * @var array[Tax_Detail_Line_Item]
	 */
	private $line_items;

	/**
	 * Stores whether shipping is taxable or not.
	 *
	 * @var bool
	 */
	private $freight_taxable;

	/**
	 * Stores whether transaction has nexus or not.
	 *
	 * @var bool
	 */
	private $has_nexus;

	/**
	 * Stores shipping tax rate.
	 *
	 * @var float
	 */
	private $shipping_tax_rate;

	/**
	 * Stores average tax rate of transaction.
	 *
	 * @var float
	 */
	private $rate;

	/**
	 * Stores ship to country code of transaction.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * Stores ship to state code of transaction.
	 *
	 * @var string
	 */
	private $state;

	/**
	 * Stores ship to city of transaction.
	 *
	 * @var string
	 */
	private $city;

	/**
	 * Stores ship to zip code of transaction.
	 *
	 * @var string
	 */
	private $zip;

	/**
	 * Stores tax response from TaxJar API.
	 *
	 * @var array
	 */
	private $raw_response;

	/**
	 * Tax_Details constructor.
	 *
	 * @param array $tax_response Response from TaxJar API.
	 */
	public function __construct( $tax_response ) {
		$this->raw_response    = $tax_response;
		$response_body         = json_decode( $tax_response['body'] );
		$this->has_nexus       = $response_body->tax->has_nexus;
		$this->freight_taxable = $response_body->tax->freight_taxable;
		$this->add_line_items( $response_body );
		$this->set_shipping_rate( $response_body );
		$this->set_rate( $response_body );
	}

	/**
	 * Adds line item details.
	 *
	 * @param mixed $response_body Body of response from TaxJar API.
	 */
	private function add_line_items( $response_body ) {
		$this->line_items = array();

		if ( ! empty( $response_body->tax->breakdown->line_items ) ) {
			foreach ( $response_body->tax->breakdown->line_items as $response_line_item ) {
				$line_item                                = new Tax_Detail_Line_Item( $response_line_item );
				$this->line_items[ $line_item->get_id() ] = $line_item;
			}
		}
	}

	/**
	 * Sets shipping rate.
	 *
	 * @param mixed $response_body Body of response from TaxJar API.
	 */
	private function set_shipping_rate( $response_body ) {
		if ( $this->is_shipping_taxable() ) {
			$this->set_shipping_rate_from_response( $response_body );
		} else {
			$this->shipping_tax_rate = 0.0;
		}
	}

	/**
	 * Sets shipping rate from TaxJar API response.
	 *
	 * @param mixed $response_body Body of response from TaxJar API.
	 */
	private function set_shipping_rate_from_response( $response_body ) {
		if ( $this->response_contains_shipping_rate( $response_body ) ) {
			$this->shipping_tax_rate = $response_body->tax->breakdown->shipping->combined_tax_rate;
		} else {
			$this->shipping_tax_rate = 0.0;
		}
	}

	/**
	 * Checks if response from TaxJar API contains a shipping rate.
	 *
	 * @param mixed $response_body Body of response from TaxJar API.
	 *
	 * @return bool
	 */
	private function response_contains_shipping_rate( $response_body ) {
		return ! empty( $response_body->tax->breakdown->shipping->combined_tax_rate );
	}

	/**
	 * Sets average tax rate of transaction if present in response from TaxJar API.
	 *
	 * @param mixed $response_body Body of response from TaxJar API.
	 */
	private function set_rate( $response_body ) {
		if ( ! empty( $response_body->tax->rate ) ) {
			$this->rate = $response_body->tax->rate;
		} else {
			$this->rate = 0.0;
		}
	}

	/**
	 * Gets line item tax details for a single item.
	 *
	 * @param string $id Id of line item.
	 *
	 * @return bool|mixed
	 * @throws Exception
	 */
	public function get_line_item( $id ) {
		if ( ! empty( $this->line_items[ $id ] ) ) {
			return $this->line_items[ $id ];
		} else {
			throw new Exception( 'Line item not present in tax details.' );
		}
	}

	/**
	 * Gets ship to location.
	 *
	 * @return array
	 */
	public function get_location() {
		return array(
			'country' => $this->get_country(),
			'state'   => $this->get_state(),
			'zip'     => $this->get_zip(),
			'city'    => $this->get_city(),
		);
	}

	/**
	 * Checks if tax details has nexus.
	 *
	 * @return bool
	 */
	public function has_nexus() {
		return true === $this->has_nexus;
	}

	/**
	 * Checks if shipping is taxable.
	 *
	 * @return bool
	 */
	public function is_shipping_taxable() {
		return true === $this->freight_taxable;
	}

	/**
	 * Sets ship to country code.
	 *
	 * @param string $country Ship to country code.
	 */
	public function set_country( $country ) {
		$this->country = $country;
	}

	/**
	 * Gets ship to country code.
	 *
	 * @return string
	 */
	public function get_country() {
		return $this->country;
	}

	/**
	 * Sets ship to state code.
	 *
	 * @param string $state Ship to state code.
	 */
	public function set_state( $state ) {
		$this->state = $state;
	}

	/**
	 * Gets ship to state code.
	 *
	 * @return string
	 */
	public function get_state() {
		return $this->state;
	}

	/**
	 * Sets ship to city.
	 *
	 * @param string $city Ship to city.
	 */
	public function set_city( $city ) {
		$this->city = $city;
	}

	/**
	 * Gets ship to city.
	 *
	 * @return string
	 */
	public function get_city() {
		return $this->city;
	}

	/**
	 * Sets ship to zip code.
	 *
	 * @param string $zip Ship to zip code.
	 */
	public function set_zip( $zip ) {
		$this->zip = $zip;
	}

	/**
	 * Gets ship to zip code.
	 *
	 * @return string
	 */
	public function get_zip() {
		return $this->zip;
	}

	/**
	 * Gets shipping tax rate.
	 *
	 * @return float
	 */
	public function get_shipping_tax_rate() {
		return $this->shipping_tax_rate;
	}

	/**
	 * Gets average tax rate.
	 *
	 * @return float
	 */
	public function get_rate() {
		return $this->rate;
	}

	/**
	 * Gets raw response from TaxJar API.
	 *
	 * @return array
	 */
	public function get_raw_response() {
		return $this->raw_response;
	}
}

