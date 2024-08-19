<?php
/**
 * Admin Meta Boxes
 *
 * Adds meta box to order type posts.
 *
 * @package TaxJar
 */

namespace TaxJar;

use Automattic\WooCommerce\Utilities\OrderUtil;

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
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ), 30, 2 );
	}

	/**
	 * Add meta box to order post types.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_Post $post Post object.
	 */
	public function add_order_meta_box( $post_type, $post ) {
		$wc_order = wc_get_order( $post->ID );

		if ( ! $wc_order ) {
			return;
		}

		foreach ( wc_get_order_types( 'order-meta-boxes' ) as $type ) {
			add_meta_box(
				'taxjar',
				__( 'TaxJar', 'taxjar' ),
				'\TaxJar\Order_Meta_Box::output',
				$this->get_page_screen_id( $type ),
				'normal',
				'low',
				array( 'order' => $wc_order )
			);
		}
	}

	/**
	 * Get the id of the page where the meta box will be displayed.
	 *
	 * @param string $order_type The order type.
	 * @return string
	 */
	private function get_page_screen_id( $order_type ) {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return wc_get_page_screen_id( $order_type );
		}

		return $order_type;
	}
}
