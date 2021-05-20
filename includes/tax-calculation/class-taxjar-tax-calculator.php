<?php

namespace TaxJar;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxJar_Tax_Calculator {

	private $logger;
	private $cache;
	private $request_body_factory;
	private $tax_client;
	private $applicator;
	private $validator;

	private $context;
	private $request_body;
	private $tax_details;

	public function set_logger( $logger ) {
		if ( $logger instanceof TaxJar_Logger ) {
			$this->logger = $logger;
		} else {
			throw new Exception( 'Logger must be instance of TaxJar_Logger' );
		}
	}

	public function set_cache( $cache ) {
		if ( $cache instanceof TaxJar_Cache_Interface ) {
			$this->cache = $cache;
		} else {
			throw new Exception( 'Cache must implement TaxJar_Cache_Interface' );
		}
	}

	public function set_request_body_factory( $request_body_factory ) {
		if ( $request_body_factory instanceof TaxJar_Tax_Request_Body_Factory ) {
			$this->request_body_factory = $request_body_factory;
		} else {
			throw new Exception( 'Request Body Factory must be instance of TaxJar_Tax_Request_Body_Factory' );
		}
	}

	public function set_tax_client( $tax_client ) {
		if ( $tax_client instanceof TaxJar_Tax_Client_Interface ) {
			$this->tax_client = $tax_client;
		} else {
			throw new Exception( 'Tax Client must implement TaxJar_Tax_Client_Interface' );
		}
	}

	public function set_applicator( $applicator ) {
		if ( $applicator instanceof TaxJar_Tax_Applicator_Interface ) {
			$this->applicator = $applicator;
		} else {
			throw new Exception( 'Tax Client must implement TaxJar_Tax_Applicator_Interface' );
		}
	}

	public function set_validator( $validator ) {
		if ( $validator instanceof TaxJar_Tax_Calculation_Validator_Interface ) {
			$this->validator = $validator;
		} else {
			throw new Exception( 'Validator must implement TaxJar_Tax_Calculation_Validator_Interface' );
		}
	}

	public function set_context( $context ) {
		$this->context = $context;
	}

	public function get_context() {
		return $this->context;
	}

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

	private function failure( $exception ) {
		$details = array(
			'exception' => $exception,
			'context' => $this->context,
			'request_body' => $this->request_body,
			'tax_details' => $this->tax_details
		);
		$this->logger->log_failure( $details );
	}

	private function create_request_body() {
		$this->request_body = $this->request_body_factory->create();
	}

	public function validate() {
		$this->validator->validate( $this->request_body );
	}

	public function calculate_tax() {
		if ( $this->is_matching_rate_in_cache() ) {
			$this->get_tax_from_cache();
		} else {
			$this->get_tax_from_api();
			$this->cache_tax();
		}
	}

	private function is_matching_rate_in_cache() {
		return $this->cache->contains_hashed_value( $this->request_body->to_array() );
	}

	private function get_tax_from_cache() {
		$cached_response = $this->cache->read_hashed_value( $this->request_body->to_array() );
		$this->tax_details = new TaxJar_Tax_Details( $cached_response );
	}

	private function get_tax_from_api() {
		$this->tax_details = $this->tax_client->get_taxes( $this->request_body );
	}

	private function cache_tax() {
		$this->cache->set_with_hashed_key( $this->request_body->to_array(), $this->tax_details->get_raw_response() );
	}

	public function apply_tax() {
		$this->applicator->apply_tax( $this->tax_details );
	}

	private function success() {
		$details = array(
			'context' => $this->context,
			'request_body' => $this->request_body,
			'tax_details' => $this->tax_details
		);
		$this->logger->log_success( $details );
	}

}