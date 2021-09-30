<?php
/**
 * Tax Calculator
 *
 * @package TaxJar\TaxCalculation
 */

namespace TaxJar;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class Tax_Calculator
 */
class Tax_Calculator {

	/**
	 * Logs events.
	 *
	 * @var Tax_Calculation_Logger
	 */
	private $logger;

	/**
	 * Store rates from TaxJar API.
	 *
	 * @var Cache_Interface
	 */
	private $cache;

	/**
	 * Builds tax request body for tax calculation API requests.
	 *
	 * @var Tax_Request_Body_Builder
	 */
	private $request_body_builder;

	/**
	 * Retrieves tax details from API.
	 *
	 * @var Tax_Client_Interface
	 */
	private $tax_client;

	/**
	 * Applies tax.
	 *
	 * @var Tax_Applicator_Interface
	 */
	private $applicator;

	/**
	 * Ensures tax can and should be calculated.
	 *
	 * @var Tax_Calculation_Validator_Interface
	 */
	private $validator;

	/**
	 * Context tax calculation is occurring in.
	 *
	 * @var string
	 */
	private $context;

	/**
	 * Stores request body used for API request.
	 *
	 * @var Tax_Request_Body
	 */
	private $request_body;

	/**
	 * Stores tax details to apply in calculation process.
	 *
	 * @var Tax_Details
	 */
	private $tax_details;

	/**
	 * Sets the logger.
	 *
	 * @param Tax_Calculation_Logger $logger Logger instance.
	 *
	 * @throws Exception When logger is not instance of Logger.
	 */
	public function set_logger( $logger ) {
		if ( $logger instanceof Tax_Calculation_Logger ) {
			$this->logger = $logger;
		} else {
			throw new Exception( 'Logger must be instance of Logger' );
		}
	}

	/**
	 * Sets the cache.
	 *
	 * @param Cache $cache Cache instance.
	 *
	 * @throws Exception When cache does not implement Cache_Interface.
	 */
	public function set_cache( $cache ) {
		if ( $cache instanceof Cache_Interface ) {
			$this->cache = $cache;
		} else {
			throw new Exception( 'Cache must implement Cache_Interface' );
		}
	}

	/**
	 * Sets the request body builder.
	 *
	 * @param Tax_Request_Body_Builder $request_body_builder Request body builder instance.
	 *
	 * @throws Exception When request_body_builder is not an instance of Tax_Request_Body_Builder.
	 */
	public function set_request_body_builder( $request_body_builder ) {
		if ( $request_body_builder instanceof Tax_Request_Body_Builder ) {
			$this->request_body_builder = $request_body_builder;
		} else {
			throw new Exception( 'Request Body Factory must be instance of Tax_Request_Body_Factory' );
		}
	}

	/**
	 * Sets the tax client.
	 *
	 * @param Tax_Client $tax_client Tax client instance.
	 *
	 * @throws Exception When tax client does not implement Tax_Client_Interface.
	 */
	public function set_tax_client( $tax_client ) {
		if ( $tax_client instanceof Tax_Client_Interface ) {
			$this->tax_client = $tax_client;
		} else {
			throw new Exception( 'Tax Client must implement Tax_Client_Interface' );
		}
	}

	/**
	 * Sets the tax applicator.
	 *
	 * @param Tax_Applicator_Interface $applicator Applicator instance.
	 *
	 * @throws Exception When applicator does not implement Tax_Applicator_Interface.
	 */
	public function set_applicator( $applicator ) {
		if ( $applicator instanceof Tax_Applicator_Interface ) {
			$this->applicator = $applicator;
		} else {
			throw new Exception( 'Tax applicator must implement Tax_Applicator_Interface' );
		}
	}

	/**
	 * Sets the validator.
	 *
	 * @param Tax_Calculation_Validator_Interface $validator Validator instance.
	 *
	 * @throws Exception When validator does not implement Tax_Calculation_Validator_Interface.
	 */
	public function set_validator( $validator ) {
		if ( $validator instanceof Tax_Calculation_Validator_Interface ) {
			$this->validator = $validator;
		} else {
			throw new Exception( 'Validator must implement Tax_Calculation_Validator_Interface' );
		}
	}

	/**
	 * Sets the context.
	 *
	 * @param string $context Context tax calculation is occurring in.
	 */
	public function set_context( $context ) {
		$this->context = $context;
	}

	/**
	 * Gets the tax calculation context.
	 *
	 * @return string
	 */
	public function get_context() {
		return $this->context;
	}

	/**
	 * Calculates and applies tax if possible and necessary.
	 */
	public function maybe_calculate_and_apply_tax() {
		try {
			$this->create_request_body();
			$this->validate();
			$this->calculate_tax();
			$this->apply_tax();
			$this->success();
		} catch ( Exception $exception ) {
			$this->failure( $exception );
		}
	}

	/**
	 * Creates the tax request body used to retrieve rates from the TaxJar API.
	 */
	private function create_request_body() {
		$this->request_body = $this->request_body_builder->create();
	}

	/**
	 * Ensures tax can and should be calculated.
	 */
	public function validate() {
		$this->validator->validate( $this->request_body );
	}

	/**
	 * Gets tax details from cache if present.
	 * Otherwise retrieves them from client.
	 */
	public function calculate_tax() {
		if ( $this->is_matching_rate_in_cache() ) {
			$this->get_tax_from_cache();
		} else {
			$this->get_tax_from_client();
			$this->cache_tax();
		}
	}

	/**
	 * Checks if tax details are already in cache.
	 *
	 * @return bool
	 */
	private function is_matching_rate_in_cache() {
		return $this->cache->contains_hashed_value( $this->request_body->to_array() );
	}

	/**
	 * Retrieves tax details from cache.
	 */
	private function get_tax_from_cache() {
		$cached_response   = $this->cache->read_hashed_value( $this->request_body->to_array() );
		$this->tax_details = new Tax_Details( $cached_response );
		$this->set_tax_details_address();
	}

	/**
	 * Gets tax details from client.
	 */
	private function get_tax_from_client() {
		$this->tax_details = $this->tax_client->get_taxes( $this->request_body );
		$this->set_tax_details_address();
	}

	/**
	 * Set tax details to address fields from request body.
	 */
	private function set_tax_details_address() {
		$this->tax_details->set_country( $this->request_body->get_to_country() );
		$this->tax_details->set_state( $this->request_body->get_to_state() );
		$this->tax_details->set_zip( $this->request_body->get_to_zip() );
		$this->tax_details->set_city( $this->request_body->get_to_city() );
	}

	/**
	 * Stores tax details in cache.
	 */
	private function cache_tax() {
		$this->cache->set_with_hashed_key( $this->request_body->to_array(), $this->tax_details->get_raw_response() );
	}

	/**
	 * Applies tax details.
	 */
	public function apply_tax() {
		$this->applicator->apply_tax( $this->tax_details );
	}

	/**
	 * Logs success details.
	 */
	private function success() {
		$result = new Tax_Calculation_Result();
		$result->set_success( true );
		$result->set_context( $this->get_context() );
		$result->set_raw_request( $this->request_body->to_json() );
		$result->set_raw_response( wp_json_encode( $this->tax_details->get_raw_response() ) );
		$this->logger->log_success( $result );
	}

	/**
	 * Logs failure details.
	 *
	 * @param Exception $exception Exception that occurred during tax calculation.
	 */
	private function failure( Exception $exception ) {
		$result = new Tax_Calculation_Result();
		$result->set_success( false );
		$result->set_context( $this->get_context() );
		$result->set_raw_request( $this->request_body->to_json() );

		if ( $this->tax_details ) {
			$result->set_raw_response( wp_json_encode( $this->tax_details->get_raw_response() ) );
		}

		$result->set_error_message( $exception->getMessage() );
		$this->logger->log_failure( $result, $exception );
	}
}
