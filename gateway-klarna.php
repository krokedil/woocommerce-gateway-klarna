<?php
/*
Plugin Name: WooCommerce Klarna Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce. Provides a <a href="http://www.klarna.se" target="_blank">Klarna</a> gateway for WooCommerce.
Version: 1.7.6
Author: Niklas Högefjord
Author URI: http://krokedil.com
*/

/*  Copyright 2011-2013  Niklas Högefjord  (email : niklas@krokedil.se)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '4edd8b595d6d4b76f31b313ba4e4f3f6', '18624' );

// Init Klarna Gateway after WooCommerce has loaded
add_action('plugins_loaded', 'init_klarna_gateway', 2);

function init_klarna_gateway() {

	// If the WooCommerce payment gateway class is not available, do nothing
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	
	/**
	 * Localisation
	 */
	load_plugin_textdomain('klarna', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');


	// Define Klarna root Dir
	define('KLARNA_DIR', dirname(__FILE__) . '/');
	
	// Define Klarna lib
	define('KLARNA_LIB', dirname(__FILE__) . '/library/');
	
	
	class WC_Gateway_Klarna extends WC_Payment_Gateway {
			
		public function __construct() { 
			global $woocommerce;
			
			$this->shop_country	= get_option('woocommerce_default_country');
			
			// Check if woocommerce_default_country includes state as well. If it does, remove state
        	if (strstr($this->shop_country, ':')) :
        		$this->shop_country = current(explode(':', $this->shop_country));
        	else :
        		$this->shop_country = $this->shop_country;
        	endif;
        	
        	// If WPML is used, set the customer selected language as the shop_country
        	// This will be updated to support WPML's separated language and currency feature
        	// This is done in the Klarna checkout file but will be added for the other payment methods in short.
        	if ( class_exists( 'woocommerce_wpml' ) && defined('ICL_LANGUAGE_CODE') )
				$this->shop_country	= strtoupper(ICL_LANGUAGE_CODE);
				
				
			// If WooCommerce Currency Switcher is used (http://dev.pathtoenlightenment.net/shop), set the customer selected currency as the shop_country
        	if ( class_exists( 'WC_Aelia_CurrencySwitcher' ) && defined('AELIA_CS_USER_CURRENCY') ) {
				if(defined('AELIA_CS_USER_CURRENCY')) {
					//echo AELIA_CS_USER_CURRENCY . constant('AELIA_CS_USER_CURRENCY');
					$plugin_instance = WC_Aelia_CurrencySwitcher::instance();
					$selected_currency = strtoupper($plugin_instance->get_selected_currency());
					
				}
				
				switch ( $selected_currency ) {
				case 'NOK' :
					$klarna_country = 'NO';
					break;
				case 'EUR' :
					$klarna_country = 'FI';
					break;
				case 'SEK' :
					$klarna_country = 'SE';
					break;
				default:
					$klarna_country = $this->shop_country;
				}

				$this->shop_country	= $klarna_country;
			}
				
			
			// Actions
        	add_action( 'wp_enqueue_scripts', array(&$this, 'klarna_load_scripts'), 5 );
        	
	    }
	    
				
		/**
	 	 * Register and Enqueue Klarna scripts
	 	 */
		function klarna_load_scripts() {
			wp_enqueue_script( 'jquery' );
			
			// Invoice terms popup
			if ( is_checkout() ) {
				wp_register_script( 'klarna-invoice-js', 'https://static.klarna.com:444/external/js/klarnainvoice.js', array('jquery'), '1.0', false );
				wp_enqueue_script( 'klarna-invoice-js' );
			}
			
			// Account terms popup
			if ( is_checkout() || is_product() || is_shop() || is_product_category() || is_product_tag() ) {	
				// Original file: https://static.klarna.com:444/external/js/klarnapart.js
				wp_register_script( 'klarna-part-js', plugins_url( '/js/klarnapart.js', __FILE__ ), array('jquery'), '1.0', false );
				wp_enqueue_script( 'klarna-part-js' );
			}
			
			// Special Campaign terms popup
			if ( is_checkout() ) {
				// Original file: https://static.klarna.com:444/external/js/klarnaspecial.js
				wp_register_script( 'klarna-special-js', plugins_url( '/js/klarnaspecial.js', __FILE__ ), array('jquery'), '1.0', false );
				wp_enqueue_script( 'klarna-special-js' );
			}

		}
		
		
		
	
	
	} // End class WC_Gateway_Klarna
	
	// Include the WooCommerce Compatibility Utility class
	// The purpose of this class is to provide a single point of compatibility functions for dealing with supporting multiple versions of WooCommerce (currently 2.0.x and 2.1)
	require_once 'classes/class-wc-klarna-compatibility.php';
	
	
	// Include our Klarna Invoice class
	require_once 'class-klarna-invoice.php';
	
	// Include our Klarna Account class
	require_once 'class-klarna-account.php';
	
	// Include our Klarna Special campaign class
	require_once 'class-klarna-campaign.php';
	
	// Include our Klarna Checkout class - if Sweden, Norway or Finland is set as the base country
	$klarna_shop_country = get_option('woocommerce_default_country');
	$available_countries = array('SE', 'NO', 'FI');
	if ( in_array( $klarna_shop_country, $available_countries ) ) {
		require_once 'class-klarna-checkout.php';
	}
	
	
	
	
	
	// WC 2.0 Update notice
	class WC_Gateway_Klarna_Update_Notice {
		
		public function __construct() {
			
			// Add admin notice about the callback change
			//add_action('admin_notices', array($this, 'krokedil_admin_notice'));
			//add_action('admin_init', array($this, 'krokedil_nag_ignore'));
		}
	
		/* Display a notice about the changes to the Invoice fee handling */
		function krokedil_admin_notice() {
			
			global $current_user ;
			$user_id = $current_user->ID;
		
			/* Check that the user hasn't already clicked to ignore the message */
			if ( ! get_user_meta($user_id, 'klarna_callback_change_notice_17') && current_user_can( 'manage_options' ) ) {
				echo '<div class="updated fade"><p class="alignleft">';
				printf(__('The Klarna Checkout settings has changed. You will need to update and save the settings for Klarna checkout again before the payment method can be used. Please visit <a target="_blank" href="%1$s"> the payment gateway documentation</a> for more info.', 'klarna'), 'http://docs.woothemes.com/document/klarna/');
				echo '</p><p class="alignright">';
				printf(__('<a class="submitdelete" href="%1$s"> Hide this message</a>', 'klarna'), '?klarna_nag_ignore=0');
				echo '</p><br class="clear">';
				echo '</div>';
			}
		
		}

		/* Hide the notice about the changes to the Invoice fee handling if ignore link has been clicked */
		function krokedil_nag_ignore() {
			global $current_user;
			$user_id = $current_user->ID;
			/* If user clicks to ignore the notice, add that to their user meta */
			if ( isset($_GET['klarna_nag_ignore']) && '0' == $_GET['klarna_nag_ignore'] ) {
				add_user_meta($user_id, 'klarna_callback_change_notice_17', 'true', true);
			}
		}
	} // End class
	$wc_klarna_update_notice = new WC_Gateway_Klarna_Update_Notice;


} // End init_klarna_gateway

/**
 * Add the gateway to WooCommerce
 **/
function add_klarna_gateway( $methods ) {
	
	$methods[] = 'WC_Gateway_Klarna_Invoice';
	$methods[] = 'WC_Gateway_Klarna_Account';
	$methods[] = 'WC_Gateway_Klarna_Campaign';
	
	// Only add the Klarna Checkout method if Sweden, Norway or Finland is set as the base country
	$klarna_shop_country = get_option('woocommerce_default_country');
	$available_countries = array('SE', 'NO', 'FI');
	if ( in_array( $klarna_shop_country, $available_countries ) ) {
		$methods[] = 'WC_Gateway_Klarna_Checkout';
	}
	
	return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_klarna_gateway' );
