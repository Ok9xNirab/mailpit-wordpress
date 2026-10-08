<?php
/**
 * Clean up on uninstall.
 *
 * @package mailpit-wordpress
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mailpit_wp_settings' );
delete_transient( 'mailpit_wp_last_error' );
