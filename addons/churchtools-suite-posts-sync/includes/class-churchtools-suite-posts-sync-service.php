<?php
/**
 * ChurchTools Posts Sync Service
 *
 * Syncs ChurchTools posts into WordPress posts or pages.
 *
 * @package ChurchTools_Suite_Posts_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChurchTools_Suite_Posts_Sync_Service {

	const META_CT_POST_ID            = '_cts_ct_post_id';
	const META_CT_POST_HASH          = '_cts_ct_post_hash';
	const META_CT_POST_GUID          = '_cts_ct_post_guid';
	const META_CT_GROUP_ID           = '_cts_ct_group_id';
	const META_CT_GROUP_TITLE        = '_cts_ct_group_title';
	const META_CT_GROUP_VISIBILITY   = '_cts_ct_group_visibility';
	const META_CT_POST_VISIBILITY    = '_cts_ct_post_visibility';
	const META_CT_COMMENTS_ACTIVE    = '_cts_ct_comments_active';
	const META_CT_IMAGES             = '_cts_ct_images';
	const META_CT_PUBLICATION_DATE   = '_cts_ct_publication_date';
	const META_CT_EXPIRATION_DATE    = '_cts_ct_expiration_date';
	const META_CT_PUBLISHED_DATE     = '_cts_ct_published_date';
	const META_CT_LAST_EDITED_DATE   = '_cts_ct_last_edited_date';
	const META_CT_IS_BANNED          = '_cts_ct_is_banned';
	const META_CT_ACTOR_ID           = '_cts_ct_actor_id';
	const META_CT_ACTOR_NAME         = '_cts_ct_actor_name';
	const META_CT_ACTOR_IMAGE        = '_cts_ct_actor_image';
	const META_CT_CREATED_DATE       = '_cts_ct_created_date';
	const META_CT_MODIFIED_DATE      = '_cts_ct_modified_date';
	const META_CT_CREATED_PERSON_ID  = '_cts_ct_created_person_id';
	const META_CT_MODIFIED_PERSON_ID = '_cts_ct_modified_person_id';
	const META_CT_RAW_PAYLOAD        = '_cts_ct_raw_payload';
	const META_CT_GROUP_TERM_ID      = '_cts_ct_group_term_id';

	/**
	 * @var ChurchTools_Suite_CT_Client
	 */
	private $client;

	public function __construct( $client ) {
		$this->client = $client;
	}

	public function sync_posts( array $args = array() ) {
		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-parsedown.php';

		$target_type            = get_option( 'churchtools_suite_ct_posts_target_type', 'post' );
		$supported_target_types = $this->get_supported_target_types();
		if ( ! in_array( $target_type, $supported_target_types, true ) ) {
			$target_type = in_array( CTS_POSTS_SYNC_CPT, $supported_target_types, true ) ? CTS_POSTS_SYNC_CPT : 'post';
		}

		$target_status = get_option( 'churchtools_suite_ct_posts_target_status', 'draft' );
		if ( ! in_array( $target_status, array( 'draft', 'publish', 'private' ), true ) ) {
			$target_status = 'draft';
		}

		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : (int) get_option( 'churchtools_suite_ct_posts_sync_limit', 200 );
		if ( $limit < 1 ) {
			$limit = 200;
		}
		if ( $limit > 1000 ) {
			$limit = 1000;
		}

		$query_params          = $this->get_sync_query_params( $args );
		$query_params['limit'] = $limit;

		$response = $this->client->api_request( 'posts', 'GET', $query_params );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$items = $this->extract_posts_from_response( $response );

		$stats             = array(
			'posts_found'           => count( $items ),
			'posts_created'         => 0,
			'posts_updated'         => 0,
			'posts_skipped'         => 0,
			'posts_missing_checked' => 0,
			'posts_privated'        => 0,
			'errors'                => 0,
			'target_type'           => $target_type,
			'target_status'         => $target_status,
			'query'                 => $query_params,
		);
		$response_post_ids = array();

		foreach ( $items as $item ) {
			$normalized = $this->normalize_ct_post( $item );
			if ( ! empty( $normalized['ct_id'] ) ) {
				$response_post_ids[] = (string) $normalized['ct_id'];
			}

			if ( empty( $normalized['ct_id'] ) || empty( $normalized['title'] ) ) {
				++$stats['errors'];
				continue;
			}

			$existing_post_id = $this->find_existing_post_id( $normalized['ct_id'] );

			$payload_hash = md5(
				(string) wp_json_encode(
					array(
						'normalized'    => $normalized,
						'target_type'   => $target_type,
						'target_status' => $target_status,
					)
				)
			);

			if ( $existing_post_id > 0 ) {
				$current_hash = (string) get_post_meta( $existing_post_id, self::META_CT_POST_HASH, true );
				$current_post = get_post( $existing_post_id );
				if ( $current_post && $current_hash === $payload_hash && $current_post->post_type === $target_type && $current_post->post_status === $target_status ) {
					if ( is_wp_error( $this->ensure_featured_image( $existing_post_id, $normalized ) ) ) {
						++$stats['errors'];
					}
					++$stats['posts_skipped'];
					continue;
				}

				$update_result = wp_update_post(
					array(
						'ID'           => $existing_post_id,
						'post_type'    => $target_type,
						'post_status'  => $target_status,
						'post_title'   => $normalized['title'],
						'post_content' => $normalized['content'],
						'post_excerpt' => $normalized['excerpt'],
						'post_name'    => $normalized['slug'],
						'post_date'    => $normalized['date'],
					),
					true
				);

				if ( is_wp_error( $update_result ) ) {
					++$stats['errors'];
					continue;
				}

				update_post_meta( $existing_post_id, self::META_CT_POST_HASH, $payload_hash );
				$this->update_ct_post_meta( $existing_post_id, $normalized );
				$this->assign_group_category( $existing_post_id, $normalized, $target_type );
				if ( is_wp_error( $this->ensure_featured_image( $existing_post_id, $normalized ) ) ) {
					++$stats['errors'];
				}
				++$stats['posts_updated'];
				continue;
			}

			$insert_result = wp_insert_post(
				array(
					'post_type'    => $target_type,
					'post_status'  => $target_status,
					'post_title'   => $normalized['title'],
					'post_content' => $normalized['content'],
					'post_excerpt' => $normalized['excerpt'],
					'post_name'    => $normalized['slug'],
					'post_date'    => $normalized['date'],
				),
				true
			);

			if ( is_wp_error( $insert_result ) ) {
				++$stats['errors'];
				continue;
			}

			$new_post_id = (int) $insert_result;
			update_post_meta( $new_post_id, self::META_CT_POST_ID, (string) $normalized['ct_id'] );
			update_post_meta( $new_post_id, self::META_CT_POST_HASH, $payload_hash );
			$this->update_ct_post_meta( $new_post_id, $normalized );
			$this->assign_group_category( $new_post_id, $normalized, $target_type );
			if ( is_wp_error( $this->ensure_featured_image( $new_post_id, $normalized ) ) ) {
				++$stats['errors'];
			}
			++$stats['posts_created'];
		}

		$response_post_ids = array_values( array_unique( $response_post_ids ) );
		$this->privatize_missing_ct_posts( $response_post_ids, $target_type, $stats );

		update_option(
			'churchtools_suite_posts_sync_last_result',
			array(
				'run_at' => current_time( 'mysql' ),
				'stats'  => $stats,
			),
			false
		);

		return $stats;
	}

	/**
	 * Verify published synced entries of the configured target post type.
	 *
	 * A post is made private only when the detail endpoint confirms that the
	 * ChurchTools post no longer exists (HTTP 404) or returns no usable data.
	 * Other API errors are counted but do not change the WordPress post status.
	 *
	 * @param array<int, string>   $response_post_ids ChurchTools IDs from the list response.
	 * @param string               $target_type       Configured WordPress target post type.
	 * @param array<string, mixed> $stats             Sync statistics, updated by reference.
	 */
	private function privatize_missing_ct_posts( array $response_post_ids, string $target_type, array &$stats ): void {
		$published_posts = get_posts(
			array(
				'post_type'        => $target_type,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_query' => array(
					array(
						'key'     => self::META_CT_POST_ID,
						'compare' => 'EXISTS',
					),
				),
				'suppress_filters' => true,
			)
		);

		foreach ( $published_posts as $post_id ) {
			$ct_post_id = trim( (string) get_post_meta( (int) $post_id, self::META_CT_POST_ID, true ) );
			if ( '' === $ct_post_id || in_array( $ct_post_id, $response_post_ids, true ) ) {
				continue;
			}

			++$stats['posts_missing_checked'];
			$detail_response = $this->client->api_request( 'posts/' . rawurlencode( $ct_post_id ), 'GET' );

			if ( ! $this->is_missing_ct_post_response( $detail_response ) ) {
				if ( is_wp_error( $detail_response ) ) {
					++$stats['errors'];
				}
				continue;
			}

			$update_result = wp_update_post(
				array(
					'ID'          => (int) $post_id,
					'post_status' => 'private',
				),
				true
			);

			if ( is_wp_error( $update_result ) ) {
				++$stats['errors'];
				continue;
			}

			++$stats['posts_privated'];
		}
	}

	/**
	 * Determine whether a detail response means that a ChurchTools post is gone.
	 *
	 * @param array<string, mixed>|WP_Error $response Detail endpoint response.
	 */
	private function is_missing_ct_post_response( $response ): bool {
		if ( is_wp_error( $response ) ) {
			if ( 'empty_response' === $response->get_error_code() ) {
				return true;
			}

			$error_data = $response->get_error_data();
			return is_array( $error_data ) && 404 === (int) ( $error_data['status'] ?? 0 );
		}

		if ( empty( $response ) ) {
			return true;
		}

		return array_key_exists( 'data', $response ) && empty( $response['data'] );
	}

	private function extract_posts_from_response( array $response ): array {
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$data = $response['data'];
			if ( $this->is_numeric_array( $data ) ) {
				return $data;
			}
			foreach ( array( 'posts', 'items', 'results' ) as $key ) {
				if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
					return $data[ $key ];
				}
			}
		}

		foreach ( array( 'posts', 'items', 'results' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				return $response[ $key ];
			}
		}

		if ( isset( $response['id'] ) ) {
			return array( $response );
		}

		return array();
	}

	private function is_numeric_array( array $array ): bool {
		if ( array() === $array ) {
			return true;
		}
		return array_keys( $array ) === range( 0, count( $array ) - 1 );
	}

	private function normalize_ct_post( $item ): array {
		$ct_id   = (string) ( $item['id'] ?? $item['postId'] ?? $item['uid'] ?? '' );
		$ct_guid = (string) ( $item['guid'] ?? '' );
		$title   = trim( (string) ( $item['title'] ?? $item['name'] ?? $item['headline'] ?? '' ) );
		if ( '' === $title && '' !== $ct_id ) {
			$title = sprintf( 'ChurchTools Post %s', $ct_id );
		}

		$content_raw = (string) ( $item['content'] ?? $item['body'] ?? $item['text'] ?? $item['description'] ?? '' );
		$excerpt     = (string) ( $item['excerpt'] ?? $item['summary'] ?? $item['teaser'] ?? '' );

		$slug = (string) ( $item['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}

		$date_source = (string) ( $item['publishedDate'] ?? $item['publicationDate'] ?? $item['publishedAt'] ?? $item['publishDate'] ?? $item['date'] ?? ( $item['meta']['createdDate'] ?? $item['createdDate'] ?? '' ) );
		$date        = current_time( 'mysql' );
		if ( '' !== $date_source ) {
			$timestamp = strtotime( $date_source );
			if ( $timestamp ) {
				$date = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		$group_id         = (string) ( $item['group']['domainIdentifier'] ?? $item['groupId'] ?? '' );
		$group_title      = (string) ( $item['group']['title'] ?? '' );
		$group_visibility = (string) ( $item['groupVisibility'] ?? $item['group']['domainAttributes']['visibility'] ?? '' );
		$post_visibility  = (string) ( $item['visibility'] ?? '' );
		$comments_active  = ! empty( $item['commentsActive'] ) ? 1 : 0;
		$image_candidates = array();
		if ( isset( $item['images'] ) && is_array( $item['images'] ) ) {
			$image_candidates = array_merge( $image_candidates, $item['images'] );
		}
		if ( isset( $item['image'] ) ) {
			$image_candidates[] = $item['image'];
		}
		if ( isset( $item['imageUrl'] ) ) {
			$image_candidates[] = $item['imageUrl'];
		}

		$parse_down = new ChurchTools_Suite_Parsedown();
		$images     = $this->extract_image_urls( $image_candidates );
		$content    = $this->normalize_content_with_images( $content_raw, $images );
		$content    = $parse_down->text( $content );
		$images     = $this->extract_image_urls( array_merge( $images, $this->extract_content_image_urls( $parse_down->text( $content_raw ) ) ) );

		$publication_date   = (string) ( $item['publicationDate'] ?? '' );
		$expiration_date    = (string) ( $item['expirationDate'] ?? '' );
		$published_date     = (string) ( $item['publishedDate'] ?? '' );
		$last_edited_date   = (string) ( $item['lastEditedDate'] ?? '' );
		$is_banned          = ! empty( $item['isBanned'] ) ? 1 : 0;
		$actor_id           = (string) ( $item['actor']['domainIdentifier'] ?? $item['actorId'] ?? '' );
		$actor_name         = (string) ( $item['actor']['title'] ?? '' );
		$actor_image        = (string) ( $item['actor']['imageUrl'] ?? '' );
		$created_date       = (string) ( $item['meta']['createdDate'] ?? '' );
		$modified_date      = (string) ( $item['meta']['modifiedDate'] ?? '' );
		$created_person_id  = (string) ( $item['meta']['createdPerson']['id'] ?? '' );
		$modified_person_id = (string) ( $item['meta']['modifiedPerson']['id'] ?? '' );
		$raw_payload        = (string) wp_json_encode( $item );

		return array(
			'ct_id'              => $ct_id,
			'ct_guid'            => $ct_guid,
			'title'              => wp_strip_all_tags( $title ),
			'content'            => $content,
			'excerpt'            => wp_strip_all_tags( $excerpt ),
			'slug'               => $slug,
			'date'               => $date,
			'group_id'           => $group_id,
			'group_title'        => $group_title,
			'group_visibility'   => $group_visibility,
			'post_visibility'    => $post_visibility,
			'comments_active'    => $comments_active,
			'images'             => $images,
			'publication_date'   => $publication_date,
			'expiration_date'    => $expiration_date,
			'published_date'     => $published_date,
			'last_edited_date'   => $last_edited_date,
			'is_banned'          => $is_banned,
			'actor_id'           => $actor_id,
			'actor_name'         => $actor_name,
			'actor_image'        => $actor_image,
			'created_date'       => $created_date,
			'modified_date'      => $modified_date,
			'created_person_id'  => $created_person_id,
			'modified_person_id' => $modified_person_id,
			'raw_payload'        => $raw_payload,
		);
	}

	private function find_existing_post_id( string $ct_post_id ): int {
		if ( '' === $ct_post_id ) {
			return 0;
		}

		$existing_posts = get_posts(
			array(
				'post_type'        => $this->get_supported_target_types(),
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_key' => self::META_CT_POST_ID,
				/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_value' => $ct_post_id,
				'suppress_filters' => true,
			)
		);

		if ( ! empty( $existing_posts ) ) {
			return (int) $existing_posts[0];
		}

		return 0;
	}

	private function get_supported_target_types(): array {
		if ( class_exists( 'ChurchTools_Suite_Posts_Sync' ) && method_exists( 'ChurchTools_Suite_Posts_Sync', 'get_supported_target_types' ) ) {
			return ChurchTools_Suite_Posts_Sync::get_supported_target_types();
		}

		return array( 'post', 'page', defined( 'CTS_POSTS_SYNC_CPT' ) ? CTS_POSTS_SYNC_CPT : 'ct_post' );
	}

	private function get_sync_query_params( array $args = array() ): array {
		$params = array();

		$after = isset( $args['after'] ) ? (string) $args['after'] : (string) get_option( 'churchtools_suite_ct_posts_after', '' );
		if ( '' !== $after ) {
			$params['after'] = $after;
		}

		$before = isset( $args['before'] ) ? (string) $args['before'] : (string) get_option( 'churchtools_suite_ct_posts_before', '' );
		if ( '' !== $before ) {
			$params['before'] = $before;
		}

		$campus_ids = isset( $args['campus_ids'] ) && is_array( $args['campus_ids'] ) ? $args['campus_ids'] : $this->parse_id_list_option( 'churchtools_suite_ct_posts_campus_ids' );
		if ( ! empty( $campus_ids ) ) {
			$params['campus_ids'] = $campus_ids;
		}

		$actor_ids = isset( $args['actor_ids'] ) && is_array( $args['actor_ids'] ) ? $args['actor_ids'] : $this->parse_id_list_option( 'churchtools_suite_ct_posts_actor_ids' );
		if ( ! empty( $actor_ids ) ) {
			$params['actor_ids'] = $actor_ids;
		}

		$group_ids = isset( $args['group_ids'] ) && is_array( $args['group_ids'] ) ? $args['group_ids'] : $this->parse_id_list_option( 'churchtools_suite_ct_posts_group_ids' );
		if ( ! empty( $group_ids ) ) {
			$params['group_ids'] = $group_ids;
		}

		$group_visibility = isset( $args['group_visibility'] ) ? sanitize_key( (string) $args['group_visibility'] ) : sanitize_key( (string) get_option( 'churchtools_suite_ct_posts_group_visibility', '' ) );
		if ( '' !== $group_visibility ) {
			$params['group_visibility'] = $group_visibility;
		}

		$post_visibility = isset( $args['post_visibility'] ) ? sanitize_key( (string) $args['post_visibility'] ) : sanitize_key( (string) get_option( 'churchtools_suite_ct_posts_post_visibility', '' ) );
		if ( '' !== $post_visibility ) {
			$params['post_visibility'] = $post_visibility;
		}

		$only_my_groups = isset( $args['only_my_groups'] ) ? (bool) $args['only_my_groups'] : (bool) get_option( 'churchtools_suite_ct_posts_only_my_groups', 0 );
		if ( $only_my_groups ) {
			$params['only_my_groups'] = 'true';
		}

		$include = array();
		if ( ! empty( $args['include'] ) && is_array( $args['include'] ) ) {
			$include = array_values( array_map( 'sanitize_key', $args['include'] ) );
		} else {
			if ( (int) get_option( 'churchtools_suite_ct_posts_include_comments', 0 ) === 1 ) {
				$include[] = 'comments';
			}
			if ( (int) get_option( 'churchtools_suite_ct_posts_include_linkings', 0 ) === 1 ) {
				$include[] = 'linkings';
			}
			if ( (int) get_option( 'churchtools_suite_ct_posts_include_reactions', 0 ) === 1 ) {
				$include[] = 'reactions';
			}
		}

		if ( ! empty( $include ) ) {
			$params['include'] = array_values( array_unique( $include ) );
		}

		return $params;
	}

	private function parse_id_list_option( string $option_name ): array {
		$raw = (string) get_option( $option_name, '' );
		if ( '' === $raw ) {
			return array();
		}

		$parts = preg_split( '/[^0-9]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$ids = array();
		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			$ids[] = (int) $part;
		}

		$ids = array_values( array_unique( array_filter( $ids, static fn( $value ) => $value > 0 ) ) );
		return $ids;
	}

	/**
	 * Collect inline images after rendering Markdown, including reference images.
	 *
	 * @return list<string>
	 */
	private function extract_content_image_urls( string $html ): array {
		preg_match_all( '/<img\b[^>]*?\s+src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $matches, PREG_SET_ORDER );
		$urls = array();
		foreach ( $matches as $match ) {
			$url    = ( $match[1] ?? '' ) ?: ( ( $match[2] ?? '' ) ?: ( $match[3] ?? '' ) );
			$urls[] = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return $urls;
	}

	/**
	 * Backfill missing thumbnails even when the post payload has not changed.
	 * Existing thumbnails (including manually selected ones) are preserved.
	 *
	 * @param int                  $post_id    WordPress post ID.
	 * @param array<string, mixed> $normalized Normalized post data.
	 * @return true|WP_Error
	 */
	private function ensure_featured_image( int $post_id, array $normalized ) {
		if ( has_post_thumbnail( $post_id ) || empty( $normalized['images'] ) ) {
			return true;
		}

		require_once CHURCHTOOLS_SUITE_PATH . 'includes/class-churchtools-suite-image-importer.php';
		$image_url     = (string) $normalized['images'][0];
		$attachment_id = ChurchTools_Suite_Image_Importer::find_existing_image( $image_url );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			$attachment_id = ChurchTools_Suite_Image_Importer::import_image( $image_url, $normalized['title'] );
		}
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		if ( ! set_post_thumbnail( $post_id, (int) $attachment_id ) ) {
			return new WP_Error( 'thumbnail_failed', __( 'Beitragsbild konnte nicht gesetzt werden.', 'churchtools-suite-posts-sync' ) );
		}
		return true;
	}

	private function extract_image_urls( array $images ): array {
		$urls = array();

		foreach ( $images as $image ) {
			$url = '';

			if ( is_string( $image ) ) {
				$url = $image;
			} elseif ( is_array( $image ) ) {
				$candidates = array(
					$image['url'] ?? '',
					$image['imageUrl'] ?? '',
					$image['src'] ?? '',
					$image['href'] ?? '',
					isset( $image['file'] ) && is_array( $image['file'] ) ? ( $image['file']['url'] ?? '' ) : '',
				);

				foreach ( $candidates as $candidate ) {
					if ( is_string( $candidate ) && trim( $candidate ) !== '' ) {
						$url = $candidate;
						break;
					}
				}
			}

			$url = $this->normalize_possible_relative_url( $url );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		$urls = array_values( array_unique( $urls ) );
		return $urls;
	}

	private function normalize_content_with_images( string $content, array $images ): string {
		$content = trim( $content );

		$post_image_orientation = sanitize_key( (string) get_option( 'churchtools_suite_post_img_orientation', 'vertical' ) );

		if ( '' !== $content ) {
			$content = preg_replace_callback(
				'/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/',
				function ( array $matches ): string {
					$alt = isset( $matches[1] ) ? trim( (string) $matches[1] ) : '';
					$src = isset( $matches[2] ) ? $this->normalize_possible_relative_url( (string) $matches[2] ) : '';
					if ( '' === $src ) {
						return '';
					}

					return '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" />';
				},
				$content
			);

			$content = $this->normalize_relative_urls_in_html( $content );
		}

		$missing_images_markup = array();

		foreach ( $images as $image_url ) {
			$image_url = (string) $image_url;
			if ( '' === $image_url ) {
				continue;
			}

			if ( '' !== $content && str_contains( $content, $image_url ) ) {
				continue;
			}

			$image_url  = esc_url( $image_url );
			$image_data = apply_filters(
				'churchtools_suite_image_posts',
				array(
					'link_url'   => $image_url . '?w=auto&h=1080&fm=webp&crop=original',
					'img_src'    => $image_url . '?w=auto&h=480&fm=webp&crop=original',

					'link_class' => '',
					'img_class'  => '',
				)
			);

			$missing_images_markup[] = '<a href="' . $image_data['link_url'] . '" class="' . $image_data['link_class'] . '"><img src="' . $image_data['img_src'] . '" class="' . $image_data['img_class'] . '" alt="" /></a>';
		}

		if ( ! empty( $missing_images_markup ) ) {

			if ( 'horizontal' === $post_image_orientation ) {
				$image_html = "<div style='display:flex;gap:10px;'>";
			} else {
				$image_html = '';
			}

			foreach ( $missing_images_markup as $image ) {
				if ( 'horizontal' === $post_image_orientation ) {
					$image_html .= $image;
				} else {
					$image_html .= '<p>' . $image . "</p>\n";
				}
			}

			if ( 'horizontal' === $post_image_orientation ) {
				$image_html .= '</div>';
			}

			$content = trim( $content . "\n\n" . $image_html );
		}

		return wp_kses_post( $content );
	}

	private function normalize_relative_urls_in_html( string $html ): string {
		if ( '' === $html ) {
			return $html;
		}

		$pattern    = '/(src|href)\s*=\s*(["\'])\/(.*?)\2/i';
		$normalized = preg_replace_callback(
			$pattern,
			function ( array $matches ): string {
				$attr  = isset( $matches[1] ) ? (string) $matches[1] : 'src';
				$quote = isset( $matches[2] ) ? (string) $matches[2] : '"';
				$path  = isset( $matches[3] ) ? (string) $matches[3] : '';
				$url   = $this->normalize_possible_relative_url( '/' . ltrim( $path, '/' ) );
				if ( '' === $url ) {
					return $matches[0];
				}

				return $attr . '=' . $quote . esc_url( $url ) . $quote;
			},
			$html
		);

		return is_string( $normalized ) ? $normalized : $html;
	}

	private function normalize_possible_relative_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url_raw( $url );
		}

		if ( str_starts_with( $url, '//' ) ) {
			$base        = (string) get_option( 'churchtools_suite_ct_url', '' );
			$base_scheme = (string) wp_parse_url( $base, PHP_URL_SCHEME );
			if ( '' === $base_scheme ) {
				$base_scheme = 'https';
			}

			return esc_url_raw( $base_scheme . ':' . $url );
		}

		if ( str_starts_with( $url, '/' ) ) {
			$base = (string) get_option( 'churchtools_suite_ct_url', '' );
			$base = trim( $base );
			if ( '' === $base ) {
				return '';
			}

			return esc_url_raw( rtrim( $base, '/' ) . '/' . ltrim( $url, '/' ) );
		}

		return esc_url_raw( $url );
	}

	private function assign_group_category( int $post_id, array $normalized, string $target_type ): void {
		if ( ! is_object_in_taxonomy( $target_type, 'category' ) ) {
			return;
		}

		$group_id    = (string) ( $normalized['group_id'] ?? '' );
		$group_title = (string) ( $normalized['group_title'] ?? '' );
		if ( '' === $group_title ) {
			return;
		}

		$term_id = self::ensure_group_category_term( $group_id, $group_title );
		if ( $term_id <= 0 ) {
			return;
		}

		$current_term_ids = wp_get_post_terms( $post_id, 'category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current_term_ids ) || ! is_array( $current_term_ids ) ) {
			$current_term_ids = array();
		}

		$previous_ct_term_id = (int) get_post_meta( $post_id, self::META_CT_GROUP_TERM_ID, true );
		if ( $previous_ct_term_id > 0 && $previous_ct_term_id !== $term_id ) {
			$current_term_ids = array_values(
				array_filter(
					array_map( 'intval', $current_term_ids ),
					static fn( int $id ): bool => $id !== $previous_ct_term_id
				)
			);
		}

		$current_term_ids[] = $term_id;
		$current_term_ids   = array_values( array_unique( array_map( 'intval', $current_term_ids ) ) );

		$result = wp_set_post_terms( $post_id, $current_term_ids, 'category', false );
		if ( is_wp_error( $result ) ) {
			return;
		}

		update_post_meta( $post_id, self::META_CT_GROUP_TERM_ID, $term_id );
	}

	public static function ensure_group_category_term( string $group_id, string $group_title ): int {
		$group_title = trim( $group_title );
		if ( '' === $group_title ) {
			return 0;
		}

		$group_id         = trim( $group_id );
		$existing_term_id = 0;

		if ( '' !== $group_id ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => false,
					'number'     => 1,
					'fields'     => 'ids',
					/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_key' => '_cts_ct_group_id',
					/* phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Metadata lookup is required to match imported ChurchTools records to their existing WordPress posts. */ 'meta_value' => $group_id,
				)
			);

			if ( ! is_wp_error( $terms ) && is_array( $terms ) && ! empty( $terms ) ) {
				$existing_term_id = (int) $terms[0];
			}
		}

		if ( $existing_term_id > 0 ) {
			wp_update_term(
				$existing_term_id,
				'category',
				array(
					'name' => $group_title,
				)
			);

			if ( '' !== $group_id ) {
				update_term_meta( $existing_term_id, '_cts_ct_group_id', $group_id );
			}
			update_term_meta( $existing_term_id, '_cts_ct_group_title', $group_title );

			return $existing_term_id;
		}

		$slug   = 'ct-group-' . ( '' !== $group_id ? $group_id . '-' : '' ) . sanitize_title( $group_title );
		$insert = wp_insert_term(
			$group_title,
			'category',
			array(
				'slug' => sanitize_title( $slug ),
			)
		);

		if ( is_wp_error( $insert ) ) {
			$fallback = get_term_by( 'name', $group_title, 'category' );
			if ( $fallback instanceof WP_Term ) {
				$term_id = (int) $fallback->term_id;
				if ( '' !== $group_id ) {
					update_term_meta( $term_id, '_cts_ct_group_id', $group_id );
				}
				update_term_meta( $term_id, '_cts_ct_group_title', $group_title );
				return $term_id;
			}

			return 0;
		}

		$term_id = (int) ( $insert['term_id'] ?? 0 );
		if ( $term_id > 0 ) {
			if ( '' !== $group_id ) {
				update_term_meta( $term_id, '_cts_ct_group_id', $group_id );
			}
			update_term_meta( $term_id, '_cts_ct_group_title', $group_title );
		}

		return $term_id;
	}

	private function update_ct_post_meta( int $post_id, array $normalized ): void {
		update_post_meta( $post_id, self::META_CT_POST_GUID, (string) ( $normalized['ct_guid'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_GROUP_ID, (string) ( $normalized['group_id'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_GROUP_TITLE, (string) ( $normalized['group_title'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_GROUP_VISIBILITY, (string) ( $normalized['group_visibility'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_POST_VISIBILITY, (string) ( $normalized['post_visibility'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_COMMENTS_ACTIVE, (int) ( $normalized['comments_active'] ?? 0 ) );
		update_post_meta( $post_id, self::META_CT_IMAGES, wp_json_encode( $normalized['images'] ?? array() ) );
		update_post_meta( $post_id, self::META_CT_PUBLICATION_DATE, (string) ( $normalized['publication_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_EXPIRATION_DATE, (string) ( $normalized['expiration_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_PUBLISHED_DATE, (string) ( $normalized['published_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_LAST_EDITED_DATE, (string) ( $normalized['last_edited_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_IS_BANNED, (int) ( $normalized['is_banned'] ?? 0 ) );
		update_post_meta( $post_id, self::META_CT_ACTOR_ID, (string) ( $normalized['actor_id'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_ACTOR_NAME, (string) ( $normalized['actor_name'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_ACTOR_IMAGE, (string) ( $normalized['actor_image'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_CREATED_DATE, (string) ( $normalized['created_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_MODIFIED_DATE, (string) ( $normalized['modified_date'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_CREATED_PERSON_ID, (string) ( $normalized['created_person_id'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_MODIFIED_PERSON_ID, (string) ( $normalized['modified_person_id'] ?? '' ) );
		update_post_meta( $post_id, self::META_CT_RAW_PAYLOAD, (string) ( $normalized['raw_payload'] ?? '' ) );
	}
}
