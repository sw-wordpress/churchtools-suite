<?php
/**
 * Connection state helper.
 *
 * The dashboard, the top bar of the new page shell and the settings screen all need to know
 * whether ChurchTools is configured and whether a session exists. That logic used to be copied
 * into `admin/views/tab-dashboard.php` (and partially into `tab-data.php`); it lives here once.
 *
 * @package ChurchTools_Suite
 * @since   1.4.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'churchtools_suite_connection_state' ) ) {
	/**
	 * Current ChurchTools connection state. Never exposes secrets.
	 *
	 * @return array{configured:bool, connected:bool, auth_method:string, has_url:bool, last_login:string}
	 */
	function churchtools_suite_connection_state(): array {
		$ct_url         = (string) get_option( 'churchtools_suite_ct_url', '' );
		$ct_auth_method = (string) get_option( 'churchtools_suite_ct_auth_method', 'password' );
		$ct_username    = (string) get_option( 'churchtools_suite_ct_username', '' );

		if ( ! class_exists( 'ChurchTools_Suite_Secret_Storage' ) && defined( 'CHURCHTOOLS_SUITE_PATH' ) ) {
			require_once CHURCHTOOLS_SUITE_PATH . 'includes/functions/secret-storage.php';
		}
		$stored_token = (string) get_option( 'churchtools_suite_ct_token', '' );
		$ct_token     = class_exists( 'ChurchTools_Suite_Secret_Storage' )
			? (string) ChurchTools_Suite_Secret_Storage::decrypt( $stored_token )
			: $stored_token;

		// The password option is stored encrypted as well; only its presence matters here.
		$has_password = '' !== (string) get_option( 'churchtools_suite_ct_password', '' );
		$cookies      = get_option( 'churchtools_suite_ct_cookies', array() );

		$token_auth = 'token' === $ct_auth_method;
		$configured = '' !== $ct_url && ( $token_auth ? '' !== $ct_token : ( '' !== $ct_username && $has_password ) );
		$connected  = $configured && ( $token_auth ? '' !== $ct_token : ! empty( $cookies ) );

		return array(
			'configured'  => $configured,
			'connected'   => $connected,
			'auth_method' => $token_auth ? 'token' : 'password',
			'has_url'     => '' !== $ct_url,
			'last_login'  => (string) get_option( 'churchtools_suite_ct_last_login', '' ),
		);
	}
}
