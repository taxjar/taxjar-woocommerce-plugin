<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Plugin URI: https://www.taxjar.com/woocommerce-sales-tax-plugin/
 * Description: Save hours every month by putting your sales tax on autopilot. Automated, multi-state sales tax calculation, collection, and filing.
 * Version: 1.7.1
 * Author: TaxJar
 * Author URI: https://www.taxjar.com
 * WC requires at least: 2.6.0
 * WC tested up to: 3.4.0
 *
 * Copyright: © 2014-2017 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc.
 * License: GNU General Public License v2.0 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Taxjar_Integration
 * @author TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Taxjar' ) ) :

/**
 * Main TaxJar WooCommerce Class.
 *
 * @class WC_Taxjar
 * @version	1.3.0
 */
final class WC_Taxjar {

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_settings_link' ) );
		register_activation_hook( __FILE__, array( 'WC_Taxjar', 'plugin_registration_hook' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {
			// Include our integration class and WP_User for wp_delete_user()
			include_once ABSPATH . 'wp-admin/includes/user.php';
			include_once 'includes/class-wc-taxjar-ajax.php';
			include_once 'includes/class-wc-taxjar-nexus.php';
			include_once 'includes/class-wc-taxjar-download-orders.php';
			include_once 'includes/class-wc-taxjar-connection.php';
			include_once 'includes/class-wc-taxjar-integration.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ), 20 );

			// Display notices if applicable.
			add_action( 'admin_notices', array( $this, 'maybe_display_admin_notices' ) );
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_Taxjar_Integration';
		return $integrations;
	}

	/**
	 * Run on plugin activation
	 */
  	static function plugin_registration_hook() {
		// TaxJar requires at least version 5.3 of PHP
		if ( version_compare( PHP_VERSION, '5.3', '<' ) ) {
			exit( sprintf( '<strong>TaxJar requires PHP 5.3 or higher. You are currently using %s.</strong>', PHP_VERSION ) );
		}

		// WooCommerce must be activated for TaxJar to activate
		if ( ! class_exists( 'Woocommerce' ) ) {
			exit( '<strong>Please activate WooCommerce before activating TaxJar.</strong>' );
		}

		global $wpdb;

		// Clear all transients
		wc_delete_product_transients();
		wc_delete_shop_order_transients();
		WC_Cache_Helper::get_transient_version( 'shipping', true );

		/*
		 * Deletes all expired transients. The multi-table delete syntax is used
		 * to delete the transient record from table a, and the corresponding
		 * transient_timeout record from table b.
		 *
		 * Based on code inside core's upgrade_network() function.
		 */
		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			AND b.option_value < %d";
		$rows = $wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );

		$sql = "DELETE a, b FROM $wpdb->options a, $wpdb->options b
			WHERE a.option_name LIKE %s
			AND a.option_name NOT LIKE %s
			AND b.option_name = CONCAT( '_site_transient_timeout_', SUBSTRING( a.option_name, 17 ) )
			AND b.option_value < %d";
		$rows2 = $wpdb->query( $wpdb->prepare( $sql, $wpdb->esc_like( '_site_transient_' ) . '%', $wpdb->esc_like( '_site_transient_timeout_' ) . '%', time() ) );

		// Export Tax Rates
		$current_class = '';
		$rates = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates
			ORDER BY tax_rate_order
			LIMIT %d, %d
			",
			0,
			10000
		) );

		ob_start();
		$header =
			__( 'Country Code', 'woocommerce' ) . ',' .
			__( 'State Code', 'woocommerce' ) . ',' .
			__( 'ZIP/Postcode', 'woocommerce' ) . ',' .
			__( 'City', 'woocommerce' ) . ',' .
			__( 'Rate %', 'woocommerce' ) . ',' .
			__( 'Tax Name', 'woocommerce' ) . ',' .
			__( 'Priority', 'woocommerce' ) . ',' .
			__( 'Compound', 'woocommerce' ) . ',' .
			__( 'Shipping', 'woocommerce' ) . ',' .
			__( 'Tax Class', 'woocommerce' ) . "\n";

		echo $header;

		foreach ( $rates as $rate ) {
			if ( $rate->tax_rate_country ) {
				echo esc_attr( $rate->tax_rate_country );
			} else {
				echo '*';
			}

			echo ',';

			if ( $rate->tax_rate_country ) {
				echo esc_attr( $rate->tax_rate_state );
			} else {
				echo '*';
			}

			echo ',';

			$locations = $wpdb->get_col( $wpdb->prepare( "SELECT location_code FROM {$wpdb->prefix}woocommerce_tax_rate_locations WHERE location_type='postcode' AND tax_rate_id = %d ORDER BY location_code", $rate->tax_rate_id ) );

			if ( $locations ) {
				echo esc_attr( implode( '; ', $locations ) );
			} else {
				echo '*';
			}

			echo ',';

			$locations = $wpdb->get_col( $wpdb->prepare( "SELECT location_code FROM {$wpdb->prefix}woocommerce_tax_rate_locations WHERE location_type='city' AND tax_rate_id = %d ORDER BY location_code", $rate->tax_rate_id ) );
			if ( $locations ) {
				echo esc_attr( implode( '; ', $locations ) );
			} else {
				echo '*';
			}

			echo ',';

			if ( $rate->tax_rate ) {
				echo esc_attr( $rate->tax_rate );
			} else {
				echo '0';
			}

			echo ',';

			if ( $rate->tax_rate_name ) {
				echo esc_attr( $rate->tax_rate_name );
			} else {
				echo '*';
			}

			echo ',';

			if ( $rate->tax_rate_priority ) {
				echo esc_attr( $rate->tax_rate_priority );
			} else {
				echo '1';
			}

			echo ',';

			if ( $rate->tax_rate_compound ) {
				echo esc_attr( $rate->tax_rate_compound );
			} else {
				echo '0';
			}

			echo ',';

			if ( $rate->tax_rate_shipping ) {
				echo esc_attr( $rate->tax_rate_shipping );
			} else {
				echo '0';
			}

			echo ',';

			echo "\n";
		} // End foreach().

		$csv = ob_get_contents();
		ob_end_clean();
		$upload_dir = wp_upload_dir();
		file_put_contents( $upload_dir['basedir'] . '/taxjar-wc_tax_rates-' . date( 'm-d-Y' ) . '-' . time() . '.csv', $csv );

		// Delete all tax rates
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rates' );
		$wpdb->query( 'TRUNCATE ' . $wpdb->prefix . 'woocommerce_tax_rate_locations' );
	} // End plugin_registration_hook().

	/**
	 * Display an admin notice, if not on the integration screen and if the account isn't yet connected.
	 */
	public function maybe_display_admin_notices() {
		if ( isset( $_GET['page'] ) && 'wc-settings' == $_GET['page'] && isset( $_GET['section'] ) && 'taxjar-integration' == $_GET['section'] ) {
			return;
		}

		$api_token = WC()->integrations->integrations['taxjar-integration']->get_option( 'api_token' );

		if ( '' == $api_token ) {
			$url = $this->get_settings_url();
			// translators: Installation admin notice
			echo '<div class="updated fade"><p>' . sprintf( __( '%1$sTaxJar for WooCommerce is almost ready. %2$sTo get started, %3$sconnect your TaxJar account%4$s.', 'wc-taxjar' ), '<strong>', '</strong>', '<a href="' . esc_url( $url ) . '">', '</a>' ) . '</p></div>' . "\n";
		}
	}

	/**
	 * Generate a URL to our specific settings screen.
	 */
	public function get_settings_url() {
		$url = admin_url( 'admin.php' );
		$url = add_query_arg( 'page', 'wc-settings', $url );
		$url = add_query_arg( 'tab', 'integration', $url );
		$url = add_query_arg( 'section', 'taxjar-integration', $url );

		return $url;
	}

	/**
	 * Adds settings link to the plugins page
	 */
	public function plugin_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=taxjar-integration">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

} // End WC_Taxjar.

$WC_Taxjar = new WC_Taxjar( __FILE__ );

endif;
