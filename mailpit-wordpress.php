<?php
/**
 * Plugin Name:       Mailpit for WordPress
 * Plugin URI:        https://github.com/Ok9xNirab/mailpit-wordpress
 * Description:       Routes all outgoing WordPress mail (wp_mail) through a local Mailpit SMTP server for testing.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Istiaq Nirab
 * Author URI:        https://nirab.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mailpit-wordpress
 *
 * Settings can be overridden in wp-config.php:
 *   define( 'MAILPIT_HOST', '127.0.0.1' );
 *   define( 'MAILPIT_SMTP_PORT', 1025 );
 *   define( 'MAILPIT_UI_URL', 'http://localhost:8025' );
 *   define( 'MAILPIT_USERNAME', '' ); // only if Mailpit SMTP auth is enabled
 *   define( 'MAILPIT_PASSWORD', '' );
 */

defined( 'ABSPATH' ) || exit;

define( 'MAILPIT_WP_OPTION', 'mailpit_wp_settings' );

/**
 * Get merged settings: wp-config constants > saved options > defaults.
 *
 * @return array{enabled:bool,host:string,port:int,ui_url:string,username:string,password:string}
 */
function mailpit_wp_settings() {
	$defaults = array(
		'enabled'  => true,
		'host'     => '127.0.0.1',
		'port'     => 1025,
		'ui_url'   => 'http://localhost:8025',
		'username' => '',
		'password' => '',
	);

	$settings = wp_parse_args( (array) get_option( MAILPIT_WP_OPTION, array() ), $defaults );

	$constants = array(
		'host'     => 'MAILPIT_HOST',
		'port'     => 'MAILPIT_SMTP_PORT',
		'ui_url'   => 'MAILPIT_UI_URL',
		'username' => 'MAILPIT_USERNAME',
		'password' => 'MAILPIT_PASSWORD',
	);
	foreach ( $constants as $key => $constant ) {
		if ( defined( $constant ) ) {
			$settings[ $key ] = constant( $constant );
		}
	}

	$settings['enabled'] = (bool) $settings['enabled'];
	$settings['port']    = (int) $settings['port'];

	return $settings;
}

/**
 * Point PHPMailer at Mailpit.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
 */
function mailpit_wp_configure_phpmailer( $phpmailer ) {
	$settings = mailpit_wp_settings();
	if ( ! $settings['enabled'] ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host        = $settings['host'];
	$phpmailer->Port        = $settings['port'];
	$phpmailer->SMTPSecure  = '';
	$phpmailer->SMTPAutoTLS = false;
	$phpmailer->SMTPAuth    = '' !== $settings['username'];

	if ( $phpmailer->SMTPAuth ) {
		$phpmailer->Username = $settings['username'];
		$phpmailer->Password = $settings['password'];
	}
}
add_action( 'phpmailer_init', 'mailpit_wp_configure_phpmailer', PHP_INT_MAX );

/**
 * Store the last mail failure so it can be shown on the settings page.
 *
 * @param WP_Error $error Mail error.
 */
function mailpit_wp_log_failure( $error ) {
	set_transient( 'mailpit_wp_last_error', $error->get_error_message(), HOUR_IN_SECONDS );
}
add_action( 'wp_mail_failed', 'mailpit_wp_log_failure' );

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Send a test email through Mailpit.
	 *
	 * ## OPTIONS
	 *
	 * [<to>]
	 * : Recipient. Defaults to the site admin email.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mailpit test
	 *     wp mailpit test someone@example.com
	 *
	 * @param array $args Positional args.
	 */
	$mailpit_wp_cli_test = function ( $args ) {
		$to   = isset( $args[0] ) ? $args[0] : get_option( 'admin_email' );
		$sent = wp_mail( $to, 'Mailpit test from ' . get_bloginfo( 'name' ), 'If you can read this in Mailpit, it works.' );
		if ( $sent ) {
			WP_CLI::success( sprintf( 'Sent to %s. View it at %s', $to, mailpit_wp_settings()['ui_url'] ) );
		} else {
			WP_CLI::error( 'Send failed: ' . get_transient( 'mailpit_wp_last_error' ) );
		}
	};
	WP_CLI::add_command( 'mailpit test', $mailpit_wp_cli_test );
}
