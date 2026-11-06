<?php
/**
 * Validate the exact file handed to WordPress before any extraction.
 *
 * @package ChurchTools_Suite
 */

declare( strict_types=1 );

final class ChurchTools_Suite_Update_Package {
	/**
	 * @param string              $file Downloaded ZIP.
	 * @param array<string,mixed> $package
	 * @return true|WP_Error
	 * @throws RuntimeException Caught and converted into WP_Error.
	 */
	public static function verify( string $file, array $package ): true|WP_Error {
		clearstatcache( true, $file );
		$hash = is_file( $file ) ? hash_file( 'sha256', $file ) : false;
		if ( ! class_exists( 'ZipArchive' ) || ! is_file( $file ) || filesize( $file ) !== $package['bytes'] || ! is_string( $hash ) || ! hash_equals( $package['sha256'], $hash ) ) {
			return new WP_Error( 'invalid_update_file', 'ZIP-Größe oder SHA-256 stimmt nicht, oder ZIP-Prüfung fehlt.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return new WP_Error( 'invalid_update_zip', 'Ungültiges ZIP-Archiv.' );
		}
		try {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive API.
			$count = $zip->numFiles;
			if ( $count < 1 || $count > 10000 ) {
				throw new RuntimeException( 'Zu viele ZIP-Einträge.' );
			}
			$total = 0;
			$seen  = array();
			foreach ( range( 0, $count - 1 ) as $index ) {
				$stat = $zip->statIndex( $index );
				if ( false === $stat ) {
					throw new RuntimeException( 'ZIP-Eintrag unlesbar.' );
				}
				$name  = $stat['name'];
				$parts = explode( '/', rtrim( $name, '/' ) );
				$key   = strtolower( rtrim( $name, '/' ) );
				if ( ! $zip->getExternalAttributesIndex( $index, $opsys, $attributes ) ) {
					throw new RuntimeException( 'ZIP-Dateityp unlesbar.' );
				}
				$mode = ( $attributes >> 16 ) & 0170000;
				if ( $parts[0] !== $package['slug'] || preg_match( '/[\\\\:\x00-\x1f]/', $name ) || in_array( '..', $parts, true ) || in_array( '.', $parts, true ) || in_array( '', $parts, true ) || isset( $seen[ $key ] ) || ( 0 !== $mode && ! in_array( $mode, array( 0100000, 0040000 ), true ) ) || ! empty( $stat['encryption_method'] ) ) {
					throw new RuntimeException( 'Unsicherer ZIP-Pfad oder Dateityp.' );
				}
				$seen[ $key ] = true;
				$total       += $stat['size'];
				if ( $total > 262144000 || $stat['size'] > max( 1048576, 200 * $stat['comp_size'] ) ) {
					throw new RuntimeException( 'Entpacklimit überschritten.' );
				}
			}
			$main = $zip->getFromName( $package['slug'] . '/' . $package['main_file'], 16384 );
			if ( ! is_string( $main ) || ! preg_match( '/^[ \t\/*#@]*Plugin Name:\s*\S.+$/mi', $main ) ) {
				throw new RuntimeException( 'Plugin-Hauptdatei fehlt.' );
			}
			foreach ( array(
				'Version'           => 'version',
				'Requires PHP'      => 'requires_php',
				'Requires at least' => 'requires_wp',
			) as $header => $field ) {
				if ( ! preg_match( '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':\s*(.+)$/mi', $main, $match ) || trim( $match[1], " \t\r\n*/" ) !== $package[ $field ] ) {
					throw new RuntimeException( 'Plugin-Header widerspricht dem Manifest: ' . $header );
				}
			}
			return true;
		} catch ( Throwable $error ) {
			return new WP_Error( 'unsafe_update_zip', $error->getMessage() );
		} finally {
			$zip->close();
		}
	}
}
