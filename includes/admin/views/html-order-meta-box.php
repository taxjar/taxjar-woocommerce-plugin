<?php
/**
 * Display the TaxJar calculation and sync data on a subscription or order.
 *
 * @var array $metadata Array of the TaxJar order metadata.
 * @var \WC_Order $order Order object.
 *
 * @package TaxJar
 */

namespace TaxJar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>

<div id="taxjar_order_data" class="panel-wrap">
	<div class="wc-tabs-back"></div>
	<ul class="taxjar_order_data_tabs wc-tabs" style="display:none;">

		<?php
		$order_tabs = apply_filters(
			'taxjar_order_data_tabs',
			array(
				'calculation_status' => array(
					'label'  => __( 'Calculation Status', 'taxjar' ),
					'target' => 'calculation_status_order_data',
					'class'  => '',
				),
				'sync_status'        => array(
					'label'  => __( 'Sync Status', 'taxjar' ),
					'target' => 'sync_status_order_data',
					'class'  => '',
				),
			)
		);

		foreach ( $order_tabs as $key => $order_tab ) :
			?>
			<li class="<?php echo esc_html( $key ); ?>_options <?php echo esc_html( $key ); ?>_tab <?php echo esc_html( implode( ' ', (array) $order_tab['class'] ) ); ?>">
				<a href="#<?php echo esc_html( $order_tab['target'] ); ?>">
					<span><?php echo esc_html( $order_tab['label'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<div id="calculation_status_order_data" class="panel woocommerce_options_panel">
		<p class="form-field">
			<label >Calculation Status: <span class="calculation-status-icon <?php echo esc_html( $metadata['calculation_status'] ); ?>"></span></label>
			<span class="description"><?php echo wp_kses( $metadata['calculation_status_description'], array( 'br' => array() ) ); ?></span>
		</p>
		<?php do_action( 'taxjar_calculation_status_order_data_panel', $order->get_id(), $order ); ?>
	</div>
	<div id="sync_status_order_data" class="panel woocommerce_options_panel">
		<?php if ( is_a( $order, 'WC_Subscription' ) ) { ?>
		<p><?php echo esc_html( __( 'Subscriptions are not synced to TaxJar. Each order created from the subscription will be individually synced.', 'taxjar' ) ); ?></p>
			<?php
		} else {
			$refunds           = $order->get_refunds();
			$order_sync_status = Order_Meta_Box::get_sync_status( $order );
			?>

		<div class="accordion-container">
			<div class="accordion-section order-section">
				<h3 class="accordion-section-title">
					Order #<?php echo esc_html( $order->get_id() ); ?>
					<span class="sync-status tips <?php echo esc_attr( $order_sync_status ); ?>" data-tip="<?php echo esc_attr( Order_Meta_Box::get_sync_status_tip( $order_sync_status, 'order' ) ); ?>"></span>
				</h3>
				<div class="accordion-section-content">
					<p>
					<?php
					echo wp_kses(
						Order_Meta_Box::get_order_sync_accordion_content( $order ),
						array(
							'br' => array(),
							'a'  => array( 'href' => array() ),
						)
					);
					?>
						</p>
				</div>
			</div>
			<?php
			foreach ( $refunds as $refund ) {
				$refund_sync_status = Order_Meta_Box::get_sync_status( $refund );
				?>
				<div class="accordion-section refund-section">
					<h3 class="accordion-section-title">
						Refund #<?php echo esc_html( $refund->get_id() ); ?>
						<span class="sync-status tips <?php echo esc_attr( $refund_sync_status ); ?>" data-tip="<?php echo esc_attr( Order_Meta_Box::get_sync_status_tip( $refund_sync_status, 'refund' ) ); ?>"></span>
					</h3>
					<div class="accordion-section-content">
						<p>
						<?php
						echo wp_kses(
							Order_Meta_Box::get_refund_sync_accordion_content( $refund ),
							array(
								'br' => array(),
								'a'  => array( 'href' => array() ),
							)
						);
						?>
							</p>
					</div>
				</div>
			<?php } ?>
		</div>

		<?php } ?>

		<?php do_action( 'taxjar_sync_status_order_data_panel', $order->get_id(), $order ); ?>
	</div>
	<?php do_action( 'taxjar_order_data_panels', $order->get_id(), $order ); ?>
	<div class="clear"></div>
</div>
