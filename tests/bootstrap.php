<?php
class TaxJar_WC_Unit_Tests_Bootstrap {

	protected static $instance = null;

	public $wp_tests_dir;
	public $tests_dir;
	public $plugins_dir;
	public $test_wp_dir;
	public $plugin_dir;
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

		require dirname( dirname( __FILE__ ) ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

		// Use version-aware hook selection
		// WC 8.x and 10.x require plugins_loaded (pluggable functions needed)
		// WC 7.x and 9.x can use muplugins_loaded (faster, no pluggable dependencies)
		$wc_version       = getenv( 'WC_VERSION' ) ?: '7.9.0';
		$major_version    = (int) explode( '.', $wc_version )[0];
		$use_plugins_hook = in_array( $major_version, array( 8, 10 ), true );
		$hook             = $use_plugins_hook ? 'plugins_loaded' : 'muplugins_loaded';
		tests_add_filter( $hook, array( $this, 'load_wc' ) );

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

		// For WC 8.x+, ActionScheduler is bundled but may not have all classes/functions loaded
		// Load ActionScheduler if Store class or functions are missing
		$wc_version    = getenv( 'WC_VERSION' ) ?: '7.9.0';
		$major_version = (int) explode( '.', $wc_version )[0];

		echo "WC Version: $wc_version, Major: $major_version\n";
		echo "ActionScheduler_Store exists: " . ( class_exists( 'ActionScheduler_Store' ) ? 'yes' : 'no' ) . "\n";
		echo "as_next_scheduled_action exists: " . ( function_exists( 'as_next_scheduled_action' ) ? 'yes' : 'no' ) . "\n";

		if ( $major_version >= 8 && ( ! class_exists( 'ActionScheduler_Store' ) || ! function_exists( 'as_next_scheduled_action' ) ) ) {
			// Try multiple possible ActionScheduler locations
			$as_paths = array(
				$this->plugin_dir . 'woocommerce/vendor/woocommerce/action-scheduler/action-scheduler.php',
				$this->plugin_dir . 'woocommerce/packages/action-scheduler/action-scheduler.php',
				$this->plugin_dir . 'woocommerce/lib/packages/League/Container/ActionScheduler/action-scheduler.php',
			);

			echo "Searching for ActionScheduler bootstrap...\n";
			foreach ( $as_paths as $as_bootstrap ) {
				echo "Checking: $as_bootstrap - " . ( file_exists( $as_bootstrap ) ? 'EXISTS' : 'NOT FOUND' ) . "\n";
				if ( file_exists( $as_bootstrap ) ) {
					echo "Loading ActionScheduler from: $as_bootstrap\n";
					require_once $as_bootstrap;
					echo "ActionScheduler loaded. Store class now exists: " . ( class_exists( 'ActionScheduler_Store' ) ? 'yes' : 'no' ) . "\n";
					break;
				}
			}
		}

		// load taxjar core
		update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );
		update_option( 'woocommerce_db_version', WC_VERSION );
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';

		// Manually load Install class since it's normally loaded via 'plugins_loaded' hook
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/includes/class-wc-taxjar-install.php';

		// Manually load Integration class since it's loaded via init() which may not fire in tests
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/includes/class-wc-taxjar-integration.php';

		// Load WooCommerce Subscriptions if available
		$subscriptions_file = $this->plugin_dir . 'woocommerce-subscriptions/woocommerce-subscriptions.php';
		if ( file_exists( $subscriptions_file ) ) {
			include_once $subscriptions_file;
		}
	}

	public function install_wc() {
		// prevent error from occurring when reinstalling WooCommerce
		remove_action( 'woocommerce_payment_gateways_settings', 'WC_Subscriptions_Admin::add_recurring_payment_gateway_information', 10 , 1 );

		// Clean existing install first.
		define( 'WP_UNINSTALL_PLUGIN', true );
		define( 'WC_REMOVE_ALL_DATA', true );
		update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
		include $this->plugin_dir . 'woocommerce/uninstall.php';

		do_action( 'wp_enqueue_scripts' );
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
		require_once $this->tests_dir . '/framework/class-taxjar-test-order-factory.php';
		require_once $this->tests_dir . '/framework/cart-builder.php';
		require_once $this->tests_dir . '/framework/abstract-cart-integration-test.php';
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
