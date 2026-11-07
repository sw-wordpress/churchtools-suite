<?php
/**
 * Bounded HTTPS requests. No authentication headers are forwarded.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

final class ChurchTools_Suite_Update_Transport {
	/**
	 * @return string|WP_Error Body, or the streamed file path.
	 */
	public static function fetch( string $url, int $limit, string $file = '' ): string|WP_Error {
		for ( $redirect = 0; $redirect <= 4; ++$redirect ) {
			if ( ! self::allowed( $url ) ) {
				return new WP_Error( 'unsafe_update_url', 'Nicht erlaubte Update-Adresse.' );
			}
			$args = array(
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => $limit + 1,
				'headers'             => array(
					'Accept'               => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2022-11-28',
					'User-Agent'           => 'SW-Verified-Plugin-Updates',
				),
			);
			if ( '' !== $file ) {
				$args['stream']            = true;
				$args['filename']          = $file;
				$args['timeout']           = 60;
				$args['headers']['Accept'] = 'application/octet-stream';
			}
			$response = wp_safe_remote_get( $url, $args );
			if ( is_wp_error( $response ) ) {
				return new WP_Error( 'update_network_error', 'GitHub konnte nicht erreicht werden.' );
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( ! is_string( $location ) ) {
					return new WP_Error( 'unsafe_update_url', 'Ungültige Weiterleitung.' );
				}
				$url = $location;
				continue;
			}
			if ( 200 !== $code ) {
				return new WP_Error( 'update_http_error', 'GitHub-Antwort: HTTP ' . $code );
			}
			$body  = '' === $file ? wp_remote_retrieve_body( $response ) : '';
			$bytes = '' === $file ? strlen( $body ) : filesize( $file );
			if ( false === $bytes || $bytes > $limit ) {
				return new WP_Error( 'update_too_large', 'Update-Antwort überschreitet das Größenlimit.' );
			}
			return '' === $file ? $body : $file;
		}
		return new WP_Error( 'update_redirect_limit', 'Zu viele Update-Weiterleitungen.' );
	}

	public static function allowed( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || isset( $parts['user'], $parts['pass'] ) || isset( $parts['user'] ) || isset( $parts['fragment'] ) || ( isset( $parts['port'] ) && 443 !== $parts['port'] ) ) {
			return false;
		}
		$host    = strtolower( $parts['host'] ?? '' );
		$path    = $parts['path'] ?? '';
		$decoded = rawurldecode( $path );
		if ( str_contains( $decoded, '\\' ) || str_contains( $decoded, chr( 0 ) ) || in_array( '..', explode( '/', $decoded ), true ) || in_array( '.', explode( '/', $decoded ), true ) ) {
			return false;
		}
		if ( 'api.github.com' === $host ) {
			return str_starts_with( $path, '/repos/' . ChurchTools_Suite_Update_Manifest::source()['repository'] . '/' );
		}
		if ( 'github.com' === $host ) {
			return str_starts_with( $path, '/' . ChurchTools_Suite_Update_Manifest::source()['repository'] . '/releases/download/' );
		}
		return in_array( $host, array( 'release-assets.githubusercontent.com', 'objects.githubusercontent.com' ), true );
	}
}
