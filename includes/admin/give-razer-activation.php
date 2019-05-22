<?php

/**
 * Give [Razer] Payment Gateway Activation
 *
 * @package     [Razer] for GiveWP
 * @copyright   Copyright (c) 2019, Razer
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       3.0.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

/**
 * Plugins row action links
 *
 * @since 3.0.2
 *
 * @param array $actions An array of plugin action links.
 *
 * @return array An array of updated action links.
 */
function give_razer_plugin_action_links($actions) {
  $new_actions = array(
    'settings' => sprintf(
      '<a href="%1$s">%2$s</a>', admin_url('edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=razer-settings'), esc_html__('Settings', 'give-razer')
    ),
  );
  return array_merge($new_actions, $actions);
}
add_filter('plugin_action_links_' . GIVE_RAZER_BASENAME, 'give_razer_plugin_action_links');
