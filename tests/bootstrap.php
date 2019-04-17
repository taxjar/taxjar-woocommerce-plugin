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

		$this->includes();

		$this->setup();
	}

	public function includes() {
		// load the WP testing environment
		require_once $this->wp_tests_dir . 'includes/functions.php';
		require_once $this->wp_tests_dir . 'includes/bootstrap.php';

		// load woocommerce core
		require_once $this->plugin_dir . 'woocommerce/woocommerce.php';

		// completely remove woocommerce data from DB
        define( 'WP_UNINSTALL_PLUGIN', true );
        define( 'WC_REMOVE_ALL_DATA', true );
        update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
        include $this->plugin_dir . 'woocommerce/uninstall.php';

		// load taxjar core
		require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';

		// load framework
		require_once $this->tests_dir . '/framework/woocommerce-helper.php';
		require_once $this->tests_dir . '/framework/coupon-helper.php';
		require_once $this->tests_dir . '/framework/customer-helper.php';
		require_once $this->tests_dir . '/framework/product-helper.php';
		require_once $this->tests_dir . '/framework/shipping-helper.php';

	}

	public function setup() {
		update_option( 'woocommerce_taxjar-integration_settings',
			array(
				'api_token' => $this->api_token,
				'enabled' => 'yes',
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

		WC_Install::install();

		// load woocommerce subscriptions
        update_option( 'active_plugins', array( 'woocommerce/woocommerce.php' ) );
        update_option( 'woocommerce_db_version', WC_VERSION );
        require_once $this->plugin_dir . 'woocommerce-subscriptions/woocommerce-subscriptions.php';

		do_action( 'plugins_loaded' );
		do_action( 'woocommerce_init' );
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

TaxJar_WC_Unit_Tests_Bootstrap::instance();
