<?php
/**
 * Klarna get_addresses
 *
 * The Klarna get_addresses class displays a form field above the billing form on the WooCommerce Checkout page.
 * The Get Addresses form only displays if Klarna Account or Invoice Payment are enabled and active.
 * The customer enters their personal identity number/organisation number and then retrieves a getAddresses response from Klarna.
 * The response from Klarna contains the registered address for the individual/orgnaisation.
 * If a company uses the Get Addresses function the answer could contain several addresses. The customer can then select wich one to use.
 * When a retrieved address is selected, several checkout form fields are being changed to readonly and can after this not be edited.  
 *
 *
 * @class 		WC_Klarna_Get_Address
 * @version		1.0
 * @category	Class
 * @author 		Krokedil
 */
 
class WC_Klarna_Get_Address {
	public function __construct() {
		
		$data 									= new WC_Gateway_Klarna_Invoice;
		$this->testmode 						= $data->get_testmode();
		$this->eid		 						= $data->get_eid();
		$this->secret 							= $data->get_secret();
		$this->invo_enabled 					= $data->get_enabled();
		$this->invo_dob_display					= 'description_box'; //$data->get_dob_display();
		
		$data 									= new WC_Gateway_Klarna_Account;
		$this->partpay_enabled 					= $data->get_enabled();
		$this->partpay_dob_display				= 'description_box'; //$data->get_dob_display();
		
		// If Invoice payment isn't activated by the merchant, use Part payment credentials for getAddresses instead
		if( empty($this->eid) ) {
			$this->eid		 					= $data->get_eid();
			$this->secret 						= $data->get_secret();
			$this->order_type_partpayment 		= 'yes';
		}
		
		add_action( 'wp_ajax_ajax_request', array($this, 'ajax_request') );
		add_action( 'wp_ajax_nopriv_ajax_request', array($this, 'ajax_request') );
		
		//add_action( 'wp_footer', array($this, 'print_checkout_script') );
		add_action('wp_head', array( $this, 'ajaxurl'));
		add_action( 'wp_head', array( $this, 'js' ) );
		add_action( 'wp_footer', array( $this, 'checkout_restore_customer_defaults' ) );
		
		
		// Register and enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts') );
		
		// GetAddresses form above the checkout billing form
		//add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'get_address_button2' ) );
        
       
	} // End constructor
	
	

	
	
	/**
 	 * CSS for Get Addresses form
 	 *
 	**/
	function enqueue_scripts() {
		if( is_checkout() && ($this->partpay_enabled || $this->invo_enabled ) ) {
			wp_enqueue_style('klarna-style', KLARNA_URL . 'assets/css/style.css');
		}
	}
	
	
	
	
	/**
 	 * JS restoring the default checkout field values if user switch from Klarna (invoice, account or campaign) to another payment method
 	 * This is to prevent that customers use Klarnas Get Address feature and in the end use another payment method than Klarna.
 	 *
 	**/
	
	public function checkout_restore_customer_defaults() {
		
		if( is_checkout() && ($this->partpay_enabled || $this->invo_enabled ) ) {
		
			global $woocommerce, $current_user;
		
			$original_customer = array();
			$original_customer = WC()->session->get( 'customer' );
			
			$original_billing_first_name = '';
			$original_billing_last_name  = '';
			$original_shipping_first_name = '';
			$original_shipping_last_name  = '';
			$original_billing_company = '';
			$original_shipping_company  = '';
		
			$original_billing_first_name = $current_user->billing_first_name;
			$original_billing_last_name = $current_user->billing_last_name;
			$original_shipping_first_name = $current_user->shipping_first_name;
			$original_shipping_last_name = $current_user->shipping_last_name;
			$original_billing_company = $current_user->billing_company;
			$original_shipping_company  = $current_user->shipping_company;
			?>
			
			<script type="text/javascript">
			jQuery(document).ajaxComplete(function() {
				
				// On switch of payment method
				jQuery('input[name="payment_method"]').on('change', function(){
					var selected_paytype = jQuery('input[name=payment_method]:checked').val();
					if( selected_paytype !== 'klarna' && selected_paytype !== 'klarna_account' && selected_paytype !== 'klarna_campaign'){
						
						// Replace fetched customer values from Klarna with the original customer values
						jQuery("#billing_first_name").val('<?php echo $original_billing_first_name;?>');
						jQuery("#billing_last_name").val('<?php echo $original_billing_last_name;?>');
						jQuery("#billing_company").val('<?php echo $original_billing_company;?>');
						jQuery("#billing_address_1").val('<?php echo $original_customer['address'];?>');
						jQuery("#billing_address_2").val('<?php echo $original_customer['address_2'];?>');
						jQuery("#billing_postcode").val('<?php echo $original_customer['postcode'];?>');
						jQuery("#billing_city").val('<?php echo $original_customer['city'];?>');
						
						jQuery("#shipping_first_name").val('<?php echo $original_shipping_first_name;?>');
						jQuery("#shipping_last_name").val('<?php echo $original_shipping_last_name;?>');
						jQuery("#shipping_company").val('<?php echo $original_shipping_company;?>');
						jQuery("#shipping_address_1").val('<?php echo $original_customer['shipping_address'];?>');
						jQuery("#shipping_address_2").val('<?php echo $original_customer['shipping_address_2'];?>');
						jQuery("#shipping_postcode").val('<?php echo $original_customer['shipping_postcode'];?>');
						jQuery("#shipping_city").val('<?php echo $original_customer['shipping_city'];?>');
						
						
					}
				});
			});
			</script>
			<?php
		}
		
	} // End function
	
	/**
 	 * JS for fetching the personal identity number before the call to Klarna and populating the checkout fields after the call to Klarna
 	 *
 	**/
	function js() {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($){

			$(document).on('click','.compadress',function(){
				var value = $(this).attr("id");
					
				var json = $("#h" + value).val();
				var info = JSON.parse(json);
				
				klarnainfo("company", info, value);
			});
			
			function klarnainfo(type, info, value){
				
				if(type == 'company'){
					var adress = info[0][value];
					var orgno_getadress = "";
					/*
					if(jQuery('#klarna_pno').val() != ''){
						orgno_getadress = jQuery('#klarna_pno').val();
					}
					*/
					jQuery("#billing_first_name").val(adress['fname']);
					jQuery("#billing_last_name").val(adress['lname']);
					jQuery("#billing_company").val(adress['company']); //.prop( "readonly", true );
					jQuery("#billing_address_1").val(adress['street']); //.prop( "readonly", true );
					jQuery("#billing_address_2").val(adress['careof']); //.prop( "readonly", true );
					jQuery("#billing_postcode").val(adress['zip']); //.prop( "readonly", true );
					jQuery("#billing_city").val(adress['city']); //.prop( "readonly", true );
					
					jQuery("#shipping_first_name").val(adress['fname']);
					jQuery("#shipping_last_name").val(adress['lname']);
					jQuery("#shipping_company").val(adress['company']); //.prop( "readonly", true );
					jQuery("#shipping_address_1").val(adress['street']); //.prop( "readonly", true );
					jQuery("#shipping_address_2").val(adress['careof']); //.prop( "readonly", true );
					jQuery("#shipping_postcode").val(adress['zip']); //.prop( "readonly", true );
					jQuery("#shipping_city").val(adress['city']); //.prop( "readonly", true );
					
					jQuery("#phone_number").val(adress['cellno']);
					//jQuery("#klarna_pno").val(orgno_getadress);
				}
				
				if(type == 'private'){
					if(value == 0){
						
						var adress = info[0][value];
						var pno_getadress = "";
						
						/*
						if(jQuery('#klarna_pno').val() != ''){
							pno_getadress = jQuery('#klarna_pno').val();
						}
						*/
						jQuery("#billing_first_name").val(adress['fname']); //.prop( "readonly", true );
						jQuery("#billing_last_name").val(adress['lname']); //.prop( "readonly", true );
						jQuery("#billing_address_1").val(adress['street']); //.prop( "readonly", true );
						jQuery("#billing_address_2").val(adress['careof']);
						jQuery("#billing_postcode").val(adress['zip']); //.prop( "readonly", true );
						jQuery("#billing_city").val(adress['city']); //.prop( "readonly", true );
						
						jQuery("#shipping_first_name").val(adress['fname']); //.prop( "readonly", true );
						jQuery("#shipping_last_name").val(adress['lname']); //.prop( "readonly", true );
						jQuery("#shipping_address_1").val(adress['street']); //.prop( "readonly", true );
						jQuery("#shipping_address_2").val(adress['careof']);
						jQuery("#shipping_postcode").val(adress['zip']); //.prop( "readonly", true );
						jQuery("#shipping_city").val(adress['city']); //.prop( "readonly", true );
						
						jQuery("#phone_number").val(adress['cellno']);
						//jQuery("#klarna_pno").val(pno_getadress);
					}
				}
			}
			
			
			jQuery(document).on('click','.klarna-push-pno',function() {
				if( jQuery('#klarna_invo_pno').val() != '' ) {
					var pno_getadress = jQuery('#klarna_invo_pno').val();
				} else if( jQuery('#klarna_pno').val() != '' ) {
					var pno_getadress = jQuery('#klarna_pno').val();
				} else if( jQuery('#klarna_campaign_pno').val() != '' ) {
					var pno_getadress = jQuery('#klarna_campaign_pno').val();
				}
				
				if(pno_getadress == '') {
				
					$(".klarna-get-address-message").show();
					$(".klarna-get-address-message").html('<span style="clear:both; margin: 5px 2px; padding: 4px 8px; background:#ffecec"><?php _e('Be kind and enter a date of birth!', 'klarna');?></span>');
				
				} else {
										
					jQuery.post(
						'<?php echo get_option('siteurl') . '/wp-admin/admin-ajax.php' ?>',
						{
							action			: 'ajax_request',
							pno_getadress	: pno_getadress,
							_wpnonce		: '<?php echo wp_create_nonce('nonce-register_like'); ?>',
						},
						function(response){
							console.log(response);
							
							if(response.get_address_message == "" || (typeof response.get_address_message === 'undefined')){
								$(".klarna-get-address-message").hide();
								
								//if(klarna_client_type == "company"){
									var adresses = new Array();
									adresses.push(response);
									
									var res = "";
									//console.log(adresses[0].length);
									
									if(adresses[0].length < 2 ){
										
										klarnainfo('private', adresses, 0);
									}
									else{
										$(".klarna-response").show();
										
										res += '<h4 class="klarna-select-address-title"><?php _e('Select Address', 'woocommerce-gateway-klarna');?></h4>';
										for(var a = 0; a <= adresses.length; a++) {
										
											res += '<div id="adress' + a + '" class="adressescompanies">' +  
														'<input type="radio" id="' + a + '" name="klarna-selected-company" value="klarna-selected-company' + a + '" class="compadress"  /><label for="klarna-selected-company' + a + '">' +  adresses[0][a]['company'];
											if (adresses[0][a]['street']!=null) {
												res += ', ' + adresses[0][a]['street'];
											}
											res += ' ' + adresses[0][a]['careof'] + 
														', ' + adresses[0][a]['zip'] + ' ' + adresses[0][a]['city'] + '</label>';
												 res += "<input type='hidden' id='h" + a + "' value='" + JSON.stringify(adresses) + "' />";
											res +=	'</div>';
										}
									}
									
									jQuery(".klarna-response").html(res);
								/*}
								else{
									klarnainfo(klarna_client_type, response, 0);
								}*/
							}
							else{
								$(".klarna-get-address-message").show();
								$(".klarna-response").hide();
								
								jQuery(".klarna-get-address-message").html('<span style="clear:both;margin:5px 2px;padding:4px 8px;background:#ffecec">' + response.get_address_message + '</span>');
								
								$(".checkout .input-text").each(function( index ) {
									$(this).val("");
									$(this).prop("readonly", false);
								});
							}
						}
					);
				}				
			});
		});
		</script>
		<?php
	}
	
	
	/**
 	 * Display the GetAddress fields
 	 *
 	**/

	public function get_address_button() {
		
		if( ($this->invo_enabled && $this->invo_dob_display == 'description_box') || ($this->partpay_enabled && $this->partpay_dob_display == 'description_box') ) {
			ob_start();
			
				// Only display GetAddress button for Sweden
				if($this->get_country() == 'SE') { ?>
					<span class="klarna-push-pno get-address-button button"><?php _e('Fetch', 'klarna'); ?></span>
					<p class="form-row">
						<div class="klarna-response"></div>
						<div class="klarna-get-address-message"></div>
					</p>
				<?php 
				}
				 
			return ob_get_clean();
		}
	} // End function


/**
 	 * Display the GetAddress fields
 	 *
 	**/

	public function get_address_button2() {
		
		if( ($this->invo_enabled && $this->invo_dob_display == 'description_box') || ($this->partpay_enabled && $this->partpay_dob_display == 'description_box') ) {
			
			?>
			<div class="get-address-box">
  
				<span id="klarna-client-type-box-private" class="desc">
                	<input type="text" class="input-text" name="klarna_pno" id="klarna_pno" placeholder="<?php _e('Enter personal identity number/company registration number', 'klarna'); ?>" value=""/>
            	</span>
					
				<?php 
				// Don't display GetAddress button for Finland
				if($this->get_country() != 'FI') { ?>
					<span class="klarna-push-pno get-address-button button"><?php _e('Get address', 'klarna'); ?></span>
				<?php } ?>
			
				<div id="klarna-response"></div>
				<div class="klarna-get-address-message"></div>
            
			</div>
			
			<?php
			
		}
	} // End function


	
	
	
	/**
	 * Add ajaxurl var to head
	 */
	function ajaxurl() {
		?>
		<script type="text/javascript">
				var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
		</script>
		<?php
	}
	
	
	/**
	 * Ajax request callback function
	 */
	function ajax_request() {
	
		// The $_REQUEST contains all the data sent via ajax
		if ( isset($_REQUEST) ) {
		
			// Klarna settings
			require_once(KLARNA_LIB . 'Klarna.php');
			require_once(KLARNA_LIB . '/transport/xmlrpc-3.0.0.beta/lib/xmlrpc.inc');
			require_once(KLARNA_LIB . '/transport/xmlrpc-3.0.0.beta/lib/xmlrpc_wrappers.inc');
			
			// Test mode or Live mode		
			if ( $this->testmode == 'yes' ) {
				// Disable SSL if in testmode
				$klarna_ssl = 'false';
				$klarna_mode = Klarna::BETA;
			} else {
				// Set SSL if used in webshop
				if (is_ssl()) {
					$klarna_ssl = 'true';
				} else {
					$klarna_ssl = 'false';
				}
				$klarna_mode = Klarna::LIVE;
			}
				
			$k = new Klarna();
		
	
		$k->config(
		    $this->eid, 											// EID
		    $this->secret, 											// Secret
		    $this->get_country(), 									// Country
		    $this->get_klarna_language($this->get_country()), 		// Language
		    get_woocommerce_currency(), 							// Currency
		    $klarna_mode, 											// Live or test
		    $pcStorage = 'json', 									// PClass storage
		    $pcURI = '/srv/pclasses.json'							// PClass storage URI path
		);
			
			$pno_getadress = $_REQUEST['pno_getadress'];
			$return = array();
			
			$k->setCountry('se'); // Sweden only
			try {
			    $addrs = $k->getAddresses($pno_getadress);
			    
			    foreach($addrs as $addr) {
			    
		    		//$return[] = $addr->toArray();
		    		$return[] = array(
			            'email' 		=> 	utf8_encode($addr->getEmail()),
			            'telno' 		=> 	utf8_encode($addr->getTelno()),
			            'cellno' 		=> 	utf8_encode($addr->getCellno()),
			            'fname' 		=> 	utf8_encode($addr->getFirstName()),
			            'lname' 		=> 	utf8_encode($addr->getLastName()),
			            'company' 		=> 	utf8_encode($addr->getCompanyName()),
			            'careof' 		=> 	utf8_encode($addr->getCareof()),
			            'street' 		=> 	utf8_encode($addr->getStreet()),
			            'zip' 			=> 	utf8_encode($addr->getZipCode()),
			            'city' 			=> 	utf8_encode($addr->getCity()),
			            'country' 		=> 	utf8_encode($addr->getCountry()),
			        );
		    		
				}
			
			} catch(Exception $e) {
				//$message = "{$e->getMessage()} (#{$e->getCode()})\n";
				$return = array(
					'get_address_message'	=> __('No address found', 'klarna')
				);
				
			}
			
			wp_send_json($return);
			
			// If you're debugging, it might be useful to see what was sent in the $_REQUEST
			//print_r($_REQUEST);
		} else {
			echo '';
			die();
		}
		
		die();
	} // End function
	
	
	// Helper function - get_country
	public function get_country() {
		$data = new WC_Gateway_Klarna_Invoice;
		return $data->get_klarna_country();
	}
	
	// Helper function - get_klarna_language
	public function get_klarna_language($country) {
		$data = new WC_Gateway_Klarna_Invoice;
		return $data->get_klarna_country($country);
	}
	
} // End Class
$wc_klarna_get_address = new WC_Klarna_Get_Address;