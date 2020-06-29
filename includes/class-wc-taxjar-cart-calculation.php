<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Taxjar_Cart_Calculation {

	public function __construct( $integration ) {
		$this->taxjar_integration = $integration;

		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_cart_tax' ) );
		add_filter( 'woocommerce_calculated_total', array( $this, 'add_tax_to_cart_total' ), 10, 2 );

		add_filter( 'woocommerce_rate_code', array( $this, 'override_rate_code' ), 10, 2 );
	}

	public function add_tax_to_cart_total( $total, $cart ) {

		$total += $cart->get_cart_contents_tax();
		$total += $cart->get_fee_tax();
		$total += $cart->get_shipping_tax();

		return $total;
	}

	public function override_rate_code( $code, $tax_rate_id ) {
		return 'TEST';
	}

	public function calculate_cart_tax( $wc_cart_object ) {

		$this->cart = $wc_cart_object;

		$address = $this->taxjar_integration->get_address( $wc_cart_object );
		$line_items = $this->taxjar_integration->get_line_items( $wc_cart_object );

		$customer_id = 0;
		if ( is_object( WC()->customer ) ) {
			$customer_id = apply_filters( 'taxjar_get_customer_id', WC()->customer->get_id(), WC()->customer );
		}

		$exemption_type = apply_filters( 'taxjar_cart_exemption_type', '', $wc_cart_object );

		$taxes = $this->taxjar_integration->calculate_tax( array(
			'to_country' => $address['to_country'],
			'to_zip' => $address['to_zip'],
			'to_state' => $address['to_state'],
			'to_city' => $address['to_city'],
			'to_street' => $address['to_street'],
			'shipping_amount' => WC()->shipping->shipping_total,
			'line_items' => $line_items,
			'customer_id' => $customer_id,
			'exemption_type' => $exemption_type,
		) );

		if ( $taxes === false ) {
			return;
		}

		if ( isset( $taxes['line_items'] ) ) {
			foreach ( $taxes['line_items'] as $response_line_item_key => $response_line_item ) {
				$line_item = $this->taxjar_integration->get_line_item( $response_line_item_key, $line_items );

				if ( isset( $line_item ) ) {
					$taxes['line_items'][ $response_line_item_key ]->line_total = ( $line_item['unit_price'] * $line_item['quantity'] ) - $line_item['discount'];
					$line_item_key_chunks = explode( '-', $response_line_item_key );
					$key = $line_item_key_chunks[1];
					$this->set_tax_on_line_item( $key, $taxes['line_items'][ $response_line_item_key ]->tax_collectable );
				}
			}
		}

		$cart_tax_amount = 0;
		$shipping_tax = 0;

		if ( $taxes[ 'freight_taxable' ] ) {
			$shipping_tax = $taxes[ 'shipping_collectable' ];
		}

		foreach( $taxes['line_items'] as $line_item ) {
			$cart_tax_amount += $line_item->tax_collectable;
		}

		$this->set_cart_tax_amount( 'TEST', $cart_tax_amount );
		$this->set_cart_shipping_tax_amount( 'TEST', $shipping_tax );
		$this->update_tax_totals();
	}

	/**
	 * Set the tax for a particular cart item.
	 *
	 * @param mixed $key cart item key.
	 * @param float $tax sales tax for cart item.
	 */
	public function set_tax_on_line_item( $key, $tax ) {
		$cart_contents = $this->cart->get_cart_contents();
		$tax_data = $cart_contents[ $key ]['line_tax_data'];

		$tax_data['subtotal'][ 'TEST' ] = $tax;
		$tax_data['total'][ 'TEST' ]    = $tax;

		$cart_contents[ $key ]['line_tax_data']     = $tax_data;
		$cart_contents[ $key ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
		$cart_contents[ $key ]['line_tax']          = array_sum( $tax_data['total'] );


		$this->cart->set_cart_contents( $cart_contents );
	}

	/**
	 * Set the tax for a shipping package.
	 *
	 * @param float $tax sales tax for package.
	 */
	public function set_shipping_tax( $tax ) {
		$packages = WC()->shipping()->get_packages();
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', [] );

		foreach( $packages as $package ) {
			$rates = $package[ 'rates' ];
			foreach ( $rates as $rate_key => $rate ) {

				if ( in_array( $rate_key, $chosen_methods, true ) ) {
					$taxes = $rate->get_taxes();
					$taxes[ 'TEST' ] = $tax;
					$rate->set_taxes( $taxes );
				} else {
					$taxes = $rate->get_taxes();
					$taxes[ 'TEST' ] = 0.00;
					$rate->set_taxes( $taxes );
				}
			}
		}

		WC()->shipping()->packages = $packages;
	}

	/**
	 * Set the tax amount for a given tax rate.
	 *
	 * @param string $tax_rate_id ID of the tax rate to set taxes for.
	 * @param float  $amount
	 */
	public function set_cart_tax_amount( $tax_rate_id, $amount ) {
		$taxes                 = $this->cart->get_cart_contents_taxes();
		$taxes[ $tax_rate_id ] = $amount;
		$this->cart->set_cart_contents_taxes( $taxes );
	}

	/**
	 * Set the shipping tax amount for a given tax rate.
	 *
	 * @param string $tax_rate_id ID of the tax rate to set shipping taxes for.
	 * @param float  $amount
	 */
	public function set_cart_shipping_tax_amount( $tax_rate_id, $amount ) {
		$taxes                 = $this->cart->get_shipping_taxes();
		$taxes[ $tax_rate_id ] = $amount;
		$this->cart->set_shipping_taxes( $taxes );
	}

	/**
	 * Update tax totals based on tax arrays.
	 */
	public function update_tax_totals() {
		$this->cart->set_cart_contents_tax( WC_Tax::get_tax_total( $this->get_cart_taxes() ) );
		$this->cart->set_shipping_tax( WC_Tax::get_tax_total( $this->cart->get_shipping_taxes() ) );
	}

	/**
	 * Get cart taxes.
	 *
	 * @return array of cart taxes.
	 */
	public function get_cart_taxes() {
		return wc_array_merge_recursive_numeric(
			$this->cart->get_cart_contents_taxes(),
			$this->cart->get_fee_taxes()
		);
	}





}
