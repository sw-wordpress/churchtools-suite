<?php
/**
 * Image Importer for ChurchTools Events
 *
 * Importiert Bilder von der ChurchTools API in die WordPress Media Library
 *
 * @package ChurchTools_Suite
 * @since   0.10.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Image_Importer {

	/**
	 * Import image from URL and store in WordPress media library
	 *
	 * @param string $image_url External image URL from ChurchTools API
	 * @param string $title Image title/alt text
	 * @param string $event_id Event ID for unique identification
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure
	 */
	public static function import_image( string $image_url, string $title = '', string $event_id = '' ): mixed {
		if ( empty( $image_url ) ) {
			return new WP_Error( 'empty_url', __( 'Bild-URL ist leer', 'churchtools-suite' ) );
		}

		// Validiere URL.
		if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_url', __( 'Ungültige Bild-URL', 'churchtools-suite' ) );
		}

		// Benutze WordPress-Funktion zum Herunterladen und Speichern.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Priorität für Dateiname: expliziter Name > URL (inkl. Query-Param) > Fallback.
		$title_filename = '';
		if ( ! empty( $title ) && is_string( $title ) && preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $title ) ) {
			$title_filename = $title;
		}
		$url_filename       = self::extract_filename_from_url( $image_url );
		$fallback_base      = ! empty( $event_id ) ? 'ct-event-' . sanitize_file_name( $event_id ) : 'ct-event-' . time();
		$filename_candidate = ! empty( $title_filename ) ? $title_filename : ( ! empty( $url_filename ) ? $url_filename : $fallback_base );

		// Preserve transformation and file-identifying parameters on every attempt.
		$tmp_file = self::download_authenticated( $image_url );
		if ( is_wp_error( $tmp_file ) ) {
			$tmp_file = download_url( $image_url, 300 ); // 5 Minuten Timeout
		}

		if ( is_wp_error( $tmp_file ) ) {
			return new WP_Error(
				'download_failed',
				sprintf(
					/* translators: 1: Download URL, 2: Download error message. */                    __( 'Fehler beim Herunterladen von %1$s: %2$s', 'churchtools-suite' ),
					esc_url( $image_url ),
					$tmp_file->get_error_message()
				)
			);
		}

		// Erhalte MIME-Type.
		$file_type = wp_check_filetype_and_ext( $tmp_file, (string) $filename_candidate );

		// Debug logging - log actual MIME type received.
		/* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional plugin diagnostics and fallback logging when the normal logger is unavailable. */ error_log(
			sprintf(
				'[CTS Image Import] URL: %s | File: %s | MIME Type: "%s" | Extension: "%s" | File Size: %d bytes',
				$image_url,
				basename( $tmp_file ),
				$file_type['type'] ?: 'EMPTY',
				$file_type['ext'] ?: 'NONE',
				filesize( $tmp_file )
			)
		);

		// Fallback 1: Für vertrauenswürdige church.tools URLs - nutze Dateiendung aus URL.
		if ( empty( $file_type['type'] ) && strpos( $image_url, 'church.tools' ) !== false ) {
			$parsed_url = wp_parse_url( $image_url, PHP_URL_PATH );
			$path_info  = pathinfo( $parsed_url );
			$extension  = strtolower( $path_info['extension'] ?? '' );

			$ext_to_mime = array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'gif'  => 'image/gif',
				'webp' => 'image/webp',
			);

			if ( isset( $ext_to_mime[ $extension ] ) ) {
				$file_type['type'] = $ext_to_mime[ $extension ];
				$file_type['ext']  = $extension;
				/* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional plugin diagnostics and fallback logging when the normal logger is unavailable. */ error_log(
					sprintf(
						'[CTS Image Import] FALLBACK 1 - church.tools MIME by extension: %s (%s)',
						$file_type['type'],
						$extension
					)
				);
			}
		}

		// Fallback 2: Wenn immer noch leer - prüfe Magic Bytes.
		if ( empty( $file_type['type'] ) ) {
			$file_type['type'] = self::detect_image_by_magic_bytes( $tmp_file );
			/* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional plugin diagnostics and fallback logging when the normal logger is unavailable. */ error_log( '[CTS Image Import] FALLBACK 2 - Detected by magic bytes: ' . ( $file_type['type'] ?: 'FAILED' ) );
		}

		if ( ! in_array( $file_type['type'], array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
			wp_delete_file( $tmp_file );
			$error_msg = sprintf( 'Nur Bilder (JPG, PNG, GIF, WebP) sind erlaubt. Erhalten: "%s"', $file_type['type'] ?: 'UNKNOWN' );
			/* phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional plugin diagnostics and fallback logging when the normal logger is unavailable. */ error_log( '[CTS Image Import] REJECTED - ' . $error_msg );
			return new WP_Error( 'invalid_type', $error_msg );
		}

		// Baue stabilen Ziel-Dateinamen (kein .tmp, Name aus ChurchTools behalten).
		$target_ext = $file_type['ext'] ?? '';
		if ( empty( $target_ext ) && ! empty( $file_type['type'] ) ) {
			$target_ext = self::mime_type_to_extension( $file_type['type'] );
		}

		$candidate_ext = strtolower( (string) pathinfo( $filename_candidate, PATHINFO_EXTENSION ) );
		if ( empty( $target_ext ) && in_array( $candidate_ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			$target_ext = $candidate_ext;
		}
		if ( empty( $target_ext ) ) {
			$target_ext = 'jpg';
		}

		$filename_base = sanitize_file_name( (string) pathinfo( $filename_candidate, PATHINFO_FILENAME ) );
		if ( empty( $filename_base ) ) {
			$filename_base = $fallback_base;
		}

		$final_filename = $filename_base . '.' . $target_ext;

		// Verschiebe Datei in Upload-Verzeichnis.
		$upload_dir      = wp_upload_dir();
		$unique_filename = wp_unique_filename( $upload_dir['path'], $final_filename );
		$target_file     = trailingslashit( $upload_dir['path'] ) . $unique_filename;

		/* phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Best-effort local filesystem operation; failures are handled or must not interrupt diagnostics and cleanup. Local temporary-file cleanup or atomic file move; no remote filesystem credentials are involved. */ if ( ! @rename( $tmp_file, $target_file ) ) {
			wp_delete_file( $tmp_file );
			return new WP_Error( 'move_failed', __( 'Fehler beim Verschieben der Datei', 'churchtools-suite' ) );
		}

		// Erstelle WordPress Attachment Post.
		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_text_field( $title ?: $filename_base ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_name'      => sanitize_title( $filename_base ),
		);

		$attachment_id = wp_insert_attachment( $attachment, $target_file );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $target_file );
			return $attachment_id;
		}

		// Generiere Thumbnail und Metadata.
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $target_file );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Setze Custom Meta um zu zeigen, dass es importiert wurde.
		update_post_meta( $attachment_id, '_cts_imported_from', $image_url );
		update_post_meta( $attachment_id, '_cts_event_id', $event_id );

		return $attachment_id;
	}

	/**
	 * Import a local file into the WordPress media library.
	 *
	 * @param string $source_path Absolute path to the source file
	 * @param string $title Attachment title
	 * @param string $slug Optional attachment slug
	 * @param array  $meta Optional attachment meta data
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure
	 */
	public static function import_local_file( string $source_path, string $title = '', string $slug = '', array $meta = array() ): mixed {
		if ( empty( $source_path ) || ! file_exists( $source_path ) || ! is_readable( $source_path ) ) {
			return new WP_Error( 'missing_file', __( 'Lokale Bilddatei nicht gefunden.', 'churchtools-suite' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $upload_dir['error'] );
		}

		if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
			return new WP_Error( 'upload_dir_create_failed', __( 'Upload-Verzeichnis konnte nicht erstellt werden.', 'churchtools-suite' ) );
		}

		$file_name   = basename( $source_path );
		$target_name = wp_unique_filename( $upload_dir['path'], sanitize_file_name( $file_name ) );
		$target_file = trailingslashit( $upload_dir['path'] ) . $target_name;

		/* phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort local filesystem operation; failures are handled or must not interrupt diagnostics and cleanup. */ if ( ! @copy( $source_path, $target_file ) ) {
			return new WP_Error( 'copy_failed', __( 'Datei konnte nicht in die Mediathek kopiert werden.', 'churchtools-suite' ) );
		}

		$extension = strtolower( (string) pathinfo( $target_file, PATHINFO_EXTENSION ) );
		$mime_map  = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'svg'  => 'image/svg+xml',
		);

		$mime_type        = $mime_map[ $extension ] ?? 'application/octet-stream';
		$attachment_title = sanitize_text_field( $title ?: pathinfo( $target_name, PATHINFO_FILENAME ) );
		$attachment_slug  = sanitize_title( $slug ?: pathinfo( $target_name, PATHINFO_FILENAME ) );

		$attachment = array(
			'post_mime_type' => $mime_type,
			'post_title'     => $attachment_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_name'      => $attachment_slug,
		);

		$attachment_id = wp_insert_attachment( $attachment, $target_file );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $target_file );
			return $attachment_id;
		}

		if ( 'image/svg+xml' !== $mime_type ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $target_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $attachment_id, (string) $meta_key, $meta_value );
		}

		update_post_meta( $attachment_id, '_cts_local_source_file', $source_path );

		return $attachment_id;
	}

	/**
	 * Download a file using the stored ChurchTools session cookies.
	 *
	 * ChurchTools fileUrl links (?q=public/filedownload...) require authentication,
	 * so a plain download_url() returns a login page / 403 instead of the image.
	 *
	 * @param string $url Image URL
	 * @return string|WP_Error Temp file path or error
	 */
	private static function download_authenticated( string $url ) {
		$cookie_data = get_option( 'churchtools_suite_ct_cookies', array() );
		$base_url    = (string) get_option( 'churchtools_suite_ct_url', '' );
		$host        = wp_parse_url( $url, PHP_URL_HOST );
		$base_host   = $base_url ? wp_parse_url( $base_url, PHP_URL_HOST ) : '';

		// Only send the session to the configured ChurchTools host.
		if ( empty( $cookie_data ) || ! is_array( $cookie_data ) || ! $host || ( $base_host && strcasecmp( $host, $base_host ) !== 0 ) ) {
			return new WP_Error( 'no_auth', 'No session cookies for host' );
		}

		$cookies = array();
		foreach ( $cookie_data as $cookie ) {
			$cookies[] = new WP_Http_Cookie(
				array(
					'name'    => $cookie['name'],
					'value'   => $cookie['value'],
					'expires' => $cookie['expires'] ?? null,
					'path'    => $cookie['path'] ?? '/',
					'domain'  => $cookie['domain'] ?? '',
				)
			);
		}

		$tmp_file = wp_tempnam( $url );
		if ( ! $tmp_file ) {
			return new WP_Error( 'tmp_failed', 'Could not create temp file' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $tmp_file,
				'cookies'  => $cookies,
			)
		);

		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			wp_delete_file( $tmp_file );
			return is_wp_error( $response ) ? $response : new WP_Error( 'http_error', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
		}

		return $tmp_file;
	}

	/**
	 * Get image URL from attachment ID
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @return string|false Image URL or false if not found
	 */
	public static function get_image_url( int $attachment_id ) {
		return wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Find existing image by URL to avoid duplicate imports for recurring events
	 *
	 * Searches for an attachment that was previously imported from the same URL
	 * (identified by _cts_imported_from meta key).
	 *
	 * @param string $image_url The external image URL to search for
	 * @return int Attachment ID if found, 0 otherwise
	 * @since 1.2.0.25
	 */
	public static function find_existing_image_by_url( string $image_url ): int {
		if ( empty( $image_url ) ) {
			return 0;
		}

		global $wpdb;

		// Search for attachment with same _cts_imported_from URL.
		$query = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
			'_cts_imported_from',
			$image_url
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom plugin tables and maintenance queries have no equivalent WordPress query API.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- SQL is assembled from fixed clauses and values passed through wpdb::prepare above.
		$attachment_id = $wpdb->get_var( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $attachment_id ? (int) $attachment_id : 0;
	}

	/**
	 * Delete imported image
	 *
	 * @param int $attachment_id WordPress attachment ID
	 * @return bool True on success
	 */
	public static function delete_image( int $attachment_id ): bool {
		if ( empty( $attachment_id ) ) {
			return false;
		}

		wp_delete_attachment( $attachment_id, true );
		return true;
	}

	/**
	 * Detect image MIME type by magic bytes (file signature)
	 *
	 * Fallback wenn WordPress MIME-Erkennung fehlschlägt
	 *
	 * @param string $file_path Path to file
	 * @return string|false MIME type or false
	 */
	private static function detect_image_by_magic_bytes( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		/* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Read only the first bytes of a local image for file-signature validation. */ $fh = fopen( $file_path, 'rb' );
		if ( ! $fh ) {
			return false;
		}

		/* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Read only the first bytes of a local image for file-signature validation. */ $bytes = fread( $fh, 12 ); // Read first 12 bytes for magic detection.
		/* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Read only the first bytes of a local image for file-signature validation. */ fclose( $fh );

		if ( empty( $bytes ) ) {
			return false;
		}

		// JPEG: FF D8 FF.
		if ( substr( $bytes, 0, 3 ) === "\xFF\xD8\xFF" ) {
			return 'image/jpeg';
		}

		// PNG: 89 50 4E 47.
		if ( substr( $bytes, 0, 4 ) === "\x89PNG" ) {
			return 'image/png';
		}

		// GIF: GIF87a oder GIF89a.
		if ( substr( $bytes, 0, 3 ) === 'GIF' ) {
			return 'image/gif';
		}

		// WebP: RIFF ... WEBP.
		if ( substr( $bytes, 0, 4 ) === 'RIFF' && substr( $bytes, 8, 4 ) === 'WEBP' ) {
			return 'image/webp';
		}

		return false;
	}

	/**
	 * Try to extract a human-readable filename from URL path or query.
	 */
	private static function extract_filename_from_url( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) ) {
			return '';
		}

		$query_name = '';
		if ( ! empty( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
			foreach ( array( 'filename', 'fileName', 'name', 'file', 'download' ) as $key ) {
				if ( ! empty( $query[ $key ] ) && is_string( $query[ $key ] ) ) {
					$query_name = (string) $query[ $key ];
					break;
				}
			}
		}

		if ( ! empty( $query_name ) ) {
			return sanitize_file_name( basename( $query_name ) );
		}

		if ( ! empty( $parsed['path'] ) ) {
			$path_name = basename( (string) $parsed['path'] );
			if ( ! empty( $path_name ) ) {
				return sanitize_file_name( $path_name );
			}
		}

		return '';
	}

	/**
	 * Map MIME type to file extension.
	 */
	private static function mime_type_to_extension( string $mime_type ): string {
		$map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		return $map[ $mime_type ] ?? '';
	}

	/**
	 * Check if image already imported
	 *
	 * @param string $image_url External image URL
	 * @return int|false Attachment ID if found, false otherwise
	 */
	public static function find_existing_image( string $image_url ) {
		if ( empty( $image_url ) ) {
			return false;
		}

		// Suche nach gespeicherten Import-URLs.
		$args = array(
			/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_key' => '_cts_imported_from',
			/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_value' => $image_url,
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0]->ID : false;
	}
}
