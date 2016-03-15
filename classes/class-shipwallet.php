<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Klarna Shipwallet Shipping Method.
 *
 * A simple shipping method for free shipping.
 *
 * @class   WC_Shipping_Free_Shipping
 * @version 2.6.0
 * @package WooCommerce/Classes/Shipping
 * @author  WooThemes
 */
class WC_Shipping_Klarna_Shipwallet extends WC_Shipping_Method {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'klarna_shipwallet';
		$this->title              = __( 'Klarna Shipwallet Shipping', 'woocommerce' );
		$this->method_title       = __( 'Klarna Shipwallet Shipping', 'woocommerce' );
		$this->method_description = __( 'Klarna Shipwallet Shipping is a special method which is displayed inside Klarna Checkout iframe.', 'woocommerce' );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 * @uses WC_Shipping_Method::add_rate()
	 */
	public function calculate_shipping( $package = array() ) {
		if ( WC()->session->get( 'klarna_shipwallet_shipping' ) ) {
			$klarna_shipwallet = WC()->session->get( 'klarna_shipwallet_shipping' );
		}

		$this->add_rate( array(
			'id'    => $this->id . $this->instance_id,
			'label' => 'Shipwallet - ' . $klarna_shipwallet['name'],
			'cost'  => ( $klarna_shipwallet['price'] - $klarna_shipwallet['tax_amount'] ) / 100,
			'taxes' => $klarna_shipwallet['tax_amount'] / 100
		) );
	}

}

add_filter( 'woocommerce_shipping_methods', 'klarna_shipwallet_shipping_method_init' );
function klarna_shipwallet_shipping_method_init( $methods ) {
	$methods['klarna_shipwallet'] = 'WC_Shipping_Klarna_Shipwallet';

	return $methods;
}