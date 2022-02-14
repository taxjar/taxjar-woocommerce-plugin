<?php
/**
 * Abstract class for compatibility modules.
 *
 * @package TaxJar
 */

namespace TaxJar;

/**
 * Abstract class Module
 */
abstract class Module {

	/**
	 * Determine if the compatibility module should be loaded.
	 *
	 * @return bool
	 */
	abstract public function should_load(): bool;

	/**
	 * Load the compatibility module.
	 *
	 * @return mixed
	 */
	abstract public function load();
}
