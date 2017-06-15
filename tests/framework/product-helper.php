<?php

class TaxJar_Helper_Product {

  public static function get_test_product() {
    $products = get_posts( array(
		'post_type' => 'product',
	) );

    if ( 0 == count( $products ) ) {
		TaxJar_Helper_Product::create_simple_product();
      	$products = get_posts( array(
			'post_type' => 'product',
		) );
    }

    $factory = new WC_Product_Factory();
    return $factory->get_product( $products[0]->ID );
  }

  private static function create_simple_product() {
    $post = array(
      'post_title' => 'Dummy Product',
      'post_type' => 'product',
      'post_status' => 'publish',
    );

    $post_id = wp_insert_post( $post );

    register_taxonomy(
      'product_type',
      'product'
    );

    update_post_meta( $post_id, '_price', '10' );
	update_post_meta( $post_id, '_regular_price', '10' );
	update_post_meta( $post_id, '_sale_price', '' );
	update_post_meta( $post_id, '_sku', 'DUMMY SKU' );
    update_post_meta( $post_id, '_manage_stock', 'no' );
	update_post_meta( $post_id, '_tax_status', 'taxable' );
    update_post_meta( $post_id, '_downloadable', 'no' );
    update_post_meta( $post_id, '_virtual', 'no' );
    update_post_meta( $post_id, '_stock_status', 'instock' );

    wp_set_object_terms( $post_id, 'simple', 'product_type' );
  }

}
