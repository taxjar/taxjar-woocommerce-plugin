"""Factory for generating plugin file content."""


def generate_plugin_header(
    version: str = '4.1.0',
    wc_tested: str = '9.0.0',
    wc_requires: str = '7.0.0',
    wp_tested: str = '6.4',
) -> str:
    """Generate plugin header with configurable values."""
    return f'''<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Plugin URI: https://www.taxjar.com/woocommerce-sales-tax-plugin/
 * Description: Save hours every month by putting your sales tax on autopilot.
 * Version: {version}
 * Author: TaxJar
 * Author URI: https://www.taxjar.com
 * Text Domain: taxjar-simplified-taxes-for-woocommerce
 *
 * WC tested up to: {wc_tested}
 * WC requires at least: {wc_requires}
 * Tested up to: {wp_tested}
 */

if ( ! defined( 'ABSPATH' ) ) {{
    exit;
}}

class WC_Taxjar {{
    static $version = '{version}';
    public static $minimum_woocommerce_version = '{wc_requires}';

    public function __construct() {{
        // Plugin initialization
    }}
}}
'''
