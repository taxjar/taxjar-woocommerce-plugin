<?php
/**
 * Loads compatibility modules
 *
 * @package TaxJar
 */

namespace TaxJar;

/**
 * Class Module_Loader
 */
class Module_Loader {

	/**
	 * List of compatibility modules to load
	 *
	 * @var string[]
	 */
	private $extensions = array(
		'\TaxJar\WooCommerce_Gift_Cards',
		'\TaxJar\WooCommerce_Smart_Coupons',
		'\TaxJar\WooCommerce_PDF_Product_Vouchers',
	);

	/**
	 * Add necessary actions
	 */
	public function __construct() {
		add_action( 'wp_loaded', array( $this, 'load_modules' ) );
	}

	/**
	 * Load compatibility modules
	 */
	public function load_modules() {
		foreach ( $this->extensions as $extension_class ) {
			$extension = new $extension_class();

			if ( $extension->should_load() ) {
				$extension->load();
			}
		}
	}
}
