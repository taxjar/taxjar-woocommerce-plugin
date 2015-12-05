<?php

class TaxJar_WC_Unit_Tests_Bootstrap {
  protected static $instance = null;

  public $wp_tests_dir;

  public $tests_dir;

  public $plugin_dir;

  public function __construct() {

    ini_set( 'display_errors','on' );
    error_reporting( E_ALL );

    $this->tests_dir    = dirname( __FILE__ );
    $this->plugin_dir   = dirname( $this->tests_dir );
    $this->wp_tests_dir = '/tmp/wordpress-tests-lib';

    // load test function so tests_add_filter() is available
    require_once( $this->wp_tests_dir . '/includes/functions.php' );

    // load the WP testing environment
    require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );
  }

  public static function instance() {
    if ( is_null( self::$instance ) ) {
      self::$instance = new self();
    }

    return self::$instance;
  }

}

TaxJar_WC_Unit_Tests_Bootstrap::instance();