<?php
/**
 * Admin Meta Boxes
 *
 * Adds meta box to order type posts.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Admin_Meta_Boxes
 */
class Admin_Meta_Boxes {

	/**
	 * Admin_Meta_Boxes Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ), 30 );
	}

	/**
	 * Add meta box to order post types.
	 */
	public function add_order_meta_box() {
		foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
			add_meta_box(
				'taxjar',
				__( 'TaxJar', 'taxjar' ),
				'\TaxJar\Order_Meta_Box::output',
				$type,
				'normal',
				'low'
			);
		}
	}
}
