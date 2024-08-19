<?php
/**
 * Plugin Name: TaxJar - Sales Tax Automation for WooCommerce
 * Plugin URI: https://www.taxjar.com/woocommerce-sales-tax-plugin/
 * Description: Save hours every month by putting your sales tax on autopilot. Automated, multi-state sales tax calculation, collection, and filing.
 * Version: 4.2.3
 * Author: TaxJar
 * Author URI: https://www.taxjar.com
 * WC requires at least: 7.0.0
 * WC tested up to: 8.1.0
 * Requires PHP: 7.0
 *
 * Copyright: © 2014-2019 TaxJar. TaxJar is a trademark of TPS Unlimited, Inc.
 * License: GNU General Public License v2.0 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WC_Taxjar_Integration
 * @author TaxJar
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if WooCommerce is active and at the required minimum version, and if it isn't, disable plugin.
 */
$active_plugins = (array) get_option( 'active_plugins', array() );
if ( is_multisite() ) {
	$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
}
$woocommerce_active = in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );

if ( ! $woocommerce_active || version_compare( get_option( 'woocommerce_db_version' ), WC_Taxjar::$minimum_woocommerce_version, '<' ) ) {
	add_action( 'admin_notices', 'WC_Taxjar::display_woocommmerce_inactive_notice' );
	return;
}

/**
 * Main TaxJar WooCommerce Class.
 *
 * @class WC_Taxjar
 * @version	1.3.0
 */
final class WC_Taxjar {

	static $version = '4.2.3';
	public static $minimum_woocommerce_version = '7.0.0';

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'before_woocommerce_init', function() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_settings_link' ) );
		register_activation_hook( __FILE__, array( 'WC_Taxjar', 'plugin_registration_hook' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) ) {

			include_once 'includes/utilities/class-constants-manager.php';

			include_once 'includes/interfaces/class-cache-interface.php';
			include_once 'includes/interfaces/class-tax-client-interface.php';
			include_once 'includes/interfaces/class-tax-applicator-interface.php';
			include_once 'includes/interfaces/class-tax-calculation-validator-interface.php';
			include_once 'includes/interfaces/class-tax-calculation-result-data-store.php';

			// Include our integration class and WP_User for wp_delete_user()
			include_once ABSPATH . 'wp-admin/includes/user.php';
			include_once 'includes/class-wc-taxjar-ajax.php';
			include_once 'includes/class-wc-taxjar-nexus.php';
			include_once 'includes/class-wc-taxjar-download-orders.php';
			include_once 'includes/class-wc-taxjar-connection.php';
			include_once 'includes/class-wc-taxjar-integration.php';
			include_once 'includes/class-wc-taxjar-transaction-sync.php';
			include_once 'includes/class-wc-taxjar-customer-sync.php';
			include_once 'includes/class-wc-taxjar-install.php';
			include_once 'includes/class-wc-taxjar-record-queue.php';
			include_once 'includes/abstract-class-taxjar-record.php';
			include_once 'includes/class-taxjar-order-record.php';
			include_once 'includes/class-taxjar-refund-record.php';
			include_once 'includes/class-taxjar-customer-record.php';
			include_once 'includes/class-wc-taxjar-queue-list.php';
			include_once 'includes/class-taxjar-api-request.php';
			include_once 'includes/class-taxjar-settings.php';
			include_once 'includes/class-taxjar-tax-calculation.php';
			include_once 'includes/class-cache.php';

			include_once 'includes/TaxCalculation/class-tax-request-body.php';
			include_once 'includes/TaxCalculation/class-tax-request-body-builder.php';
			include_once 'includes/TaxCalculation/class-order-tax-request-body-builder.php';
			include_once 'includes/TaxCalculation/class-admin-order-tax-request-body-builder.php';
			include_once 'includes/TaxCalculation/class-tax-client.php';
			include_once 'includes/TaxCalculation/class-tax-details.php';
			include_once 'includes/TaxCalculation/class-tax-detail-line-item.php';
			include_once 'includes/TaxCalculation/class-tax-applicator.php';
			include_once 'includes/TaxCalculation/class-cart-tax-applicator.php';
			include_once 'includes/TaxCalculation/class-order-tax-applicator.php';
			include_once 'includes/TaxCalculation/class-rate-manager.php';
			include_once 'includes/TaxCalculation/class-tax-calculation-logger.php';
			include_once 'includes/TaxCalculation/class-order-calculation-logger.php';
			include_once 'includes/TaxCalculation/class-tax-calculation-exception.php';
			include_once 'includes/TaxCalculation/class-tax-calculator.php';
			include_once 'includes/TaxCalculation/class-tax-calculation-validator.php';
			include_once 'includes/TaxCalculation/class-order-tax-calculation-validator.php';
			include_once 'includes/TaxCalculation/class-cart-tax-calculation-validator.php';
			include_once 'includes/TaxCalculation/class-tax-calculator-builder.php';
			include_once 'includes/TaxCalculation/class-block-flag.php';
			include_once 'includes/TaxCalculation/class-cart-tax-request-body-builder.php';
			include_once 'includes/TaxCalculation/class-tax-calculation-result.php';
			include_once 'includes/TaxCalculation/class-cart-calculation-logger.php';
			include_once 'includes/TaxCalculation/class-tax-builder.php';
			include_once 'includes/TaxCalculation/class-cart-tax-calculation-result-data-store.php';
			include_once 'includes/TaxCalculation/class-order-tax-calculation-result-data-store.php';

			include_once 'includes/admin/class-admin-meta-boxes.php';
			include_once 'includes/admin/class-order-meta-box.php';

			include_once 'includes/compatibility/abstract-class-module.php';
			include_once 'includes/compatibility/class-module-loader.php';
			include_once 'includes/compatibility/modules/class-woocommerce-gift-cards.php';
			include_once 'includes/compatibility/modules/class-woocommerce-smart-coupons.php';
			include_once 'includes/compatibility/modules/class-woocommerce-pdf-product-vouchers.php';

			// Register the integration.
			add_action( 'woocommerce_integrations_init', array( $this, 'add_integration' ), 20 );

			// Display notices if applicable.
			add_action( 'admin_notices', array( $this, 'maybe_display_admin_notices' ) );
		}
	}

	/**
	 * Add a new integration to WooCommerce.
	 */
	public function add_integration() {
		TaxJar();
	}

	public static function display_woocommmerce_inactive_notice() {
		if ( current_user_can( 'activate_plugins' ) ) {
			$admin_notice_content = sprintf( esc_html__( '%1$sTaxJar is inactive.%2$s This version of TaxJar requires WooCommerce %3$s or newer. Please install or update WooCommerce to version %3$s or newer.', 'wc-taxjar' ), '<strong>', '</strong>', self::$minimum_woocommerce_version );
			?>
			<div class="error">
				<p><?php echo $admin_notice_content; ?></p>
			</div>
			<?php
		}
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

		$api_token = TaxJar()->get_option( 'api_token' );

		if ( '' == $api_token && apply_filters( 'taxjar_should_display_connect_notice', true ) ) {
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
		$url = add_query_arg( 'tab', 'taxjar-integration', $url );

		return $url;
	}

	/**
	 * Adds settings link to the plugins page
	 */
	public function plugin_settings_link( $links ) {
		$settings_link = '<a href="admin.php?page=wc-settings&tab=taxjar-integration">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

} // End WC_Taxjar.

$WC_Taxjar = new WC_Taxjar( __FILE__ );

/**
 * Returns the main instance of TaxJar Integration.
 */
function TaxJar() {
	return WC_Taxjar_Integration::instance();
}


