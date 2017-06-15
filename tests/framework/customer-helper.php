<?php

class TaxJar_Customer_helper {

  public static function get_test_customer( $country = 'US', $state = 'CO', $zip = '80111', $city = 'Greenwood Village' ) {
    global $woocommerce;

    $customer = new WC_Customer();
    $customer->set_shipping_location( $country, $state, $zip, $city );

    return $customer;
  }

}
