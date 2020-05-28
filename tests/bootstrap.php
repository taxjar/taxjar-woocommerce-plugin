<?php
class TaxJar_WC_Unit_Tests_Bootstrap {

	protected static $instance = null;

	public $wp_tests_dir;
	public $tests_dir;
	public $plugins_dir;
	public $test_wp_dir;
	public $api_token;

	public function __construct() {
		ini_set( 'display_errors', 'on' );
		error_reporting( E_ALL );

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = __DIR__ . '/../../';
		$this->wp_tests_dir = ! empty( getenv( 'WP_TESTS_DIR' ) ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib/';

		$this->api_token = getenv( 'TAXJAR_API_TOKEN' );

		// load test function so tests_add_filter() is available
		require_once $this->wp_tests_dir . '/includes/functions.php';

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		tests_add_filter( 'taxjar_get_order_transaction_id', array( $this, 'add_tests_prefix' ) );
		tests_add_filter( 'taxjar_get_refund_transaction_id', array( $this, 'add_tests_prefix' ) );

		// load the WP testing environment
		require_once $this->wp_tests_dir . '/includes/bootstrap.php';

		$this->includes();
	}

	public function add_tests_prefix( $transaction_id ) {
		return 'WOOTEST' . $transaction_id;
	}

	public function load_wc() {
		// load woocommerce
		require_once $this->plugin_dir . 'woocommerce/woocommerce.php';

		// load taxjar core
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );
		update_option( 'woocommerce_db_version', WC_VERSION );
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';
		include_once $this->plugin_dir . 'woocommerce-subscriptions/woocommerce-subscriptions.php';
	}

	public function install_wc() {
		// prevent error from occurring when reinstalling WooCommerce
		remove_action( 'woocommerce_payment_gateways_settings', 'WC_Subscriptions_Admin::add_recurring_payment_gateway_information', 10 , 1 );

		// Clean existing install first.
		define( 'WP_UNINSTALL_PLUGIN', true );
		define( 'WC_REMOVE_ALL_DATA', true );
		update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
		include $this->plugin_dir . 'woocommerce/uninstall.php';

		WC_Install::install();

		// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374
		if ( version_compare( $GLOBALS['wp_version'], '4.7', '<' ) ) {
			$GLOBALS['wp_roles']->reinit();
		} else {
			$GLOBALS['wp_roles'] = null; // WPCS: override ok.
			wp_roles();
		}

		echo esc_html( 'Installing WooCommerce...' . PHP_EOL );

		$this->setup();
	}

	public function includes() {
		// load framework
		require_once $this->tests_dir . '/framework/woocommerce-helper.php';
		require_once $this->tests_dir . '/framework/coupon-helper.php';
		require_once $this->tests_dir . '/framework/customer-helper.php';
		require_once $this->tests_dir . '/framework/product-helper.php';
		require_once $this->tests_dir . '/framework/class-taxjar-shipping-helper.php';
		require_once $this->tests_dir . '/framework/wp-http-testcase.php';
		require_once $this->tests_dir . '/framework/subscription-helper.php';
		require_once $this->tests_dir . '/framework/order-helper.php';
		require_once $this->tests_dir . '/framework/class-taxjar-api-order-helper.php';
		require_once $this->tests_dir . '/framework/class-tj-wc-rest-unit-test-case.php';
	}

	public function setup() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}

		if ( ! defined( 'TAXJAR_REMOVE_ALL_DATA' ) ) {
			define( 'TAXJAR_REMOVE_ALL_DATA', true );
		}

		global $wpdb;

		WC_Taxjar_Install::drop_tables();
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'woocommerce\_taxjar\_%';" );
		$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'taxjar\_version%';" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}actionscheduler_actions;" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}actionscheduler_claims;" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}actionscheduler_groups;" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}actionscheduler_logs;" );

		delete_transient( 'taxjar_installing' );
		WC_Taxjar_Install::install();

		update_option( 'woocommerce_taxjar-integration_settings',
			array(
				'api_token' => $this->api_token,
				'enabled' => 'yes',
				'api_calcs_enabled' => 'yes',
				'taxjar_download' => 'yes',
				'store_postcode' => '80111',
				'store_city' => 'Greenwood Village',
				'store_street' => '6060 S Quebec St',
				'debug' => 'yes',
			)
		);

		update_option( 'woocommerce_default_country', 'US:CO' );
		update_option( 'woocommerce_calc_shipping', 'yes' );
		update_option( 'woocommerce_coupons_enabled', 'yes' );
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

TaxJar_WC_Unit_Tests_Bootstrap::instance();
