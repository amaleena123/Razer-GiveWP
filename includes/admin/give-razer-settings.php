<?php

/**
 * Class Give_Razer_Settings
 *
 * @since 1.0.0
 */
class Give_Razer_Settings {

  /**
   * @access private
   * @var Give_Razer_Settings $instance
   */
  static private $instance;

  /**
   * @access private
   * @var string $section_id
   */
  private $section_id;

  /**
   * @access private
   *
   * @var string $section_label
   */
  private $section_label;

  /**
   * Give_Razer_Settings constructor.
   */
  private function __construct() {

  }

  /**
   * get class object.
   *
   * @return Give_Razer_Settings
   */
  static function get_instance() {
    if (null === static::$instance) {
      static::$instance = new static();
    }

    return static::$instance;
  }

  /**
   * Setup hooks.
   */
  public function setup_hooks() {

    $this->section_id    = 'razer';
    $this->section_label = __('Razer', 'give-razer');

    if (is_admin()) {
      // Add settings.
      add_filter('give_settings_gateways', array($this, 'add_settings'), 99);
    }
  }

  /**
   * Add setting section.
   *
   * @param array $sections Array of section.
   *
   * @return array
   */
  public function add_section($sections) {
    $sections[$this->section_id] = $this->section_label;

    return $sections;
  }

  /**
   * Add plugin settings.
   *
   * @param array $settings Array of setting fields.
   *
   * @return array
   */
  public function add_settings($settings) {
      $give_razer_settings = array(
          array(
              'name' => __('Razer Settings', 'give-razer'),
              'id'   => 'give_title_razer',
              'type' => 'give_title',
          ),
          array(
              'name'        => __('Merchant ID', 'give-razer'),
              'desc'        => __('Enter Merchant ID,  found in your Razer Merchant Portal Settings.', 'give-razer'),
              'id'          => 'razer_merchantid',
              'type'        => 'text',
              //'row_classes' => 'give-razer-key',
          ),
          array(
              'name'        => __('Verify Key', 'give-razer'),
              'desc'        => __('Enter your Verify Key, found in your Razer Merchant Portal Settings.', 'give-razer'),
              'id'          => 'razer_verify_key',
              'type'        => 'text',
          ),
          array(
              'name'        => __('Secret Key', 'give-billplz'),
              'desc'        => __('Enter your Secret Key, found in your Razer Merchant Portal Settings.', 'give-razer'),
              'id'          => 'razer_secret_key',
              'type'        => 'text',
          ),
          array(
              'name'        => __('Online Banking Channel Setting', 'give-razer'),
              'desc'        => __('Select Online Banking Channel.', 'give-razer'),
              'id'          => 'razer_onlinebanking_channel',
              'type' => 'multicheck',
              'options' => array(
                 'none' => 'Disable this channel setting',
                 /*
                 'maybank2u'  => 'Maybank2u',
                 'cimbclicks' => 'CIMBClicks',
                 'hlb' => 'Hong Leong Bank',
                 'pbb' => 'Public Bank',
                 'rhb' => 'RHB Now',
                 'bankislam' => 'Bank Islam',
                 */
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
              )
          ),
          array(
              'name'        => __('Credit/Debit Card Channel Setting', 'give-razer'),
              'desc'        => __('Select Credit/Debit Card Channel.', 'give-razer'),
              'id'          => 'razer_cc_channel',
              'type' => 'multicheck',
              'options' => array(
                  'none' => 'Disable this channel setting',
                  'credit'  => 'Visa/Mastercard Card (Malaysia)',
                  'FIRSTDATA'  => 'Visa/Mastercard Card (Overseas) - must request from Razer Support team'
               )

          ),
          array(
              'name'        => __('E-Wallet Channel Setting', 'give-razer'),
              'desc'        => __('Select E-Wallet Channel.', 'give-razer'),
              'id'          => 'razer_ewallet_channel',
              'type' => 'multicheck',
              'options' => array(
                  'none' => 'Disable this channel setting',
                  'BOOST'  => 'Boost'
               )

          ),
    );

    return array_merge($settings, $give_razer_settings);
  }
}

Give_Razer_Settings::get_instance()->setup_hooks();
