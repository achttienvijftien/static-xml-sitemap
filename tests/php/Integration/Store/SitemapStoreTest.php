<?php
/**
 * SitemapStoreTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class SitemapStoreTest
 */
class SitemapStoreTest extends TestCase {

	private SitemapStore $store;

	public function set_up(): void {
		parent::set_up();

		$this->store = new SitemapStore();
	}

	private function new_sitemap(
		string $type,
		?string $subtype,
		string $status = Sitemap::STATUS_INDEXED,
		int $item_count = 0
	): Sitemap {
		return new Sitemap(
			[
				'object_type'    => $type,
				'object_subtype' => $subtype,
				'status'         => $status,
				'item_count'     => $item_count,
			]
		);
	}

	public function test_insert_assigns_id(): void {
		$sitemap = $this->new_sitemap( 'post', 'post' );

		$result = $this->store->insert_sitemap( $sitemap );

		$this->assertInstanceOf( Sitemap::class, $result );
		$this->assertGreaterThan( 0, $sitemap->get_id() );
	}

	public function test_get_returns_null_for_missing(): void {
		$this->assertNull( $this->store->get( 987654 ) );
	}

	public function test_get_by_object_type_with_subtype(): void {
		$sitemap = $this->new_sitemap( 'post', 'page' );
		$this->store->insert_sitemap( $sitemap );

		$found = $this->store->get_by_object_type( 'post', 'page' );

		$this->assertInstanceOf( Sitemap::class, $found );
		$this->assertSame( 'page', $found->object_subtype );
	}

	public function test_get_by_object_type_without_subtype(): void {
		$sitemap = $this->new_sitemap( 'user', null );
		$this->store->insert_sitemap( $sitemap );

		$found = $this->store->get_by_object_type( 'user' );

		$this->assertInstanceOf( Sitemap::class, $found );
		$this->assertSame( 'user', $found->object_type );
		$this->assertNull( $found->object_subtype );
	}

	public function test_get_by_object_type_returns_null_when_missing(): void {
		$this->assertNull( $this->store->get_by_object_type( 'post', 'nonexistent' ) );
	}

	public function test_unique_object_type_subtype_constraint(): void {
		global $wpdb;

		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'post' ) );

		$suppress = $wpdb->suppress_errors();
		$result   = ( new SitemapStore() )->insert_sitemap( $this->new_sitemap( 'post', 'post' ) );
		$wpdb->suppress_errors( $suppress );

		$this->assertFalse( $result );
	}

	public function test_find_by_object_type_returns_all_subtypes(): void {
		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'post' ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'page' ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'term', 'category' ) );

		$found = $this->store->find_by_object_type( 'post' );

		$this->assertCount( 2, $found );
		$this->assertContainsOnlyInstancesOf( Sitemap::class, $found );
	}

	public function test_get_all_returns_every_sitemap(): void {
		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'post' ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'user', null ) );

		$this->assertCount( 2, $this->store->get_all() );
	}

	public function test_get_viewable_sitemaps_filters_by_status_and_count(): void {
		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'post', Sitemap::STATUS_INDEXED, 5 ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'post', 'page', Sitemap::STATUS_UPDATING, 3 ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'term', 'category', Sitemap::STATUS_INDEXED, 0 ) );
		$this->store->insert_sitemap( $this->new_sitemap( 'user', null, Sitemap::STATUS_INDEXING, 10 ) );

		$viewable = $this->store->get_viewable_sitemaps();

		$this->assertCount( 2, $viewable );

		$subtypes = array_map( fn( $sitemap ) => $sitemap->object_subtype, $viewable );
		sort( $subtypes );
		$this->assertSame( [ 'page', 'post' ], $subtypes );
	}

	public function test_update_persists_status_transitions(): void {
		$sitemap = $this->new_sitemap( 'post', 'post', Sitemap::STATUS_UNINDEXED );
		$this->store->insert_sitemap( $sitemap );

		$transitions = [
			Sitemap::STATUS_INDEXING,
			Sitemap::STATUS_INDEXED,
			Sitemap::STATUS_UPDATING,
		];

		foreach ( $transitions as $status ) {
			$sitemap->status = $status;
			$this->store->update_sitemap( $sitemap );
			$this->store->invalidate_cache( $sitemap->id );

			$this->assertSame( $status, $this->store->get( $sitemap->id )->status );
		}
	}

	public function test_update_persists_stat_fields(): void {
		$sitemap = $this->new_sitemap( 'post', 'post' );
		$this->store->insert_sitemap( $sitemap );

		$sitemap->last_modified   = '2024-05-06 07:08:09';
		$sitemap->last_object_id  = 321;
		$sitemap->last_item_index = 12;
		$sitemap->item_count      = 13;
		$this->store->update_sitemap( $sitemap );
		$this->store->invalidate_cache( $sitemap->id );

		$fetched = $this->store->get( $sitemap->id );
		$this->assertSame( '2024-05-06 07:08:09', $fetched->last_modified );
		$this->assertSame( 321, $fetched->last_object_id );
		$this->assertSame( 12, $fetched->last_item_index );
		$this->assertSame( 13, $fetched->item_count );
	}

	public function test_append_updates_count_and_last_fields(): void {
		$sitemap = $this->new_sitemap( 'post', 'post' );

		$item = new PostItem(
			[
				'post_id'    => 55,
				'sitemap_id' => 1,
				'url'        => '/x',
			]
		);

		$sitemap->append( $item );

		$this->assertSame( 0, $item->get_item_index() );
		$this->assertSame( 55, $sitemap->last_object_id );
		$this->assertSame( 0, $sitemap->last_item_index );
		$this->assertSame( 1, $sitemap->item_count );
	}

	public function test_append_increments_indexes(): void {
		$sitemap = $this->new_sitemap( 'post', 'post' );

		$first  = new PostItem( [ 'post_id' => 1, 'sitemap_id' => 1, 'url' => '/1' ] );
		$second = new PostItem( [ 'post_id' => 2, 'sitemap_id' => 1, 'url' => '/2' ] );

		$sitemap->append( $first );
		$sitemap->append( $second );

		$this->assertSame( 0, $first->get_item_index() );
		$this->assertSame( 1, $second->get_item_index() );
		$this->assertSame( 2, $sitemap->item_count );
		$this->assertSame( 1, $sitemap->last_item_index );
	}

	public function test_for_object_type_initializes_unindexed(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );

		$this->assertInstanceOf( Sitemap::class, $sitemap );
		$this->assertSame( Sitemap::STATUS_UNINDEXED, $sitemap->status );
		$this->assertSame( 0, $sitemap->item_count );
		$this->assertNull( $sitemap->last_item_index );
	}

	public function test_reset_reinitializes_state(): void {
		$sitemap                  = $this->new_sitemap( 'post', 'post', Sitemap::STATUS_INDEXED, 9 );
		$sitemap->last_item_index = 8;
		$sitemap->last_object_id  = 7;

		$sitemap->reset();

		$this->assertSame( Sitemap::STATUS_UNINDEXED, $sitemap->status );
		$this->assertSame( 0, $sitemap->item_count );
		$this->assertNull( $sitemap->last_item_index );
		$this->assertNull( $sitemap->last_object_id );
	}

	public function test_status_predicates(): void {
		$indexing = $this->new_sitemap( 'post', 'post', Sitemap::STATUS_INDEXING );
		$updating = $this->new_sitemap( 'post', 'post', Sitemap::STATUS_UPDATING );

		$this->assertTrue( $indexing->is_indexing() );
		$this->assertFalse( $indexing->is_updating() );
		$this->assertTrue( $updating->is_updating() );
		$this->assertFalse( $updating->is_indexing() );
	}

	public function test_get_lock_returns_named_lock(): void {
		$sitemap     = $this->new_sitemap( 'post', 'post' );
		$sitemap->id = 44;

		$this->assertInstanceOf( Lock::class, Sitemap::get_lock( $sitemap ) );
		$this->assertInstanceOf( Lock::class, Sitemap::get_lock( 7 ) );
	}

	public function test_get_description(): void {
		$this->assertSame(
			'post type post',
			$this->new_sitemap( 'post', 'post' )->get_description()
		);
		$this->assertSame(
			'authors',
			$this->new_sitemap( 'user', null )->get_description()
		);
		$this->assertSame(
			'taxonomy category',
			$this->new_sitemap( 'term', 'category' )->get_description()
		);
	}
}
