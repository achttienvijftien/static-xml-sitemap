<?php
/**
 * WatcherTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Watcher;

/**
 * Class WatcherTest
 */
class WatcherTest extends TestCase {

	private SitemapProvider $provider;
	private Watcher $watcher;
	private UserItemStore $item_store;
	private SitemapStore $sitemap_store;

	public function set_up(): void {
		parent::set_up();

		$container = $this->create_container();

		$this->provider      = $container->get( SitemapProvider::class );
		$this->watcher       = $container->get( Watcher::class );
		$this->item_store    = $container->get( UserItemStore::class );
		$this->sitemap_store = $container->get( SitemapStore::class );

		$container->get( JobStore::class );
	}

	private function create_sitemap(): Sitemap {
		$sitemap         = Sitemap::for_object_type( 'user' );
		$sitemap->status = Sitemap::STATUS_INDEXED;

		$this->sitemap_store->insert_sitemap( $sitemap );

		return $sitemap;
	}

	private function sitemap_item_count( int $sitemap_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT item_count FROM {$wpdb->prefix}sitemaps WHERE id = %d", $sitemap_id )
		);
	}

	private function count_jobs( int $object_id, string $action ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sitemap_jobs WHERE object_id = %d AND action = %s",
				$object_id,
				$action
			)
		);
	}

	public function test_user_register_adds_author_with_published_posts(): void {
		$sitemap = $this->create_sitemap();

		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );

		$this->watcher->user_register( $author );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $author );

		$this->assertNotNull( $item );
		$this->assertSame( $author, $item->user_id );
		$this->assertSame( 0, $item->item_index );
		$this->assertSame( 1, $this->sitemap_item_count( $sitemap->id ) );
	}

	public function test_user_register_without_published_posts_is_not_added(): void {
		$sitemap = $this->create_sitemap();

		$author = self::factory()->user->create( [ 'role' => 'author' ] );

		$this->watcher->user_register( $author );
		$this->watcher->process_events();

		$this->assertNull( $this->item_store->get_one_by_object_id( $author ) );
		$this->assertSame( 0, $this->sitemap_item_count( $sitemap->id ) );
	}

	public function test_profile_update_url_change_updates_item_url(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$this->create_sitemap();

		$author = self::factory()->user->create(
			[ 'role' => 'author', 'user_nicename' => 'old-nicename' ]
		);
		self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );

		$this->watcher->user_register( $author );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $author );
		$this->assertNotNull( $item );
		$this->assertStringContainsString( 'old-nicename', $item->url );

		wp_update_user( [ 'ID' => $author, 'user_nicename' => 'new-nicename' ] );

		$this->watcher->profile_update( $author );
		$this->watcher->process_events();

		$updated = $this->item_store->get_one_by_object_id( $author );
		$this->assertStringContainsString( 'new-nicename', $updated->url );
		$this->assertStringNotContainsString( 'old-nicename', $updated->url );
	}

	public function test_profile_update_without_url_change_does_not_invalidate(): void {
		$this->create_sitemap();

		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );

		$this->watcher->user_register( $author );
		$this->watcher->process_events();

		$item         = $this->item_store->get_one_by_object_id( $author );
		$original_url = $item->url;

		wp_update_user( [ 'ID' => $author, 'display_name' => 'Changed Name' ] );

		$this->watcher->profile_update( $author );
		$this->watcher->process_events();

		$updated = $this->item_store->get_one_by_object_id( $author );

		$this->assertSame( $original_url, $updated->url );
		$this->assertSame( 0, $this->count_jobs( $author, 'remove_item' ) );
		$this->assertSame( 0, $this->count_jobs( $author, 'reindex_item' ) );
	}

	public function test_delete_user_queues_removal(): void {
		$this->create_sitemap();

		$author = self::factory()->user->create( [ 'role' => 'author' ] );
		self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );

		$this->watcher->user_register( $author );
		$this->watcher->process_events();

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $author ) );

		$this->watcher->delete_user( $author );
		$this->watcher->process_events();

		$this->assertSame( 1, $this->count_jobs( $author, 'remove_item' ) );
	}

	public function test_post_author_change_invalidates_old_author_and_adds_new(): void {
		$this->create_sitemap();

		$old_author = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'oldauthor' ] );
		$new_author = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'newauthor' ] );

		$post = self::factory()->post->create(
			[ 'post_author' => $old_author, 'post_status' => 'publish' ]
		);

		$this->watcher->user_register( $old_author );
		$this->watcher->process_events();

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $old_author ) );

		$before = clone get_post( $post );
		wp_update_post( [ 'ID' => $post, 'post_author' => $new_author ] );
		$after = get_post( $post );

		$this->watcher->post_updated( $post, $after, $before );
		$this->watcher->process_events();

		$this->assertSame( 1, $this->count_jobs( $old_author, 'remove_item' ) );
		$this->assertNotNull( $this->item_store->get_one_by_object_id( $new_author ) );
	}

	public function test_save_post_of_new_post_adds_author(): void {
		$this->create_sitemap();

		$author = self::factory()->user->create( [ 'role' => 'author' ] );

		$post_id = self::factory()->post->create(
			[ 'post_author' => $author, 'post_status' => 'publish' ]
		);

		$this->watcher->save_post( $post_id, get_post( $post_id ), false );
		$this->watcher->process_events();

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $author ) );
	}

	public function test_save_post_of_updated_post_is_ignored(): void {
		$this->create_sitemap();

		$author  = self::factory()->user->create( [ 'role' => 'author' ] );
		$post_id = self::factory()->post->create(
			[ 'post_author' => $author, 'post_status' => 'publish' ]
		);

		$this->watcher->save_post( $post_id, get_post( $post_id ), true );
		$this->watcher->process_events();

		$this->assertNull( $this->item_store->get_one_by_object_id( $author ) );
	}

	public function test_save_post_with_zero_author_is_ignored(): void {
		$this->create_sitemap();

		$post_id = self::factory()->post->create(
			[ 'post_author' => 0, 'post_status' => 'publish' ]
		);
		$post    = get_post( $post_id );

		$this->assertSame( '0', (string) $post->post_author );

		$this->watcher->save_post( $post_id, $post, false );
		$this->watcher->post_updated( $post_id, $post, $post );
		$this->watcher->process_events();

		$this->assertNull( $this->item_store->get_one_by_object_id( 0 ) );
		$this->assertSame( 0, $this->count_jobs( 0, 'remove_item' ) );
	}
}
