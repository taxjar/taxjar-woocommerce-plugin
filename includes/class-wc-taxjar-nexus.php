<?php

/**
 * TaxJar Nexus
 *
 * @package  WC_Taxjar_Integration
 * @author   TaxJar
 */

if ( ! defined( 'ABSPATH' ) )  {
  exit; // Prevent direct access to script
}

class WC_Taxjar_Nexus {

  public function __construct( $integration ) {
    $this->integration = $integration;
    $this->nexus = $this->get_nexus();
  }

  public function get_form_settings_field( ) {
    $desc_text = '';

    $desc_text .= '<h3>Nexus Information</h3>';
    $desc_text .= '<p>The following place are where sales tax will be calculated</p>';

    foreach ($this->nexus as $key => $nexus) {
      $desc_text .= '<br>';
      
      if ( isset( $nexus->region ) && isset ( $nexus->country ) ) {
        $desc_text .= sprintf( "%s, %s", $nexus->region, $nexus->country );
      } else {
        if ( isset ( $nexus->country ) ) {
          $desc_text .= $nexus->country;
        }
      }
      
    }

    return array(
      'title'             => '',
      'type'              => 'hidden',
      'description'       => $desc_text
    );
  }

  private function get_nexus( ) {
    $url      = $this->integration->uri . 'nexus/regions';
    $response = wp_remote_get( $url, array(
      'headers' =>    array(
                        'Authorization' => 'Token token="' . $this->integration->settings['api_token'] .'"',
                        'Content-Type' => 'application/x-www-form-urlencoded'
                      ),
      'user-agent' => $this->integration->ua
    ) );

    if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
      $body = json_decode( $response['body'] );
      return $body->regions;
    }

    return array();
  }


} // WC_Taxjar_Nexus