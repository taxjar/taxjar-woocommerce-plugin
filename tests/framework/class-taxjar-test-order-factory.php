<?php
/**
 * TaxJar Test Order Factory
 *
 * @package TaxJar/Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TaxJar_Test_Order_Factory
 */
class TaxJar_Test_Order_Factory {

	/**
	 * Order being built
	 *
	 * @var WC_Order|WP_Error
	 */
	private $order;

	/**
	 * Options to apply when building the order
	 *
	 * @var array
	 */
	private $order_options;

	/**
	 * Default options to apply to order
	 *
	 * @var array
	 */
	public static $default_options = array(
		'status'           => 'pending',
		'customer_id'      => 1,
		'shipping_address' => array(
			'first_name' => 'Fname',
			'last_name'  => 'Lname',
			'address_1'  => 'Shipping Address',
			'address_2'  => '',
			'city'       => 'Greenwood Village',
			'state'      => 'CO',
			'postcode'   => '80111',
			'country'    => 'US',
		),
		'billing_address'  => array(
			'first_name' => 'Fname',
			'last_name'  => 'Lname',
			'address_1'  => 'Billing Address',
			'address_2'  => '',
			'city'       => 'Greenwood Village',
			'state'      => 'CO',
			'postcode'   => '80111',
			'country'    => 'US',
			'email'      => 'admin@example.org',
			'phone'      => '111-111-1111',
		),
		'products'         => array(
			0 => array(
				'type'         => 'simple',
				'price'        => 100,
				'quantity'     => 1,
				'name'         => 'Dummy Product',
				'sku'          => 'SIMPLE1',
				'manage_stock' => false,
				'tax_status'   => 'taxable',
				'downloadable' => false,
				'virtual'      => false,
				'stock_status' => 'instock',
				'weight'       => '1.1',
				'tax_class'    => '',
				'tax_total'    => array( 7.25 ),
				'tax_subtotal' => array( 7.25 ),
			),
		),
		'shipping_method'  => array(
			'id'        => 'flat_rate_shipping',
			'label'     => 'Flat rate shipping',
			'cost'      => '10',
			'taxes'     => array( .73 ),
			'method_id' => 'flat_rate',
		),
		'totals'           => array(
			'shipping_total' => 10,
			'discount_total' => 0,
			'discount_tax'   => 0,
			'cart_tax'       => 7.25,
			'shipping_tax'   => .73,
			'total'          => 117.98,
		),
	);

	/**
	 * Creates a new zero tax test order
	 *
	 * @param array $options_override - Array of options that override the default order options.
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create_zero_tax_order( $options_override = array() ) {
		$zero_tax_options = array(
			'products' => array(
				0 => array(
					'tax_total'    => array( 0 ),
					'tax_subtotal' => array( 0 ),
				),
			),
			'shipping_method'  => array(
				'taxes'     => array( 0 ),
			),
			'totals'           => array(
				'discount_tax'   => 0,
				'cart_tax'       => 0,
				'shipping_tax'   => 0,
				'total'          => 110
			)
		);
		$options = array_replace_recursive( $zero_tax_options, $options_override );
		return self::create( $options );
	}

	/**
	 * Create a new test order
	 *
	 * @param array $options_override - Array of options that override the default order options.
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 *
	 * @return WC_Order|WP_Error
	 */
	public static function create( $options_override = array() ) {
		$factory = new self( $options_override );
		return $factory->create_order();
	}

	/**
	 * TaxJar_Test_Order_Factory constructor.
	 *
	 * @param array $options_override - Array of options that override the default order options.
	 */
	private function __construct( $options_override ) {
		$this->order         = wc_create_order();
		$this->order_options = $this->override_options( $options_override );
	}

	/**
	 * Override default order options
	 *
	 * @param array $options_override - Array of options that override the default order options.
	 *
	 * @return array
	 */
	private function override_options( $options_override ) {
		return array_replace_recursive( self::$default_options, $options_override );
	}

	/**
	 * Create test order
	 *
	 * @return WC_Order|WP_Error
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function create_order() {
		$this->set_customer_id();
		$this->set_shipping_address();
		$this->set_billing_address();
		$this->add_products();
		$this->add_shipping_item();
		$this->set_payment_method();
		$this->set_totals();

		$this->order->save();

		return $this->order;
	}

	/**
	 * Set order customer id
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function set_customer_id() {
		$this->order->set_customer_id( $this->order_options['customer_id'] );
	}

	/**
	 * Set order shipping address
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function set_shipping_address() {
		$this->order->set_shipping_first_name( $this->order_options['shipping_address']['first_name'] );
		$this->order->set_shipping_last_name( $this->order_options['shipping_address']['last_name'] );
		$this->order->set_shipping_address_1( $this->order_options['shipping_address']['address_1'] );
		$this->order->set_shipping_address_2( $this->order_options['shipping_address']['address_2'] );
		$this->order->set_shipping_city( $this->order_options['shipping_address']['city'] );
		$this->order->set_shipping_state( $this->order_options['shipping_address']['state'] );
		$this->order->set_shipping_postcode( $this->order_options['shipping_address']['postcode'] );
		$this->order->set_shipping_country( $this->order_options['shipping_address']['country'] );
	}

	/**
	 * Set order billing address
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function set_billing_address() {
		$this->order->set_billing_first_name( $this->order_options['billing_address']['first_name'] );
		$this->order->set_billing_last_name( $this->order_options['billing_address']['last_name'] );
		$this->order->set_billing_address_1( $this->order_options['billing_address']['address_1'] );
		$this->order->set_billing_address_2( $this->order_options['billing_address']['address_2'] );
		$this->order->set_billing_city( $this->order_options['billing_address']['city'] );
		$this->order->set_billing_state( $this->order_options['billing_address']['state'] );
		$this->order->set_billing_postcode( $this->order_options['billing_address']['postcode'] );
		$this->order->set_billing_country( $this->order_options['billing_address']['country'] );
		$this->order->set_billing_email( $this->order_options['billing_address']['email'] );
		$this->order->set_billing_phone( $this->order_options['billing_address']['phone'] );
	}

	/**
	 * Add products to order
	 */
	private function add_products() {
		foreach ( $this->order_options['products'] as $product_fields ) {
			$product = TaxJar_Product_Helper::create_product( $product_fields['type'], $product_fields );

			$item = new WC_Order_Item_Product();
			$item->set_props(
				array(
					'product'  => $product,
					'quantity' => $product_fields['quantity'],
					'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => $product_fields['quantity'] ) ),
					'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => $product_fields['quantity'] ) ),
				)
			);
			$item->set_taxes(
				array(
					'total'    => $product_fields['tax_total'],
					'subtotal' => $product_fields['tax_subtotal'],
				)
			);
			$item->set_order_id( $this->order->get_id() );
			$item->save();
			$this->order->add_item( $item );
		}
	}

	/**
	 * Add shipping item to order
	 */
	private function add_shipping_item() {
		$rate = new WC_Shipping_Rate(
			$this->order_options['shipping_method']['id'],
			$this->order_options['shipping_method']['label'],
			$this->order_options['shipping_method']['cost'],
			$this->order_options['shipping_method']['taxes'],
			$this->order_options['shipping_method']['method_id']
		);

		$item = new WC_Order_Item_Shipping();
		$item->set_props(
			array(
				'method_title' => $rate->label,
				'method_id'    => $rate->id,
				'total'        => wc_format_decimal( $rate->cost ),
				'taxes'        => $rate->taxes,
			)
		);

		foreach ( $rate->get_meta_data() as $key => $value ) {
			$item->add_meta_data( $key, $value, true );
		}

		$item->save();
		$this->order->add_item( $item );
	}

	/**
	 * Set order payment method
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function set_payment_method() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->order->set_payment_method( $payment_gateways['bacs'] );
	}

	/**
	 * Set order totals
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	private function set_totals() {
		$this->order->set_shipping_total( $this->order_options['totals']['shipping_total'] );
		$this->order->set_discount_total( $this->order_options['totals']['discount_total'] );
		$this->order->set_discount_tax( $this->order_options['totals']['discount_tax'] );
		$this->order->set_cart_tax( $this->order_options['totals']['cart_tax'] );
		$this->order->set_shipping_tax( $this->order_options['totals']['shipping_tax'] );
		$this->order->set_total( $this->order_options['totals']['total'] );
	}
}
