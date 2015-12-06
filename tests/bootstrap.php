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
    $this->wp_tests_dir = '/tmp/wordpress-tests-lib';

    $this->api_token = getenv ( 'TAXJAR_API_TOKEN' );

    $this->includes();

    $this->setup();
  }

  public function includes() {
    // load test function so tests_add_filter() is available
    require_once( $this->wp_tests_dir . '/includes/functions.php' );

    // load the WP testing environment
    require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

    // load woocommerce core
    require_once $this->plugin_dir. 'woocommerce/woocommerce.php';

    //load taxjar core
    require_once $this->plugin_dir. 'taxjar-woocommerce-plugin/taxjar-woocommerce.php';
  }

  public function setup() {
    update_option('woocommerce_taxjar-integration_settings', 
      array(
        'api_token' => $this->api_token, 
        'enabled' => 'yes', 
        'taxjar_download' => 'yes',
        'store_zip' => '80111',
        'store_city' => 'Greenwood Village'
      )
    );

    update_option('woocommerce_default_country', 'US:CO');

    $wc_install = new WC_Install;
    $wc_install->install();

    do_action('plugins_loaded');
  }

  public function create_test_product() {
    $post = array(
      'post_author' => 1,
      'post_content' => '',
      'post_status' => "publish",
      'post_title' => 'fasdf product',
      'post_parent' => '',
      'post_type' => "product",
    );

    $post_id = wp_insert_post( $post );

    register_taxonomy(
      'product_type',
      'product'
    );

    wp_set_object_terms($post_id, 'simple', 'product_type');

    update_post_meta( $post_id, '_visibility', 'visible' );
    update_post_meta( $post_id, '_stock_status', 'instock');
    update_post_meta( $post_id, 'total_sales', '0');
    update_post_meta( $post_id, '_downloadable', 'no');
    update_post_meta( $post_id, '_virtual', 'no');
    update_post_meta( $post_id, '_regular_price', "10" );
    update_post_meta( $post_id, '_price', "10" );
    update_post_meta( $post_id, '_sold_individually', "no" );
    update_post_meta( $post_id, '_manage_stock', "no" );
  }

  public function get_test_product() {
    $products = get_posts(array('post_type' => 'product'));
    if( count($products) == 0) {
      $this->create_test_product();
      $products = get_posts(array('post_type' => 'product'));
    }

    $factory = new WC_Product_Factory();
    return $factory->get_product($products[0]->ID);
  }

  public static function instance() {
    if ( is_null( self::$instance ) ) {
      self::$instance = new self();
    }

    return self::$instance;
  }
}

TaxJar_WC_Unit_Tests_Bootstrap::instance();