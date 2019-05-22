<?php

if (!defined('ABSPATH')) {
  exit;
}

class Give_Razer_Gateway {
  static private $instance;

  const QUERY_VAR   = 'razer_givewp_return';
  const LISTENER_CB = 'razer_givewp_listener';

  private function __construct() {
    add_action('init', array($this, 'return_listener'));
    add_action('give_gateway_razer', array($this, 'process_payment'));
    add_action('give_donation_form_after_email', array($this, 'give_razer_phone_number_form_fields'), 10, 1 );
    add_action('give_razer_cc_form', array($this, 'give_razer_cc_form'));
    add_action('give_razer_cc_form', array($this, 'give_razer_actived_channel'));
  }

  static function get_instance() {
    if (null === static::$instance) {
      static::$instance = new static();
    }

    return static::$instance;
  }

  private function create_payment($purchase_data) {

    $form_id  = intval($purchase_data['post_data']['give-form-id']);
    $price_id = isset($purchase_data['post_data']['give-price-id']) ? $purchase_data['post_data']['give-price-id'] : '';

    // Collect payment data.
    $insert_payment_data = array(
      'price'           => $purchase_data['price'],
      'give_form_title' => $purchase_data['post_data']['give-form-title'],
      'give_form_id'    => $form_id,
      'give_price_id'   => $price_id,
      'date'            => $purchase_data['date'],
      'user_email'      => $purchase_data['user_email'],
      'purchase_key'    => $purchase_data['purchase_key'],
      'currency'        => give_get_currency($form_id, $purchase_data),
      'user_info'       => $purchase_data['user_info'],
      'status'          => 'pending',
      'gateway'         => 'razer',
    );

    /**
     * Filter the payment params.
     *
     * @since 3.0.2
     *
     * @param array $insert_payment_data
     */
    $insert_payment_data = apply_filters('give_create_payment', $insert_payment_data);

    // Record the pending payment.
    return give_insert_payment($insert_payment_data);
  }

  public function process_payment($purchase_data) {

    // Validate nonce.
    give_validate_nonce($purchase_data['gateway_nonce'], 'give-gateway');

    $payment_id = $this->create_payment($purchase_data);

    // Check payment.
    if (empty($payment_id)) {
      // Record the error.
      give_record_gateway_error(__('Payment Error', 'give-razer'), sprintf( /* translators: %s: payment data */
        __('Payment creation failed before sending donor to Razer. Payment data: %s', 'give-razer'), json_encode($purchase_data)), $payment_id);
      // Problems? Send back.
      give_send_back_to_checkout();
    }

    $form_id     = intval($purchase_data['post_data']['give-form-id']);
    
    // Redirect to Razer Payment Gateway.
    $result = $this->razer_construct_form_and_post($payment_id, $purchase_data);
    
    exit;
  }

  public function give_razer_cc_form($form_id) {
      
    ob_start();

    /*
    //Enable Default CC fields (billing info)
    $global_razer_cc_fields = give_get_option('razer_collect_billing');

    //Output CC Address fields if global option is on and user hasn't elected to customize this form's offline donation options
    if ($global_razer_cc_fields) {
      give_default_cc_address_fields($form_id);
    }
    */
    
    echo ob_get_clean();
  }

  public function return_listener() {
   
    if (isset($_GET[self::QUERY_VAR]) && !isset($_POST['tranID'])) {
        give_insert_payment_note( $payment_id, "Donor close popup window page.");
        $return = give_get_failed_transaction_uri('?payment-id=' . $payment_id);
        wp_redirect($return);
    }
    else{
        //must have "razer_givewp_return"    
        if (!isset($_GET[self::QUERY_VAR])) {
           return;
        }

        //Razer Response
        /******************************************************************************
        * Response from Razer Merchant Service
        ******************************************************************************/
    
        $original_post = $_POST;

        $_POST['treq'] = 1; // Additional parameter for IPN. Value always set to 1.
    
        /********************************
        * Don't change below parameters
        ********************************/

        $nbcb     = (isset($_POST['nbcb']) ? $_POST['nbcb'] : '0');
        $tranID   = $_POST['tranID'];
        $orderid  = $_POST['orderid'];
        $status   = $_POST['status'];
        $domain   = $_POST['domain'];
        $amount   = $_POST['amount'];
        $currency = $_POST['currency'];
        $appcode  = $_POST['appcode'];
        $paydate  = $_POST['paydate'];
        $skey     = $_POST['skey'];
        $err_code = $_POST['error_code'];
        $err_desc = $_POST['error_desc'];
    
        
        if ($nbcb == 0) {
            if (!isset($_GET['form_id'])) {
               exit("Unable to proceed page!");
            }
        
            $payment_id = isset( $_GET['payment_id'] ) ? absint( $_GET['payment_id'] ) : false;
    
            if (!isset( $_GET['payment_id'] ) && ! give_get_purchase_session() ) {
                return;
            }

            if ( ! $payment_id ) {
                $session    = give_get_purchase_session();
                $payment_id = give_get_purchase_id_by_key( $session['purchase_key'] );
            }
        }
        else{
           $payment_id = $_POST['orderid'];
        }
            
        //Razer Status Code change to readable one
        switch ($_POST['status']) {
            case '00':
                $_POST['status'] = "Captured"; 
                $_POST['order_status'] = "Paid"; 
                break;
            case '22':
                $_POST['status'] = "Pending";
                $_POST['order_status'] = "Pending";
                break;
            case '11':
                $_POST['status'] = "Failed"; 
                $_POST['order_status'] = "Cancelled"; 
                break;
        }
        $_POST['status_code'] = $status;
    
    
        //nbcb
        switch ($nbcb) {
            case '1':
                $nbcb_txt  = "Callback";
                break;
            case '2':
                $nbcb_txt  = "Notification";
                break;
            default:
                $nbcb_txt  = "Return";
                break;
        }
        
        /***********************************************************
        * To verify the data integrity sending by Razer
        ************************************************************/    
        $vkey = give_get_option('razer_secret_key');
    
        $key0 = md5( $tranID.$orderid.$status.$domain.$amount.$currency );
        $key1 = md5( $paydate.$domain.$key0.$appcode.$vkey );

        $status_skey = true;
        if( $skey != $key1 ) $status_skey= -1; // Invalid transaction
    
        //echo $skey.'==='.$key1;
        $response_txt = '';
        foreach($original_post as $k => $v){
           $response_txt .= "$k : $v \n";
        }
        give_insert_payment_note( $payment_id, "[Razer MS Response : $nbcb_txt] \n". $response_txt );
        
        if ($status_skey) {
        
            if ($status == '00'){
                if ("publish" !== get_post_status($payment_id)) { 
                   give_update_payment_status( $payment_id, 'publish' );
                }
            }
            else if ($status == '22'){
                // Payment is still pending so show processing indicator to fix the race condition.
                give_insert_payment_note( $payment_id, "[22] Donation Status: ".get_post_status($payment_id));         
            }
            else if ($status == '11'){
                give_record_gateway_error( __( 'Razer Error', 'give' ), sprintf(__( $err_desc, 'give' ), json_encode( $original_post ) ), $payment_id );
                give_update_payment_status( $payment_id, 'failed' ); 
            } 
        }
    
        //Redirect payer to "Thank you" page
        if( $nbcb == 0 ){ //callback - 1; notification - 2; return - no return nbcb
            if ($status == '00' || $status == '22'){
                $return = add_query_arg(array(
                  'payment-confirmation' => 'razer',
                  'payment-id'           => $payment_id,
                ), get_permalink(give_get_option('success_page'))); 
            }else if ($status == '11'){
                $return = give_get_failed_transaction_uri('?payment-id=' . $payment_id);    
            }
    
            wp_redirect($return);
        }
        else if ( $nbcb == 1 ) {
            echo 'CBTOKEN:MPSTATOK';
        }
    }

    exit;
  }
  
  public function give_razer_actived_channel( $form_id )
  {
     echo "<h3>Payment Channels:</h3>";
      
      $active_channels_cc = give_get_option('razer_cc_channel');
      if (!in_array("none",$active_channels_cc)){
          echo '<fieldset id="give-razer-cc-channel-active" style="margin-bottom:15px;">';
          echo '<legend>Credit/Debit Card</legend>';
        
          foreach ($active_channels_cc as $channel) {
              if ( $channel != "none" ) {
                  $defautl_checked_cc = ( $channel == 'credit' ? "checked" : "" ); 
                  echo '<input type="radio" name="payment_options" id="payment_options_'.$channel.'" value="'.$channel.'" '.$defautl_checked_cc.'> '.$this->get_list_channel($channel).'<br/>';
              }
          }
          echo '</fieldset>';
      }
         
      $active_channels = give_get_option('razer_onlinebanking_channel');
      if (!in_array("none",$active_channels)) {
          echo '<fieldset id="give-razer-channel-active" style="margin-bottom:15px;">';
          echo '<legend>Online Banking</legend>';
        
          foreach ($active_channels as $channel) {
              if ( $channel != "none" ) {
                  echo '<input type="radio" name="payment_options" id="payment_options_'.$channel.'" value="'.$channel.'" > '.$this->get_list_channel($channel).'<br/>';
              }
          }
          echo '</fieldset>';
      }
  
      $active_channels_ewallet = give_get_option('razer_ewallet_channel');
      if (!in_array("none",$active_channels_ewallet)){
          echo '<fieldset id="give-razer-cc-channel-active" style="margin-bottom:15px;">';
          echo '<legend>E-Wallet</legend>';
          foreach ($active_channels_ewallet as $channel) {
              if ( $channel != "none" ) {
                  echo '<input type="radio" name="payment_options" id="payment_options_'.$channel.'" value="'.$channel.'"> '.$this->get_list_channel($channel).'<br/>';
              }
          }
          echo '</fieldset>';
      }
    
  }  
  
  public function razer_construct_form_and_post($payment_id, $payment_data) {
    
      $return_url =  add_query_arg( array(
          self::QUERY_VAR => true,
          'form_id'       => $payment_data['post_data']['give-form-id'],
          'payment_id'    => $payment_id,
      ), site_url('/'));
        
      $payer_name = $payment_data['user_info']['first_name'] . ' ' . $payment_data['user_info']['last_name'];
      $payer_country = ( isset($payment_data['user_info']['address']['country']) && !empty($payment_data['user_info']['address']['country']) )? $payment_data['user_info']['address']['country'] : 'MY';
      $payer_phone = ( isset($payment_data['post_data']['phone_number']) && !empty($payment_data['post_data']['phone_number']) )? $payment_data['post_data']['phone_number'] : '';

      $payer_amount = $payment_data['price'];
      $payer_currency =  give_get_currency( $payment_id, $payment_data ); 
      
      //Currency converter when enabled
      if (isset($payment_data['post_data']['give-cs-base-currency']) &&
          isset($payment_data['post_data']['give-cs-exchange-rate']) &&
          !empty($payment_data['post_data']['give-cs-base-currency']) &&
          !empty($payment_data['post_data']['give-cs-exchange-rate'])) {
        
          //Based MYR   
          $tmp_amt  = $payment_data['post_data']['give-amount'];
          $base_ex  = $payment_data['post_data']['give-cs-exchange-rate']; //1 MYR to other currency 
          
          $myr_amt = $tmp_amt / $base_ex; 
          $myr_amt = number_format($myr_amt,2,'.','');
          
          $payer_currency = $payment_data['post_data']['give-cs-base-currency'];
          $payer_amount = $myr_amt;
              
      }

    	$args = array(
		'status'          => true,	// Set True to proceed with MOLPay
		'mpsmerchantid'   => give_get_option('razer_merchantid'),
		'mpschannel'      => $payment_data['post_data']['payment_options'],
		'mpsamount'       => $payer_amount, 
		'mpsorderid'      => $payment_id,
		'mpsbill_name'    => $payer_name,
		'mpsbill_email'   => $payment_data['user_email'],
		'mpsbill_mobile'  => $payer_phone,
		'mpsbill_desc'    => $payment_data['post_data']['give-form-title'],
		'mpscountry'      => $payer_country,
		'mpsvcode'        => md5($payment_data['post_data']['give-amount'].give_get_option('razer_merchantid').$payment_id.give_get_option('razer_verify_key')),
		'mpscurrency'     => $payer_currency, 
		'mpslangcode'     => "en",
		'mpsreturnurl'    => $return_url,
		'mpstokenstatus'  => 1,
		'mpstcctype'	  => "SALS",
		'mpstimer'        => '8',
                'mpstimerbox'     => '#counter',
                'mpscancelurl'    => $return_url
	 );
                  
         echo '<button style="visibility: hidden; display: none;" type="button" id="myPay" data-toggle="molpayseamless"';
         foreach( $args as $key => $val ){
             if($key!='status')
                 echo ' data-'.$key.'="'.$val.'" ';
             }
             echo "></button>";
             echo "<h4 style='margin-top:15px;'>Your will redirect to Secured Payment Gateway...</h4>";
             echo "<div style='width:100%' id='counter'></div>";
        ?>
            <style>
            body{padding:0;margin:0;font-family:Arial,Helvetica,sans-serif}#header{width:100%;height:40px;background:#eee;margin-bottom:20px}.smalltxt{font-size:13px}.marginbttm{padding:7px}h1.margintop{border-top:1px solid #666;border-bottom:1px solid #666;margin-top:50px;margin-bottom:20px;padding:15px 0}.mandatory{color:red}/*!Order Table*/.ordertable-head{padding:10px 0;border-top:1px solid #bbb;border-bottom:1px solid #bbb;color:#666;font-weight:700}.ordertotal{border-top:1px solid #bbb;border-bottom:1px solid #bbb;padding:10px;font-size:15px;color:#444}/*!Button*/.bttn-couponapply{display:inline-block;padding:6px 0}.bttn-couponapply a,.bttn-couponapply a:hover{color:#7e287d}.hand{cursor:pointer}/*!Timer*/#counter{left:1%;position:fixed;top:30%;z-index:999999;padding:5px}#counter .mpslabels{color:#fff;margin-bottom:10px}#counter .mpsdelimeter{float:left;padding:5px;font-size:30px;color:#2d2d2d}#counter .mpsminutes,#counter .mpsseconds{color:#fff;float:left;font-size:40px;padding:5px 12px;text-align:center;background:#333;-moz-border-radius:6px;-webkit-border-radius:6px;border-radius:6px;border:0}#counter .mpsseconds.red{color:red}#counter small{font-size:15px}#footer{background:#f4f4f4;color:#666;padding:20px 0;margin-top:50px}ul.social{list-style-type:none;padding:0;margin:0}.social li{background-position:center;background-repeat:no-repeat;background-size:22px;display:inline-block;height:22px;width:22px}@font-face{font-family:si;src:url(socicon.eot);src:url(socicon.eot?#iefix) format('embedded-opentype'),url(socicon.woff) format('woff'),url(socicon.ttf) format('truetype'),url(socicon.svg#icomoonregular) format('svg');font-weight:400;font-style:normal}@media screen and (-webkit-min-device-pixel-ratio:0){@font-face{font-family:si;src:url(socicon.svg) format(svg)}}.soc{margin:0;padding:0;list-style:none;float:right}.soc li{display:inline-block;*display:inline;zoom:1}.soc li a{font-family:si!important;font-style:normal;font-weight:400;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;-ms-box-sizing:border-box;-o-box-sizing:border-box;box-sizing:border-box;-o-transition:.1s;-ms-transition:.1s;-moz-transition:.1s;-webkit-transition:.1s;transition:.1s;-webkit-transition-property:transform;transition-property:transform;-webkit-transform:translateZ(0);transform:translateZ(0);overflow:hidden;text-decoration:none;text-align:center;display:block;position:relative;z-index:1;width:25px;height:25px;line-height:25px;font-size:13px;-webkit-border-radius:1px;-moz-border-radius:1px;border-radius:1px;margin-right:15px;color:#fff;background-color:none;-webkit-filter:grayscale(100%);filter:grayscale(100%)}.soc a:hover{z-index:2;-webkit-transform:translateY(-5px);transform:translateY(-5px);-webkit-filter:grayscale(0%);filter:grayscale(0%)}.soc-icon-last{margin:0!important}.soc-facebook{background-color:#3e5b98}.soc-facebook:before{content:'b'}.soc-instagram{background-color:#9c7c6e}.soc-instagram:before{content:'x'}.soc-github{background-color:#5380c0}.soc-github:before{content:'Q'}.soc-twitter{background-color:#4da7de}.soc-twitter:before{content:'a'}.soc-google{background-color:#d93e2d}.soc-google:before{content:'c'}.soc-linkedin{background-color:#3371b7}.soc-linkedin:before{content:'j'}
            </style>
            <script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
            <script src="https://www.onlinepayment.com.my/MOLPay/API/seamless/latest/js/MOLPay_seamless.deco.js?rt=<?php echo date('HisYmd')?>"></script>
            <script>
                jQuery(document).ready(function(){
                        jQuery('#myPay').trigger('click');
                });                
            </script> 
        <?php
        
    }
    /* End of Hidden Form Generation */
    
    public function get_list_channel( $chn ) {
        $list_channels =  array(
                          'maybank2u'  => 'Maybank2u',
                          'cimbclicks' => 'CIMBClicks',
                          'hlb' => 'Hong Leong Bank',
                          'pbb' => 'Public Bank',
                          'rhb' => 'RHB Now',
                          'bankislam' => 'Bank Islam',
                          'credit'  => 'VISA/MasterCard',
                          'FIRSTDATA'  => 'VISA/MasterCard',
                          "fpx"      =>"Online Banking (through PayNet)",
                          "fpx_amb"  =>"Am Online",
                          "fpx_bimb" =>"Bank Islam",
                          "fpx_cimbclicks" =>"CIMB Clicks",
                          "fpx_hlb"  =>"Hong Leong Bank (HLB Connect)",
                          "fpx_mb2u" =>"Maybank2u",
                          "fpx_pbb"  =>"Public Bank (PBB Online)",
                          "fpx_rhb"  =>"RHB Now",
                          "fpx_ocbc" =>"OCBC Bank",
                          "fpx_scb"  =>"Standard Chartered Bank",
                          "fpx_abb"  =>"Affin Bank Berhad",
                          "fpx_abmb" =>"Alliance Bank (Alliance Online)",
                          "fpx_uob"  =>"United Overseas Bank (UOB)",
                          "fpx_bsn"  =>"Bank Simpanan Nasional (myBSN)",
                          "fpx_kfh"  =>"Kuwait Finance House",
                          "fpx_bkrm" =>"Bank Kerjasama Rakyat Malaysia",
                          "fpx_bmmb" =>"Bank Muamalat",
                          "fpx_hsbc" =>"Hongkong and Shanghai Banking Corporation",
                          'BOOST'  => 'Boost',
                          'WeChatPay'  => 'WeChatPay'
                      );            
        return $list_channels[$chn];                   
    }
    
    public function give_razer_phone_number_form_fields( $form_id ) {
        
        $is_meta_phone = false;
        // Check if the is form field "phone" created in give-form-fields-manager
        if( !empty(give_get_meta( $form_id, 'give-form-fields', true)) ){
        
          //Debug :: print_r(give_get_meta( $form_id, 'give-form-fields', true))
          
          $meta_ffm = json_encode(give_get_meta( $form_id, 'give-form-fields', true));
 
          //Debug :: echo $meta_ffm;
          
          if (strpos($meta_ffm, "phone") !== FALSE) {
              $is_meta_phone = true; 
          } else if (strpos($meta_ffm, "mobile") !== FALSE) {
              $is_meta_phone = true;
          } else if (strpos($meta_ffm, "contact") !== FALSE) {
              $is_meta_phone = true;
          }
          
        }  
    
        if( !$is_meta_phone ){
    ?>
        <p id="give-phone-wrap" class="form-row form-row-wide">
            <label class="give-label" for="give-phone">
                <?php esc_html_e( 'Phone Number', 'give' ); ?>
                <?php //if ( give_field_is_required( 'give_phone', $form_id ) ) { ?>
                    <span class="give-required-indicator">*</span>
                <?php //} ?>
                <span class="give-tooltip give-icon give-icon-question" data-tooltip="<?php esc_attr_e( 'Phone number information is required for this payment gateway.', 'give' ); ?>"></span>
            </label>
            <input
                class="give-input required"
                type="text"
                name="phone_number"
                placeholder="<?php esc_attr_e( 'Phone Number', 'give' ); ?>"
                id="give-phone"
                value="<?php echo isset( $give_user_info['give_phone'] ) ? $give_user_info['give_phone'] : ''; ?>"
                required aria-required="true"
            />
        </p>
    <?php
            do_action('give_payment_mode_after_phone');
        }
    }

    public function give_razer_ipn( $post ) {
        
        
    }  
}
Give_Razer_Gateway::get_instance();