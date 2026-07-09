<?php
/**
 * WatcherTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Watcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class WatcherTest
 */
class WatcherTest extends TestCase {

	private SitemapProvider $provider;
	private Watcher $watcher;
	private TermItemStore $item_store;
	private SitemapStore $sitemap_store;

	public function set_up(): void {
		parent::set_up();

		$container = $this->create_container();

		$this->provider      = $container->get( SitemapProvider::class );
		$this->watcher       = $container->get( Watcher::class );
		$this->item_store    = $container->get( TermItemStore::class );
		$this->sitemap_store = $container->get( SitemapStore::class );

		$container->get( JobStore::class );
	}

	private function create_sitemap( string $taxonomy ): Sitemap {
		$sitemap         = Sitemap::for_object_type( 'term', $taxonomy );
		$sitemap->status = Sitemap::STATUS_INDEXED;

		$this->sitemap_store->insert_sitemap( $sitemap );

		return $sitemap;
	}

	private function term_taxonomy_id( int $term_id, string $taxonomy ): int {
		return (int) get_term( $term_id, $taxonomy )->term_taxonomy_id;
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

	public function test_saved_term_adds_item(): void {
		$sitemap = $this->create_sitemap( 'category' );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'News' ] );
		$tt_id   = $this->term_taxonomy_id( $term_id, 'category' );

		$this->watcher->saved_term( $term_id, $tt_id, 'category' );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $tt_id );

		$this->assertNotNull( $item );
		$this->assertSame( $tt_id, $item->term_taxonomy_id );
		$this->assertSame( 0, $item->item_index );
		$this->assertSame( 1, $this->sitemap_item_count( $sitemap->id ) );
	}

	public function test_saved_term_adds_item_with_null_last_modified(): void {
		$this->create_sitemap( 'category' );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Fresh' ] );
		$tt_id   = $this->term_taxonomy_id( $term_id, 'category' );

		$this->watcher->saved_term( $term_id, $tt_id, 'category' );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $tt_id );

		$this->assertNotNull( $item );
		$this->assertNull( $item->last_modified );
		$this->assertNull( $item->last_modified_object_id );
	}

	public function test_saved_term_slug_change_updates_item_url(): void {
		$sitemap = $this->create_sitemap( 'post_tag' );

		$term_id = self::factory()->term->create(
			[ 'taxonomy' => 'post_tag', 'name' => 'Old', 'slug' => 'old-slug' ]
		);
		$tt_id   = $this->term_taxonomy_id( $term_id, 'post_tag' );

		$this->watcher->saved_term( $term_id, $tt_id, 'post_tag' );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $tt_id );
		$this->assertNotNull( $item );
		$this->assertStringContainsString( 'old-slug', $item->url );

		wp_update_term( $term_id, 'post_tag', [ 'slug' => 'new-slug' ] );

		$this->watcher->saved_term( $term_id, $tt_id, 'post_tag' );
		$this->watcher->process_events();

		$updated = $this->item_store->get_one_by_object_id( $tt_id );
		$this->assertStringContainsString( 'new-slug', $updated->url );
		$this->assertStringNotContainsString( 'old-slug', $updated->url );
		$this->assertSame( 1, $this->sitemap_item_count( $sitemap->id ) );
	}

	public function test_delete_term_queues_removal(): void {
		$this->create_sitemap( 'category' );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Temp' ] );
		$tt_id   = $this->term_taxonomy_id( $term_id, 'category' );

		$this->watcher->saved_term( $term_id, $tt_id, 'category' );
		$this->watcher->process_events();

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $tt_id ) );

		wp_delete_term( $term_id, 'category' );

		$this->watcher->delete_term( $term_id, $tt_id );
		$this->watcher->process_events();

		$this->assertSame( 1, $this->count_jobs( $tt_id, 'remove_item' ) );
	}

	public function test_term_meta_update_is_not_tracked_by_base_watcher(): void {
		$this->create_sitemap( 'category' );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Meta' ] );
		$tt_id   = $this->term_taxonomy_id( $term_id, 'category' );

		$this->watcher->saved_term( $term_id, $tt_id, 'category' );
		$this->watcher->process_events();

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $tt_id ) );

		update_term_meta( $term_id, 'custom_meta_key', 'value' );

		$this->watcher->process_events();

		$this->assertSame( 0, $this->count_jobs( $tt_id, 'remove_item' ) );
		$this->assertSame( 0, $this->count_jobs( $tt_id, 'reindex_item' ) );
		$this->assertSame( 0, $this->count_jobs( $tt_id, 'update_last_modified' ) );
	}

	public function test_count_update_does_not_set_last_modified(): void {
		$this->create_sitemap( 'category' );

		$term_id = self::factory()->term->create( [ 'taxonomy' => 'category', 'name' => 'Modified' ] );
		$tt_id   = $this->term_taxonomy_id( $term_id, 'category' );

		$this->watcher->saved_term( $term_id, $tt_id, 'category' );
		$this->watcher->process_events();

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'category' );

		$this->watcher->edited_term_taxonomy( $tt_id, 'category', null );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $tt_id );

		$this->assertNotNull( $item );
		$this->assertNull( $item->last_modified );
		$this->assertNull( $item->last_modified_object_id );
		$this->assertSame( 0, $this->count_jobs( $tt_id, 'update_last_modified' ) );
	}
}
