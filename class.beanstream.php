<?php
/*
 * Plugin Name: WooCommerce Beanstream Payment Gateway
 * Plugin URI: http://www.scriptcombo.com/
 * Description: Beanstream Payment Gateway for WooCommerce Extension
 * Version: 1.0.0
 * Author: Scripted++
 * Author URI: http://www.scriptcombo.com/
 *  
 */

function woocommerce_api_beanstream_init(){
	
	if(!class_exists('WC_Payment_Gateway')) return;

	class WC_API_Beanstream extends WC_Payment_Gateway{
		public function __construct()
		{	
			$this->id 				= 'beanstream';
			$this->method_title 	= 'Beanstream';
			$this->has_fields 		= false; 
			$this->supports[] 		= 'default_credit_card_form';
			
			$this->init_form_fields();
			$this->init_settings();
			
			$this->title 			= $this->settings[ 'title' ];
			$this->description 		= $this->settings[ 'description' ];
			$this->mode 			= $this->settings[ 'mode' ];
			$this->merchant_id 		= $this->settings[ 'merchant_id' ];
			$this->passcode  		= $this->settings[ 'passcode' ];
			$this->returnUrl 		= $this->settings[ 'returnUrl' ];
			$this->debugMode  		= $this->settings[ 'debugMode' ];
			$this->msg['message'] 	= '';
			$this->msg['class'] 	= '';
			
			if ( $this->debugMode == 'on' ){
				$this->logs = new WC_Logger();
			}
			 	
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
		}
	
		public function init_form_fields()
		{
			$this->form_fields = array(
					'enabled' 			=> array(
	                    'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
	                    'type' 			=> 'checkbox',
	                    'label' 		=> __( 'Enable Beanstream Payment Module.', 'woocommerce' ),
	                    'default' 		=> 'no'
	                    ),
	                'title' => array(
	                    'title' 		=> __( 'Title:', 'woocommerce' ),
	                    'type'			=> 'text',
	                    'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
	                    'default' 		=> __( 'Beanstream', 'woocommerce' )
	                    ),
	                'description' => array(
	                    'title' 		=> __( 'Description:', 'woocommerce' ),
	                    'type' 			=> 'textarea',
	                    'description' 	=> __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
	                    'default' 		=> __( 'Pay with your credit card via Beanstream.', 'woocommerce' )
	                    ),
	                'mode' 	=> array(
	                    'title' 		=> __( 'Environment', 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'description' 	=> '',
	       				'options'     	=> array(
	                    	's' 	=> __( 'Sandbox', 'woocommerce' ),
					        'p'		=> __( 'Production', 'woocommerce' )
						)
					),    
	                'merchant_id' => array(
	                    'title' 		=> __( 'Merchant Login ID', 'woocommerce' ),
	                    'type' 			=> 'text',
	                    'description' 	=> __( 'Your Beanstream Merchant ID.', 'woocommerce' ),
	                    'desc_tip'      => true,
	                    ),  
	                'passcode' => array(
	                    'title' 		=> __( 'Passcode', 'woocommerce' ),
	                    'type' 			=> 'text',
	                    'description' 	=> __( 'Your Beanstream API access passcode.', 'woocommerce' ),
	                    'desc_tip'      => true,
	                    ),
	                'returnUrl' => array(
	                    'title' 		=> __( 'Return Url' , 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'desc_tip'      => true,
	                    'options' 		=> $this->getPages( 'Select Page' ),
	                    'description' 	=> __( 'URL of success page', 'woocommerce' )
	                    ),    
	                'debugMode' => array(
	                    'title' 		=> __( 'Debug Mode', 'woocommerce' ),
	                    'type' 			=> 'select',
	                    'description' 	=> '',
	       				'options'     	=> array(
					        'off' 		=> __( 'Off', 'woocommerce' ),
					        'on' 		=> __( 'On', 'woocommerce' )
	                    ))           
			);	
		}
		
		public function process_payment( $order_id )
		{
			global $woocommerce;
			global $wp_rewrite;
	
			$order 		 	= new WC_Order( $order_id );
			$card_number	= str_replace(' ', '' , woocommerce_clean($_POST['beanstream-card-number'] ));
	        $card_cvc		= str_replace(' ', '' , woocommerce_clean($_POST['beanstream-card-cvc'] ));
	        $card_exp_year 	= str_replace(' ', '' , woocommerce_clean($_POST['beanstream-card-expiry'] ));
	        $productinfo 	=  get_bloginfo(). " Order $order_id";
	        
			$exp = explode( "/" , $card_exp_year );
	        if( count($exp) == 2 ){
	        	$card_exp_month =  str_replace(' ', '', $exp[0] ); 
	        	$card_exp_year  =  str_replace(' ', '', $exp[1] ); 
			 }else{
			 	wc_add_notice(__('Payment error: Card expiration date is invalid', 'woocommerce'), "error");
	            return false;
			 }
	        
	        try {
				
	        	$req = curl_init('https://www.beanstream.com/api/v1/payments');
	 			$auth_passcode =  base64_encode ( "{$this->merchant_id}:{$this->passcode}" );
	 			
				$headers = array(
					'Content-Type:application/json',
					'Authorization: Passcode '.$auth_passcode
				);
	
				curl_setopt($req,CURLOPT_HTTPHEADER, $headers);
				curl_setopt($req,CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($req,CURLOPT_HEADER, 0);
	
				$post = array(
					'merchant_id' 		=> $this->merchant_id,
					'order_number' 		=> $order_id,
					'amount' 			=> $order->order_total ,
					'payment_method' 	=> 'card',
					'language' 			=> '',
					'comments' 			=> $productinfo,
					'billing_name' 		=> $order->billing_first_name .' '. $order->billing_last_name,
					'address_line1' 	=> $order->billing_address_1,
					'address_line2' 	=> $order->billing_address_2,
					'address_city' 		=> $order->billing_city,
					'address_province' 	=> $order->billing_state,
					'address_country' 	=> $order->billing_country,
					'address_postal_code' => $order->billing_postcode,
					'phone_number' 		=> $order->billing_phone,
					'email_address' 	=> $order->billing_email,
					'card' => array(
						'name' 		=> $order->billing_first_name .' '. $order->billing_last_name,
						'number' 	=> $card_number ,
						'expiry_month' 	=> $card_exp_month,
						'expiry_year' 	=> $card_exp_year,
						'cvd' 			=> $card_cvc
					)
				);        
	        
				curl_setopt($req,CURLOPT_POST, 1);
				curl_setopt($req,CURLOPT_POSTFIELDS, json_encode($post));
				curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($req, CURLOPT_SSL_VERIFYHOST, false); 
				
				$res_json = curl_exec($req);
				$response = json_decode($res_json);
				
				curl_close($req);
				
				if( $response->approved == 1 ){
					
					$order->payment_complete();
				    $order->add_order_note(
			            sprintf(
			                "%s Payment Completed with Transaction Id of '%s'",
			                $this->method_title,
			                $response->id
			            )
			        );
			        
					$woocommerce->cart->empty_cart();
					
					if($this->returnUrl == '' || $this->returnUrl == 0 ){
						$redirect_url = $this->get_return_url( $order );
					}else{
						$redirect_url = get_permalink( $this->returnUrl );
					}
					
					return array(
							'result' => 'success',
							'redirect' => $redirect_url
						);
					
				}else{
					
					$order->add_order_note(
			            sprintf(
			                "%s Payment Failed with message: '%s'",
			                $this->method_title,
			                $response->message
			            )
			        );
			       
			        wc_add_notice(__( 'Transaction Error: Could not complete your payment' , 'woocommerce'), "error");
			        return false;
					
				}
	        	
				
	       } catch (Exception $e) {
	        	$order->add_order_note(
			            sprintf(
			                "%s Payment Failed with message: '%s'",
			                $this->method_title,
			                $e->getMessage()
			            )
			        );
			        
			    wc_add_notice(__( 'Transaction Error: Could not complete your payment' , 'woocommerce'), "error");
			    return false;    
	        }
			
		
		}
		
		public function admin_options()
		{	
			if($this->mode == 'p' && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes'){
				echo '<div class="error"><p>'.sprintf(__('%s Sandbox testing is disabled and can performe live transactions but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate.', 'woothemes'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')).'</p></div>';	
			}
			
			$currencies = array("CAD", "USD");
			
			if(	!in_array(get_option('woocommerce_currency'), $currencies )){
				echo '<div class="error"><p>'.__(	'Beanstream Supported Merchant Currencies CAD and USD only.', 'woocommerce'	).'</p></div>';
			}
			
			echo '<h3>'.__(	'Beanstream Payment Gateway', 'woocommerce'	).'</h3>';
			echo '<div class="updated">';
			echo '<p>'.__(	'Do you like this plugin?', 'woocommerce' ).' <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE">'.__('Please reward it with a little donation.', 'woocommerce' ).'</a> </p>';
			echo '<p><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE"><img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" /> </a> </p>';
			echo '</div>';	
			echo '<p>'.__(	'Merchant Details.', 'woocommerce' ).'</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
				
		}
		
		public function validate_fields()
		{	
		
			global $woocommerce;
			
	        $card_number 		 = isset($_POST['beanstream-card-number']) ? woocommerce_clean($_POST['beanstream-card-number']) : '';
	        $card_cvc    		 = isset($_POST['beanstream-card-cvc']) ? woocommerce_clean($_POST['beanstream-card-cvc']) : '';
	        $card_exp_year 		 = isset($_POST['beanstream-card-expiry']) ? woocommerce_clean($_POST['beanstream-card-expiry']) : '';
	        
	       
			$card_number = str_replace(' ', '', $card_number);
	        if (empty($card_number) || !ctype_digit($card_number)) {
	            wc_add_notice(__('Payment error: Card number is invalid', 'woocommerce'), "error");
	            return false;
	        }
			
	        if (!ctype_digit($card_cvc)) {
	        	 wc_add_notice(__('Payment error: Card security code is invalid (only digits are allowed)', 'woocommerce'), "error");
	        	 return false;
	        }
	        
	        if(strlen($card_cvc) > 4){
	        	wc_add_notice(__('Payment error: Card security code is invalid (wrong length)', 'woocommerce'), "error");
	        	return false;
	        }
	
	        if( !empty($card_exp_year) ){
	        	$exp = explode( "/" , $card_exp_year );
	        	if( count($exp) == 2 ){
	        		$card_exp_month =  str_replace(' ', '', $exp[0] ); 
	        		$card_exp_year  =  str_replace(' ', '', $exp[1] ); 
	        		if (
			            !ctype_digit($card_exp_month) ||
			            !ctype_digit($card_exp_year) ||
			            $card_exp_month > 12 ||
			            $card_exp_month < 1 ||
			            $card_exp_year < date('y') ||
			            $card_exp_year > date('y') + 20
			        ){
			        	wc_add_notice(__('Payment error: Card expiration date is invalid', 'woocommerce'), "error");
	            		return;
			        }	
	        	}else{
	        		 wc_add_notice(__('Payment error: Card expiration date is invalid', 'woocommerce'), "error");
	        		 return;
	        	}
	        }
	        
	        
	        return true;
		}
	
		public function payment_fields()
		{
			if ( $this->mode == 's' ){
				echo '<p>';
				echo wpautop( wptexturize(  __('TEST MODE/SANDBOX ENABLED', 'woocommerce') )). ' ';
				echo '<p>';
			}
			
			if( $this->description ){
				echo wpautop( wptexturize( $this->description ) );
			}
			
			 $this->credit_card_form();
	
		}
	
		public function showMessage( $content )
		{
			$html  = '';
			$html .= '<div class="box '.$this->msg['class'].'-box">';
			$html .= $this->msg['message'];
			$html .= '</div>';
			$html .= $content;
				
			return $html;
				
		}
	
		public function getPages( $title = false, $indent = true )
		{
			$wp_pages = get_pages( 'sort_column=menu_order' );
			$page_list = array();
			if ( $title ) $page_list[] = $title;
			foreach ( $wp_pages as $page ) {
				$prefix = '';
				if ( $indent ) {
					$has_parent = $page->post_parent;
					while( $has_parent ) {
						$prefix .=  ' - ';
						$next_page = get_page( $has_parent );
						$has_parent = $next_page->post_parent;
					}
				}
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}
		
	}
	
	function woocommerce_add_api_beanstream( $methods ) {
		$methods[] = 'WC_API_Beanstream';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_api_beanstream' );
	
	function beanstream_action_links( $links ) {
			return array_merge( array(
				'<a href="' . esc_url( 'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9CQRJBSQPPJHE'  ) . '">' . __( 'Donation', 'woocommerce' ) . '</a>'
			), $links );
		}
		
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'beanstream_action_links' );

}

add_action( 'plugins_loaded', 'woocommerce_api_beanstream_init', 0 );