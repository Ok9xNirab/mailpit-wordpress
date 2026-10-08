<?php
/**
 * Admin settings page: Tools → Mailpit.
 *
 * @package mailpit-wordpress
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the setting.
 */
function mailpit_wp_register_settings() {
	register_setting(
		'mailpit_wp',
		MAILPIT_WP_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mailpit_wp_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'mailpit_wp_register_settings' );

/**
 * Sanitize settings input.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function mailpit_wp_sanitize_settings( $input ) {
	$input = is_array( $input ) ? $input : array();

	return array(
		'enabled'  => ! empty( $input['enabled'] ),
		'host'     => isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '127.0.0.1',
		'port'     => isset( $input['port'] ) ? max( 1, min( 65535, absint( $input['port'] ) ) ) : 1025,
		'ui_url'   => isset( $input['ui_url'] ) ? esc_url_raw( $input['ui_url'] ) : 'http://localhost:8025',
		'username' => isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '',
		'password' => isset( $input['password'] ) ? sanitize_text_field( $input['password'] ) : '',
	);
}

/**
 * Add the Tools submenu page.
 */
function mailpit_wp_admin_menu() {
	add_management_page(
		__( 'Mailpit', 'mailpit-wordpress' ),
		__( 'Mailpit', 'mailpit-wordpress' ),
		'manage_options',
		'mailpit-wordpress',
		'mailpit_wp_render_page'
	);
}
add_action( 'admin_menu', 'mailpit_wp_admin_menu' );

/**
 * Handle the "send test email" form.
 */
function mailpit_wp_handle_test_email() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'mailpit-wordpress' ) );
	}
	check_admin_referer( 'mailpit_wp_test_email' );

	$to = isset( $_POST['mailpit_to'] ) ? sanitize_email( wp_unslash( $_POST['mailpit_to'] ) ) : '';
	if ( ! is_email( $to ) ) {
		$to = get_option( 'admin_email' );
	}

	delete_transient( 'mailpit_wp_last_error' );
	$sent = wp_mail(
		$to,
		sprintf( 'Mailpit test from %s', get_bloginfo( 'name' ) ),
		"If you can read this in Mailpit, it works.\n\nSent at " . current_time( 'mysql' )
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'         => 'mailpit-wordpress',
				'mailpit_sent' => $sent ? '1' : '0',
			),
			admin_url( 'tools.php' )
		)
	);
	exit;
}
add_action( 'admin_post_mailpit_wp_test_email', 'mailpit_wp_handle_test_email' );

/**
 * Render the settings page.
 */
function mailpit_wp_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = mailpit_wp_settings();
	$name     = MAILPIT_WP_OPTION;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag.
	$sent_flag = isset( $_GET['mailpit_sent'] ) ? sanitize_key( $_GET['mailpit_sent'] ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Mailpit', 'mailpit-wordpress' ); ?></h1>

		<?php if ( '1' === $sent_flag ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Test email sent.', 'mailpit-wordpress' ); ?>
				<a href="<?php echo esc_url( $settings['ui_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Mailpit', 'mailpit-wordpress' ); ?></a>
			</p></div>
		<?php elseif ( '0' === $sent_flag ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php esc_html_e( 'Test email failed:', 'mailpit-wordpress' ); ?>
				<?php echo esc_html( (string) get_transient( 'mailpit_wp_last_error' ) ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'mailpit_wp' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'mailpit-wordpress' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Route all wp_mail() through Mailpit', 'mailpit-wordpress' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="mailpit-host"><?php esc_html_e( 'SMTP host', 'mailpit-wordpress' ); ?></label></th>
					<td><input id="mailpit-host" class="regular-text" type="text" name="<?php echo esc_attr( $name ); ?>[host]" value="<?php echo esc_attr( $settings['host'] ); ?>" <?php disabled( defined( 'MAILPIT_HOST' ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="mailpit-port"><?php esc_html_e( 'SMTP port', 'mailpit-wordpress' ); ?></label></th>
					<td><input id="mailpit-port" class="small-text" type="number" min="1" max="65535" name="<?php echo esc_attr( $name ); ?>[port]" value="<?php echo esc_attr( $settings['port'] ); ?>" <?php disabled( defined( 'MAILPIT_SMTP_PORT' ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="mailpit-ui"><?php esc_html_e( 'Web UI URL', 'mailpit-wordpress' ); ?></label></th>
					<td><input id="mailpit-ui" class="regular-text" type="url" name="<?php echo esc_attr( $name ); ?>[ui_url]" value="<?php echo esc_attr( $settings['ui_url'] ); ?>" <?php disabled( defined( 'MAILPIT_UI_URL' ) ); ?>></td>
				</tr>
				<tr>
					<th scope="row"><label for="mailpit-user"><?php esc_html_e( 'SMTP username', 'mailpit-wordpress' ); ?></label></th>
					<td><input id="mailpit-user" class="regular-text" type="text" autocomplete="off" name="<?php echo esc_attr( $name ); ?>[username]" value="<?php echo esc_attr( $settings['username'] ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty unless Mailpit was started with --smtp-auth-file.', 'mailpit-wordpress' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="mailpit-pass"><?php esc_html_e( 'SMTP password', 'mailpit-wordpress' ); ?></label></th>
					<td><input id="mailpit-pass" class="regular-text" type="password" autocomplete="new-password" name="<?php echo esc_attr( $name ); ?>[password]" value="<?php echo esc_attr( $settings['password'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2><?php esc_html_e( 'Send a test email', 'mailpit-wordpress' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="mailpit_wp_test_email">
			<?php wp_nonce_field( 'mailpit_wp_test_email' ); ?>
			<label for="mailpit-to" class="screen-reader-text"><?php esc_html_e( 'Recipient', 'mailpit-wordpress' ); ?></label>
			<input id="mailpit-to" class="regular-text" type="email" name="mailpit_to" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
			<?php submit_button( __( 'Send test email', 'mailpit-wordpress' ), 'secondary', 'submit', false ); ?>
			<a class="button" href="<?php echo esc_url( $settings['ui_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open Mailpit inbox', 'mailpit-wordpress' ); ?></a>
		</form>
	</div>
	<?php
}
