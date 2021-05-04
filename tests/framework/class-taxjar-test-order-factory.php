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

	public static $default_fee_details = array(
		'name' => 'Test Fee',
		'amount' => 10,
		'tax_class' => '',
		'tax_status' => 'taxable'
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

	public static function create_fee_only_order( $fee_details_override = array() ) {
		$fee_details = array_replace_recursive( TaxJar_Test_Order_Factory::$default_fee_details, $fee_details_override );
		$factory = new TaxJar_Test_Order_Factory();
		$factory->set_customer_id( TaxJar_Test_Order_Factory::$default_options['customer_id'] );
		$factory->set_shipping_address( TaxJar_Test_Order_Factory::$default_options['shipping_address'] );
		$factory->set_billing_address( TaxJar_Test_Order_Factory::$default_options['billing_address'] );
		$factory->add_shipping_item( TaxJar_Test_Order_Factory::$default_options['shipping_method'] );
		$factory->add_fee( $fee_details );
		$factory->set_payment_method();
		return $factory->get_order();
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
		$options = self::override_options( $options_override );
		$factory = new self();
		$factory->create_order_from_options( $options );
		$factory->save_order();
		return $factory->get_order();
	}

	/**
	 * Override default order options
	 *
	 * @param array $options_override - Array of options that override the default order options.
	 *
	 * @return array
	 */
	private static function override_options( $options_override ) {
		return array_replace_recursive( self::$default_options, $options_override );
	}

	/**
	 * TaxJar_Test_Order_Factory constructor.
	 */
	public function __construct() {
		$this->order = wc_create_order();
	}

	/**
	 * Create test order
	 *
	 * @return WC_Order|WP_Error
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function create_order_from_options( $options = array() ) {
		$this->set_customer_id( $options['customer_id'] );
		$this->set_shipping_address( $options['shipping_address'] );
		$this->set_billing_address( $options['billing_address'] );
		$this->add_products( $options['products'] );
		$this->add_shipping_item( $options['shipping_method'] );
		$this->set_payment_method();
		$this->set_totals( $options['totals'] );
	}

	/**
	 * Set order customer id
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_customer_id( $customer_id ) {
		$this->order->set_customer_id( $customer_id );
	}

	/**
	 * Set order shipping address
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_shipping_address( $shipping_address) {
		$this->order->set_shipping_first_name( $shipping_address['first_name'] );
		$this->order->set_shipping_last_name( $shipping_address['last_name'] );
		$this->order->set_shipping_address_1( $shipping_address['address_1'] );
		$this->order->set_shipping_address_2( $shipping_address['address_2'] );
		$this->order->set_shipping_city( $shipping_address['city'] );
		$this->order->set_shipping_state( $shipping_address['state'] );
		$this->order->set_shipping_postcode( $shipping_address['postcode'] );
		$this->order->set_shipping_country( $shipping_address['country'] );
	}

	/**
	 * Set order billing address
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_billing_address( $billing_address ) {
		$this->order->set_billing_first_name( $billing_address['first_name'] );
		$this->order->set_billing_last_name( $billing_address['last_name'] );
		$this->order->set_billing_address_1( $billing_address['address_1'] );
		$this->order->set_billing_address_2( $billing_address['address_2'] );
		$this->order->set_billing_city( $billing_address['city'] );
		$this->order->set_billing_state( $billing_address['state'] );
		$this->order->set_billing_postcode( $billing_address['postcode'] );
		$this->order->set_billing_country( $billing_address['country'] );
		$this->order->set_billing_email( $billing_address['email'] );
		$this->order->set_billing_phone( $billing_address['phone'] );
	}

	/**
	 * Add products to order
	 */
	public function add_products( $products ) {
		foreach ( $products as $product_fields ) {
			$this->add_product( $product_fields );
		}
	}

	public function add_product( $product_details ) {
		$product = TaxJar_Product_Helper::create_product( $product_details['type'], $product_details );
		$item = new WC_Order_Item_Product();
		$item->set_props(
			array(
				'product'  => $product,
				'quantity' => $product_details['quantity'],
				'subtotal' => wc_get_price_excluding_tax( $product, array( 'qty' => $product_details['quantity'] ) ),
				'total'    => wc_get_price_excluding_tax( $product, array( 'qty' => $product_details['quantity'] ) ),
			)
		);
		$item->set_taxes(
			array(
				'total'    => $product_details['tax_total'],
				'subtotal' => $product_details['tax_subtotal'],
			)
		);
		$item->set_order_id( $this->order->get_id() );
		$item->save();
		$this->order->add_item( $item );
	}

	/**
	 * Add shipping item to order
	 */
	public function add_shipping_item( $shipping_details ) {
		$rate = new WC_Shipping_Rate(
			$shipping_details['id'],
			$shipping_details['label'],
			$shipping_details['cost'],
			$shipping_details['taxes'],
			$shipping_details['method_id']
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
	public function set_payment_method() {
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		$this->order->set_payment_method( $payment_gateways['bacs'] );
	}

	/**
	 * Set order totals
	 *
	 * @throws WC_Data_Exception Throws exception when invalid data is found.
	 */
	public function set_totals( $totals ) {
		$this->order->set_shipping_total( $totals['shipping_total'] );
		$this->order->set_discount_total( $totals['discount_total'] );
		$this->order->set_discount_tax( $totals['discount_tax'] );
		$this->order->set_cart_tax( $totals['cart_tax'] );
		$this->order->set_shipping_tax( $totals['shipping_tax'] );
		$this->order->set_total( $totals['total'] );
	}

	public function save_order() {
		$this->order->save();
	}

	public function get_order() {
		return $this->order;
	}

	public function add_fee( $fee_details ) {
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( $fee_details['name'] );
		$fee->set_amount( $fee_details['amount'] );
		$fee->set_tax_class( $fee_details['tax_class'] );
		$fee->set_tax_status( $fee_details['tax_status'] );
		$fee->set_total( $fee_details['amount'] );
		$this->order->add_item( $fee );
	}
}
