<?php
/** Regression tests for imported posts and their featured images. */
final class PostsSyncImagesTest extends CTS_TestCase {
	private array $downloads = array();
	private bool $download_fails = false;

	public function set_up(): void {
		parent::set_up();
		require_once CHURCHTOOLS_SUITE_PATH . 'addons/churchtools-suite-posts-sync/includes/class-churchtools-suite-posts-sync-service.php';
		update_option( 'churchtools_suite_ct_posts_target_type', 'post' );
		update_option( 'churchtools_suite_ct_posts_target_status', 'draft' );
		update_option( 'churchtools_suite_ct_url', 'https://example.org' );
		update_option( 'churchtools_suite_ct_cookies', array( array( 'name' => 'session', 'value' => 'fixture' ) ) );
		remove_all_filters( 'pre_http_request' );
		add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
			$this->downloads[] = $url;
			if ( $this->download_fails ) {
				return new WP_Error( 'test_download_failed', 'Image temporarily unavailable.' );
			}
			$this->assertNotEmpty( $args['stream'] );
			copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );
			return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 200 ) );
		}, 10, 3 );
	}

	private function service( array $item ): ChurchTools_Suite_Posts_Sync_Service {
		$client = new class( $item ) {
			public function __construct( private array $item ) {}
			public function api_request( $endpoint, $method = 'GET', $data = array() ) {
				return array( 'data' => array( $this->item ) );
			}
		};
		return new ChurchTools_Suite_Posts_Sync_Service( $client );
	}

	private function item( array $overrides = array() ): array {
		return array_replace( array( 'id' => 'image-regression', 'title' => 'Post with image', 'publishedDate' => '2026-11-08T10:00:00Z', 'content' => 'Body' ), $overrides );
	}

	private function imported_post_id(): int {
		$posts = get_posts( array( 'post_status' => 'draft', 'meta_key' => '_cts_ct_post_id', 'meta_value' => 'image-regression' ) );
		$this->assertCount( 1, $posts );
		return (int) $posts[0]->ID;
	}

	public function test_api_image_is_imported_and_repeated_sync_does_not_download_again(): void {
		$service = $this->service( $this->item( array( 'images' => array( array( 'url' => '/files/first.jpg' ), '/files/second.jpg' ) ) ) );
		$result = $service->sync_posts();
		$this->assertSame( 1, $result['posts_created'] );
		$this->assertSame( 0, $result['errors'] );
		$post_id = $this->imported_post_id();
		$image_id = get_post_thumbnail_id( $post_id );
		$this->assertGreaterThan( 0, $image_id );
		$this->assertSame( 'https://example.org/files/first.jpg', get_post_meta( $image_id, '_cts_imported_from', true ) );
		$this->assertFileExists( get_attached_file( $image_id ) );
		$this->assertSame( 1, $service->sync_posts()['posts_skipped'] );
		$this->assertCount( 1, $this->downloads );
		$this->assertSame( $image_id, get_post_thumbnail_id( $post_id ) );
	}

	/** @dataProvider inline_images */
	public function test_inline_image_is_extracted_without_duplicating_content( string $content, string $expected_url ): void {
		$result = $this->service( $this->item( array( 'content' => $content ) ) )->sync_posts();
		$this->assertSame( 0, $result['errors'] );
		$post_id = $this->imported_post_id();
		$this->assertSame( $expected_url, get_post_meta( get_post_thumbnail_id( $post_id ), '_cts_imported_from', true ) );
		$this->assertSame( array( $expected_url ), json_decode( get_post_meta( $post_id, '_cts_ct_images', true ), true ) );
		$this->assertSame( 1, substr_count( get_post( $post_id )->post_content, '<img ' ) );
	}

	public static function inline_images(): array {
		return array(
			'markdown' => array( 'Text ![Photo](/files/inline.jpg "Caption")', 'https://example.org/files/inline.jpg' ),
			'html' => array( '<p>Text</p><img alt="Photo" src="/files/html.jpg?w=800&amp;h=600">', 'https://example.org/files/html.jpg?w=800&h=600' ),
			'reference' => array( "Text ![Photo][picture]\n\n[picture]: /files/reference.jpg", 'https://example.org/files/reference.jpg' ),
		);
	}

	public function test_unchanged_post_without_thumbnail_is_backfilled_using_existing_attachment(): void {
		$service = $this->service( $this->item( array( 'imageUrl' => '/files/existing.jpg' ) ) );
		$service->sync_posts();
		$post_id = $this->imported_post_id();
		$image_id = get_post_thumbnail_id( $post_id );
		delete_post_thumbnail( $post_id );
		$this->downloads = array();
		$this->assertSame( 1, $service->sync_posts()['posts_skipped'] );
		$this->assertSame( $image_id, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( array(), $this->downloads );
	}

	public function test_changed_source_preserves_a_selected_thumbnail(): void {
		$this->service( $this->item( array( 'imageUrl' => '/files/selected.jpg' ) ) )->sync_posts();
		$post_id = $this->imported_post_id();
		$image_id = get_post_thumbnail_id( $post_id );
		$this->downloads = array();
		$result = $this->service( $this->item( array( 'imageUrl' => '/files/changed.jpg' ) ) )->sync_posts();
		$this->assertSame( 1, $result['posts_updated'] );
		$this->assertSame( $image_id, get_post_thumbnail_id( $post_id ) );
		$this->assertSame( array(), $this->downloads );
	}

	public function test_failed_download_is_reported_and_retried_for_unchanged_posts(): void {
		$this->download_fails = true;
		$service = $this->service( $this->item( array( 'imageUrl' => '/files/retry.jpg' ) ) );
		$result = $service->sync_posts();
		$this->assertSame( 1, $result['posts_created'] );
		$this->assertSame( 1, $result['errors'] );
		$post_id = $this->imported_post_id();
		$this->assertFalse( has_post_thumbnail( $post_id ) );
		$this->download_fails = false;
		$result = $service->sync_posts();
		$this->assertSame( 0, $result['errors'] );
		$this->assertSame( 1, $result['posts_skipped'] );
		$this->assertTrue( has_post_thumbnail( $post_id ) );
	}

	public function test_post_without_images_does_not_download_anything(): void {
		$result = $this->service( $this->item() )->sync_posts();
		$this->assertSame( 0, $result['errors'] );
		$this->assertFalse( has_post_thumbnail( $this->imported_post_id() ) );
		$this->assertSame( array(), $this->downloads );
	}
}
