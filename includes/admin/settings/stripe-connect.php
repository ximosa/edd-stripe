<?php
/*
 * Admin Settings: Stripe Connect
 *
 * @package EDD_Stripe\Admin\Settings\Stripe_Connect
 * @copyright Copyright (c) 2019, Sandhills Development, LLC
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 2.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determines if the Stripe API keys can be managed manually.
 *
 * @since 2.8.0
 *
 * @return bool
 */
function edds_stripe_connect_can_manage_keys() {
	$can_manage = true;

	/**
	 * Filters the ability to override the ability to manually manage
	 * Stripe API keys.
	 *
	 * @since 2.8.0
	 *
	 * @param bool $can_manage If the current user can manage API keys.
	 */
	$can_manage = apply_filters( 'edds_stripe_connect_can_manage_keys', $can_manage );

	return $can_manage;
}

/**
 * Retrieves a URL to allow Stripe Connect via oAuth.
 *
 * @since 2.8.0
 *
 * @return string
 */
function edds_stripe_connect_url() {
	$return_url = add_query_arg(
		array(
			'post_type' => 'download',
			'page'      => 'edd-settings',
			'tab'       => 'gateways',
			'section'   => 'edd-stripe',
		),
		admin_url( 'edit.php' )
	);

	/**
	 * Filters the URL users are returned to after using Stripe Connect oAuth.
	 *
	 * @since 2.8.0
	 *
	 * @param $return_url URL to return to.
	 */
	$return_url = apply_filters( 'edds_stripe_connect_return_url', $return_url );

	$stripe_connect_url = add_query_arg(
		array(
			'live_mode'         => (int) ! edd_is_test_mode(),
			'state'             => str_pad( wp_rand( wp_rand(), PHP_INT_MAX ), 100, wp_rand(), STR_PAD_BOTH ),
			'customer_site_url' => $return_url,
		),
		'https://easydigitaldownloads.com/?edd_gateway_connect_init=stripe_connect'
	);

	/**
	 * Filters the URL to start the Stripe Connect oAuth flow.
	 *
	 * @since 2.8.0
	 *
	 * @param $stripe_connect_url URL to oAuth proxy.
	 */
	$stripe_connect_url = apply_filters( 'edds_stripe_connect_url', $stripe_connect_url );

	return $stripe_connect_url;
}

/**
 * Returns a URL to disconnect the current Stripe Connect account ID and keys.
 *
 * @since 2.8.0
 *
 * @return string $stripe_connect_disconnect_url URL to disconnect an account ID and keys.
 */
function edds_stripe_connect_disconnect_url() {
	$stripe_connect_disconnect_url = add_query_arg(
		array(
			'post_type'             => 'download',
			'page'                  => 'edd-settings',
			'tab'                   => 'gateways',
			'section'               => 'edd-stripe',
			'edds-stripe-disconnect' => true,
		),
		admin_url( 'edit.php' )
	);

	/**
	 * Filters the URL to "disconnect" the Stripe Account.
	 *
	 * @since 2.8.0
	 *
	 * @param $stripe_connect_disconnect_url URL to remove the associated Account ID.
	 */
	$stripe_connect_disconnect_url = apply_filters(
		'edds_stripe_connect_disconnect_url',
		$stripe_connect_disconnect_url
	);

	$stripe_connect_disconnect_url = wp_nonce_url( $stripe_connect_disconnect_url, 'edds-stripe-connect-disconnect' );

	return $stripe_connect_disconnect_url;
}

/**
 * Removes the associated Stripe Connect Account ID and keys.
 *
 * This does not revoke application permissions from the Stripe Dashboard,
 * it simply allows the "Connect with Stripe" flow to run again for a different account.
 *
 * @since 2.8.0
 */
function edds_stripe_connect_process_disconnect() {
	// Current user cannot handle this request, bail.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// No nonce, bail.
	if ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( $_GET['_wpnonce'], 'edds-stripe-connect-disconnect' ) ) {
		return;
	}

	// Do not need to handle this request, bail.
	if (
		! ( isset( $_GET['page'] ) && 'edd-settings' === $_GET['page'] ) ||
		! isset( $_GET['edds-stripe-disconnect'] )
	) {
		return;
	}

	$options = array(
		'stripe_connect_account_id',
		'test_publishable_key',
		'test_secret_key',
		'live_publishable_key',
		'live_secret_key',
	);

	foreach ( $options as $option ) {
		edd_delete_option( $option );
	}

	$redirect = remove_query_arg(
		array(
			'_wpnonce',
			'edds-stripe-disconnect',
		)
	);

	return wp_redirect( esc_url_raw( $redirect ) );
}
add_action( 'admin_init', 'edds_stripe_connect_process_disconnect' );

/**
 * Renders custom HTML for the "Stripe Connect" setting field in the Stripe Payment Gateway
 * settings subtab.
 *
 * Provides a way to use Stripe Connect and manually manage API keys.
 *
 * @since 2.8.0
 */
function edds_stripe_connect_setting_field() {
	$stripe_connect_url        = edds_stripe_connect_url();
	$stripe_disconnect_url     = edds_stripe_connect_disconnect_url();

	$stripe_connect_account_id = edd_get_option( 'stripe_connect_account_id' );

	$api_key = edd_is_test_mode()
		? edd_get_option( 'test_publishable_key' )
		: edd_get_option( 'live_publishable_key' );

	ob_start();
?>

<?php if ( empty( $api_key ) ) : ?>

	<a href="<?php echo esc_url( $stripe_connect_url ); ?>" class="edd-stripe-connect">
		<span><?php esc_html_e( 'Connect with Stripe', 'edds' ); ?></span>
	</a>
	<p>
	<?php
		echo wp_kses(
			sprintf(
				/* translators: %1$s Opening anchor tag, do not translate. %2$s Closing anchor tag, do not translate. */
				__( 'Have questions about connecting with Stripe? See the %1$sdocumentation%2$s.', 'edds' ),
				'<a href="https://docs.easydigitaldownloads.com/article/2039-how-does-stripe-connect-affect-me" target="_blank" rel="noopener noreferrer">',
				'</a>'
			),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				)
			)
		);
	?>
	</p>

<?php endif; ?>

<?php if ( ! empty( $api_key ) ) : ?>

	<div
		id="edds-stripe-connect-account"
		class="edds-stripe-connect-acount-info notice inline"
		data-account-id="<?php echo esc_attr( $stripe_connect_account_id ); ?>"
		data-nonce="<?php echo wp_create_nonce( 'edds-stripe-connect-account-information' ); ?>"
	>
		<p><span class="spinner is-active"></span>
		<em><?php esc_html_e( 'Retrieving account information...', 'edds' ); ?></em>
	</div>
	<div id="edds-stripe-disconnect-reconnect">
	</div>

<?php endif; ?>

<?php if ( true === edds_stripe_connect_can_manage_keys() ) : ?>

	<div class="edds-api-key-toggle">
		<p>
			<button type="button" class="button-link">
				<small>
					<?php esc_html_e( 'Manage API keys manually', 'edds' ); ?>
				</small>
			</button>
		</p>
	</div>

	<div class="edds-api-key-toggle edd-hidden">
		<p>
			<button type="button" class="button-link">
				<small>
					<?php esc_html_e( 'Hide API keys', 'edds' ); ?>
				</small>
			</button>
		</p>

		<div class="notice inline notice-warning" style="margin: 15px 0 -10px;">
			<?php echo wpautop( esc_html__( 'Although you can add your API keys manually, we recommend using Stripe Connect: an easier and more secure way of connecting your Stripe account to your website. Stripe Connect prevents issues that can arise when copying and pasting account details from Stripe into your Easy Digital Downloads payment gateway settings. With Stripe Connect you\'ll be ready to go with just a few clicks.', 'edds' ) ); ?>
		</div>
	</div>

<?php endif; ?>

<?php
	return ob_get_clean();
}

/**
 * Responds to an AJAX request about the current Stripe connection status.
 *
 * @since 2.8.0
 */
function edds_stripe_connect_account_info_ajax_response() {
	// Generic error.
	$unknown_error = array(
		'message' => wpautop( esc_html__( 'Unable to retrieve account information.', 'edds' ) ),
	);

	// Nonce validation, show error on fail.
	if ( ! wp_verify_nonce( $_POST['nonce'], 'edds-stripe-connect-account-information' ) ) {
		return wp_send_json_error( $unknown_error );
	}

	// No account ID to use, show error on fail.
	if ( ! isset( $_POST['accountId'] ) ) {
		return wp_send_json_error( $unknown_error );
	}

	$account_id = sanitize_text_field( $_POST['accountId'] );

	$mode = edd_is_test_mode()
		? _x( 'test', 'Stripe Connect mode', 'edds' )
		: _x( 'live', 'Stripe Connect mode', 'edds' );

	// Provides general reconnect and disconnect action URLs.
	$reconnect_disconnect_actions = wp_kses(
		sprintf(
			/* translators: %1$s Stripe payment mode. %2$s Opening anchor tag for reconnecting to Stripe, do not translate. %3$s Opening anchor tag for disconnecting Stripe, do not translate. %4$s Closing anchor tag, do not translate. */
			__( 'Your Stripe account is connected in %1$s mode. %2$sReconnect in %1$s mode%4$s, or %3$sdisconnect this account%4$s.', 'edds' ),
			'<strong>' . $mode . '</strong>',
			'<a href="' . esc_url( edds_stripe_connect_url() ) . '" rel="noopener noreferrer">',
			'<a href="' . esc_url( edds_stripe_connect_disconnect_url() ) . '">',
			'</a>'
		),
		array(
			'strong' => true,
			'a'      => array(
				'href' => true,
				'rel'  => true,
			)
		)
	);

	// If connecting in Test Mode Stripe gives you the opportunity to create a temporary account.
	// This can be confusing if you are eventually logged out of this account without activating it.
	//
	// Alert the user that it should be saved.
	$dev_account_error = array(
		'message' => wpautop(
			esc_html__( 'You are currently connected to an unsaved Stripe account.', 'edds' ) . ' ' .
			sprintf(
				/* translators: %1$s Opening anchor tag, do not translate. %2$s Closing anchor tag, do not translate */
				esc_html__( 'Please %1$ssave your account in Stripe%2$s before continuing.', 'edds' ),
				'<a href="https://dashboard.stripe.com/account/details" target="_blank" rel="noopener noreferrer">',
				'</a>'
			) . '<br />' .
			'<strong>' . esc_html__( 'You will not be able to reconnect to this account unless it is saved.', 'edds' ) . '</strong>' .
			'<br /><br />' .
			wp_kses(
				sprintf(
					/* translators: %1$s Opening anchor tag for disconnecting Stripe, do not translate. %2$s Closing anchor tag, do not translate. */
					__( '%1$sDisconnect this account%2$s.', 'edds' ),
					'<a href="' . esc_url( edds_stripe_connect_disconnect_url() ) . '">',
					'</a>'
				),
				array(
					'strong' => true,
					'a'      => array(
						'href' => true,
						'rel'  => true,
					)
				)
			)
		),
		'status' => 'warning',
	);

	// Attempt to show account information from Stripe Connect account.
	if ( ! empty( $account_id ) ) {
		try {
			$account = edds_api_request( 'Account', 'retrieve', $account_id );

			// Unsaved/unactivated accounts do not have an email.
			if ( ! $account->email ) {
				return wp_send_json_success( $dev_account_error );
			}

			// Find a Display Name.
			$display_name = isset( $account->display_name )
				? esc_html( $account->display_name )
				: '';

			if ( empty( $display_name ) && isset( $account->settings ) ) {
				$display_name = esc_html( $account->settings->dashboard->display_name );
			}

			if ( ! empty( $display_name ) ) {
				$display_name = '<strong>' . $display_name . '</strong><br/ >';
			}

			// Return a message with name, email, and reconnect/disconnect actions.
			return wp_send_json_success(
				array(
					'message' => wpautop(
						// $display_name is already escaped
						$display_name . esc_html( $account->email ) . ' &mdash; ' . esc_html__( 'Administrator (Owner)', 'edds' )
					),
					'actions' => $reconnect_disconnect_actions,
					'status'  => 'success',
				)
			);
		} catch ( \Stripe\Exception\AuthenticationException $e ) {
			// API keys were changed after using Stripe Connect.
			return wp_send_json_error(
				array(
					'message' => wpautop(
						esc_html__( 'The API keys provided do not match the Stripe Connect account associated with this installation. If you have manually modified these values after connecting your account, please reconnect below or update your API keys.', 'edds' ) . 
						'<br /><br />' .
						$reconnect_disconnect_actions
					),
				)
			);
		} catch ( \Exception $e ) {
			// General error.
			return wp_send_json_error( $unknown_error );
		}
	// Manual API key management.
	} else {
		$connect_button = sprintf(
			'<a href="%s" class="edd-stripe-connect"><span>%s</span></a>',
			esc_url( edds_stripe_connect_url() ),
			esc_html__( 'Connect with Stripe', 'edds' )
		);

		$connect = esc_html__( 'It is highly recommended to Connect with Stripe for easier setup and improved security.', 'edds' );

		// See if the keys are valid.
		try {
			// While we could show similar account information, leave it blank to help
			// push people towards Stripe Connect.
			$account = edds_api_request( 'Account', 'retrieve' );

			return wp_send_json_success(
				array(
					'message' => wpautop(
						sprintf(
							/* translators: %1$s Stripe payment mode.*/
							__( 'Your manually managed %1$s mode API keys are valid.', 'edds' ),
							'<strong>' . $mode . '</strong>'
						) .
						'<br /><br />' .
						$connect . '<br /><br />' . $connect_button
					),
					'status' => 'success',
				)
			);
		// Show invalid keys.
		} catch ( \Exception $e ) {
			return wp_send_json_error(
				array(
					'message' => wpautop(
						sprintf(
							/* translators: %1$s Stripe payment mode.*/
							__( 'Your manually managed %1$s mode API keys are invalid.', 'edds' ),
							'<strong>' . $mode . '</strong>'
						) .
						'<br /><br />' .
						$connect . '<br /><br />' . $connect_button
					),
				)
			);
		}
	}
}
add_action( 'wp_ajax_edds_stripe_connect_account_info', 'edds_stripe_connect_account_info_ajax_response' );

/**
 * Registers admin notices for Stripe Connect.
 *
 * @since 2.8.0
 *
 * @return true|WP_Error True if all notices are registered, otherwise WP_Error.
 */
function edds_stripe_connect_admin_notices_register() {
	$registry = edds_get_registry( 'admin-notices' );

	if ( ! $registry ) {
		return new WP_Error( 'edds-invalid-registry', esc_html__( 'Unable to locate registry', 'edds' ) );
	}

	$connect_button = sprintf(
		'<a href="%s" class="edd-stripe-connect"><span>%s</span></a>',
		esc_url( edds_stripe_connect_url() ),
		esc_html__( 'Connect with Stripe', 'edds' )
	);

	try {
		// Stripe Connect.
		$registry->add(
			'stripe-connect',
			array(
				'message'     => sprintf(
					'<p>%s</p><p>%s</p>',
					esc_html__( 'Start accepting payments with Stripe by connecting your account. Stripe Connect helps ensure easier setup and improved security.', 'edds' ),
					$connect_button
				),
				'type'        => 'info',
				'dismissible' => true,
			)
		);

		// Stripe Connect reconnect.
		/** translators: %s Test mode status. */
		$test_mode_status = edd_is_test_mode()
			? _x( 'enabled', 'gateway test mode status', 'edds' )
			: _x( 'disabled', 'gateway test mode status', 'edds' );

		$registry->add(
			'stripe-connect-reconnect',
			array(
				'message'     => sprintf(
					'<p>%s</p><p>%s</p>',
					sprintf(
						/* translators: %s Test mode status. Enabled or disabled. */
						__( '"Test Mode" has been %s. Please verify your Stripe connection status.', 'edds' ),
						$test_mode_status
					),
					$connect_button
				),
				'type'        => 'warning',
				'dismissible' => true,
			)
		);
	} catch( Exception $e ) {
		return new WP_Error( 'edds-invalid-notices-registration', esc_html__( $e->getMessage() ) );
	};

	return true;
}
add_action( 'admin_init', 'edds_stripe_connect_admin_notices_register' );

/**
 * Conditionally prints registered notices.
 *
 * @since 2.6.19
 */
function edds_stripe_connect_admin_notices_print() {
	// Current user needs capability to dismiss notices.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$registry = edds_get_registry( 'admin-notices' );

	if ( ! $registry ) {
		return;
	}

	$notices = new EDD_Stripe_Admin_Notices( $registry );

	wp_enqueue_script( 'edds-admin-notices' );

	try {
		$enabled_gateways = edd_get_enabled_payment_gateways();

		$api_key = true === edd_is_test_mode()
			? edd_get_option( 'test_secret_key' )
			: edd_get_option( 'live_secret_key' );

		$mode_toggle = isset( $_GET['edd-message'] ) && 'connect-to-stripe' === $_GET['edd-message'];

		if ( array_key_exists( 'stripe', $enabled_gateways ) && empty( $api_key ) ) {
			wp_enqueue_style(
				'edd-stripe-admin-styles',
				EDDSTRIPE_PLUGIN_URL . 'assets/css/build/admin.min.css',
				array(),
				EDD_STRIPE_VERSION
			);

			// Stripe Connect.
			if ( false === $mode_toggle ) {
				$notices->output( 'stripe-connect' );
			// Stripe Connect reconnect.
			} else {
				$notices->output( 'stripe-connect-reconnect' );
			}
		}
	} catch( Exception $e ) {}
}
add_action( 'admin_notices', 'edds_stripe_connect_admin_notices_print' );
