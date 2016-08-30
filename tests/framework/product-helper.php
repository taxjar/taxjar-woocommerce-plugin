<?php

class TaxJar_Helper_Product {

  public static function get_test_product() {
    $products = get_posts(array('post_type' => 'product'));
    if( count($products) == 0) {
      TaxJar_Helper_Product::create_test_product();
      $products = get_posts(array('post_type' => 'product'));
    }

    $factory = new WC_Product_Factory();
    return $factory->get_product($products[0]->ID);
  }

  private static function create_test_product() {
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
}
