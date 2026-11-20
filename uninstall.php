<?php
/**
 * Runs when the plugin is deleted via the WordPress admin. Removes the option and any pending challenge transients.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('yl_captcha');

global $wpdb;
// Transients are named ylc_<hash>; remove both the value and the timeout rows.
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like('_transient_ylc_') . '%', $wpdb->esc_like('_transient_timeout_ylc_') . '%'));
