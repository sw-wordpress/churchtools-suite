<?php
/**
 * Parse connection form values consistently for saving and previewing a connection.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve blank secrets from the saved configuration only for the same account/host.
 *
 * @param array<string, mixed> $input Submitted, slashed WordPress form values.
 * @return array{url:string,auth_method:string,username:string,password:string,token:string}|WP_Error
 */
function churchtools_suite_connection_form_values( array $input ) {
	require_once __DIR__ . '/secret-storage.php';
	$value  = static function ( string $key ) use ( $input ): string {
		return isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
	};
	$tenant = strtolower( trim( $value( 'ct_tenant' ) ) );
	if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $tenant ) ) {
		return new WP_Error( 'invalid_tenant', __( 'Bitte den Namen der ChurchTools-Instanz ohne https:// und .church.tools eingeben.', 'churchtools-suite' ) );
	}
	$method   = 'token' === $value( 'ct_auth_method' ) ? 'token' : 'password';
	$url      = 'https://' . $tenant . '.church.tools';
	$username = sanitize_email( $value( 'ct_username' ) );
	$password = $value( 'ct_password' );
	$token    = trim( $value( 'ct_token' ) );
	$same_url = untrailingslashit( (string) get_option( 'churchtools_suite_ct_url', '' ) ) === $url;
	if ( 'token' === $method && '' === $username && $same_url ) {
		$username = (string) get_option( 'churchtools_suite_ct_username', '' );
	}
	if ( '' === $password && $same_url && (string) get_option( 'churchtools_suite_ct_username', '' ) === $username ) {
		$password = (string) get_option( 'churchtools_suite_ct_password', '' );
	}
	if ( '' === $token && $same_url ) {
		$token = ChurchTools_Suite_Secret_Storage::decrypt( (string) get_option( 'churchtools_suite_ct_token', '' ) );
	}
	if ( 'token' === $method ? '' === $token : ( '' === $username || '' === $password ) ) {
		return new WP_Error( 'missing_credentials', __( 'Bitte die Zugangsdaten ergänzen. Bei einer anderen Instanz oder einem anderen Benutzer das Passwort erneut eingeben.', 'churchtools-suite' ) );
	}
	return array(
		'url'         => $url,
		'auth_method' => $method,
		'username'    => $username,
		'password'    => $password,
		'token'       => $token,
	);
}
