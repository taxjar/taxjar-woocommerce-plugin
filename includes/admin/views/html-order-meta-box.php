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
				'advanced'           => array(
					'label'  => __( 'Advanced', 'taxjar' ),
					'target' => 'advanced_order_data',
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
		<p><?php echo esc_html( $metadata['sync_status'] ); ?></p>
		<?php do_action( 'taxjar_sync_status_order_data_panel', $order->get_id(), $order ); ?>
	</div>
	<div id="advanced_order_data" class="panel woocommerce_options_panel">
		<div class="accordion-container">
			<div class="accordion-section request-json">
				<h3 class="accordion-section-title">Calculation Request JSON <span class="copy-button dashicons dashicons-clipboard"></span></h3>
				<div class="accordion-section-content">
					<pre><?php echo esc_html( $metadata['request_json'] ); ?></pre>
				</div>
			</div>
			<div class="accordion-section response-json">
				<h3 class="accordion-section-title">Calculation Response JSON <span class="copy-button dashicons dashicons-clipboard"></span></h3>
				<div class="accordion-section-content">
					<pre><?php echo esc_html( $metadata['response_json'] ); ?></pre>
				</div>
			</div>
			<?php do_action( 'taxjar_advanced_order_data_panel_accordion', $order->get_id(), $order ); ?>
		</div>
		<?php do_action( 'taxjar_advanced_order_data_panel', $order->get_id(), $order ); ?>
	</div>
	<?php do_action( 'taxjar_order_data_panels', $order->get_id(), $order ); ?>
	<div class="clear"></div>
</div>
