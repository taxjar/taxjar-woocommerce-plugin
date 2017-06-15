<?php

class TaxJar_WC_Unit_Tests_Bootstrap {
  protected static $instance = null;

  public $wp_tests_dir;
  public $tests_dir;
  public $plugins_dir;
  public $test_wp_dir;
  public $api_token;

  public function __construct() {

    ini_set( 'display_errors','on' );
    error_reporting( E_ALL );

    $this->tests_dir    = dirname( __FILE__ );
    $this->plugin_dir   = __DIR__ . '/../../';
    $this->wp_tests_dir = '/tmp/wordpress-tests-lib/';

    $this->api_token = getenv ( 'TAXJAR_API_TOKEN' );

    $this->includes();

    $this->setup();
  }

  public function includes() {
    // load the WP testing environment
    require_once( $this->wp_tests_dir . 'includes/functions.php' );
    require_once( $this->wp_tests_dir . 'includes/bootstrap.php' );

    // load woocommerce core
    require_once $this->plugin_dir . 'woocommerce/woocommerce.php';

    // load taxjar core
    require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';

	// load tlc-transients
	require_once $this->plugin_dir . 'taxjar-woocommerce-plugin/includes/tlc-transients/tlc-transients.php';

    // load framework
    require_once $this->tests_dir . '/framework/woocommerce-helper.php';
    require_once $this->tests_dir . '/framework/customer-helper.php';
    require_once $this->tests_dir . '/framework/product-helper.php';
  }

  public function setup() {
    update_option('woocommerce_taxjar-integration_settings',
      array(
        'api_token' => $this->api_token,
        'enabled' => 'yes',
        'taxjar_download' => 'yes',
        'store_zip' => '80111',
        'store_city' => 'Greenwood Village',
        'debug' => 'yes'
      )
    );

    update_option('woocommerce_default_country', 'US:CO');
    update_option('woocommerce_calc_shipping', 'yes');

    $wc_install = new WC_Install;
    $wc_install->install();

    do_action('plugins_loaded');
    do_action('woocommerce_init');
  }

  public static function instance() {
    if ( is_null( self::$instance ) ) {
      self::$instance = new self();
    }

    return self::$instance;
  }
}

TaxJar_WC_Unit_Tests_Bootstrap::instance();
