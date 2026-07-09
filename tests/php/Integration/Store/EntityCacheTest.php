<?php
/**
 * EntityCacheTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\EntityCache;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class EntityCacheTest
 */
class EntityCacheTest extends TestCase {

	private function make_sitemap( int $id, string $type, ?string $subtype ): Sitemap {
		return new Sitemap(
			[
				'id'             => $id,
				'object_type'    => $type,
				'object_subtype' => $subtype,
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
	}

	public function test_add_and_get_by_id(): void {
		$cache   = new EntityCache();
		$sitemap = $this->make_sitemap( 1, 'post', 'post' );

		$cache->add( $sitemap );

		$this->assertSame( $sitemap, $cache->get( 1 ) );
	}

	public function test_get_returns_null_on_miss(): void {
		$cache = new EntityCache();

		$this->assertNull( $cache->get( 42 ) );
	}

	public function test_delete_removes_entity(): void {
		$cache = new EntityCache();
		$cache->add( $this->make_sitemap( 2, 'post', 'post' ) );

		$cache->delete( 2 );

		$this->assertNull( $cache->get( 2 ) );
	}

	public function test_clear_empties_cache(): void {
		$cache = new EntityCache();
		$cache->add( $this->make_sitemap( 3, 'post', 'post' ) );
		$cache->add( $this->make_sitemap( 4, 'post', 'page' ) );

		$cache->clear();

		$this->assertNull( $cache->get( 3 ) );
		$this->assertNull( $cache->get( 4 ) );
	}

	public function test_get_by_tag_returns_matching_entity(): void {
		$cache = ( new EntityCache() )->set_tagger(
			fn( Sitemap $sitemap ) => [ 'type' => $sitemap->object_type ]
		);

		$sitemap = $this->make_sitemap( 5, 'user', null );
		$cache->add( $sitemap );

		$this->assertSame( $sitemap, $cache->get_by_tag( [ 'type' => 'user' ] ) );
		$this->assertNull( $cache->get_by_tag( [ 'type' => 'term' ] ) );
	}

	public function test_tag_is_reassigned_when_value_changes(): void {
		$cache = ( new EntityCache() )->set_tagger(
			fn( Sitemap $sitemap ) => [ 'status' => $sitemap->status ]
		);

		$sitemap = $this->make_sitemap( 6, 'post', 'post' );
		$cache->add( $sitemap );

		$sitemap->status = Sitemap::STATUS_UPDATING;
		$cache->add( $sitemap );

		$this->assertNull( $cache->get_by_tag( [ 'status' => Sitemap::STATUS_INDEXED ] ) );
		$this->assertSame( $sitemap, $cache->get_by_tag( [ 'status' => Sitemap::STATUS_UPDATING ] ) );
	}

	public function test_delete_removes_tag_reference(): void {
		$cache = ( new EntityCache() )->set_tagger(
			fn( Sitemap $sitemap ) => [ 'type' => $sitemap->object_type ]
		);

		$sitemap = $this->make_sitemap( 7, 'term', 'category' );
		$cache->add( $sitemap );
		$cache->delete( 7 );

		$this->assertNull( $cache->get_by_tag( [ 'type' => 'term' ] ) );
	}

	public function test_store_get_reads_through_cache(): void {
		$store   = new SitemapStore();
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
		$store->insert_sitemap( $sitemap );

		$this->assertSame( $sitemap, $store->get( $sitemap->id ) );
	}

	public function test_store_get_serves_stale_value_until_invalidated(): void {
		global $wpdb;

		$store   = new SitemapStore();
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'status'         => Sitemap::STATUS_INDEXED,
				'item_count'     => 1,
			]
		);
		$store->insert_sitemap( $sitemap );

		$wpdb->update(
			"{$wpdb->prefix}sitemaps",
			[ 'item_count' => 99 ],
			[ 'id' => $sitemap->id ]
		);

		$this->assertSame( 1, $store->get( $sitemap->id )->item_count );

		$store->invalidate_cache( $sitemap->id );

		$this->assertSame( 99, $store->get( $sitemap->id )->item_count );
	}

	public function test_store_update_query_clears_cache(): void {
		global $wpdb;

		$store   = new SitemapStore();
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'status'         => Sitemap::STATUS_INDEXED,
				'item_count'     => 1,
			]
		);
		$store->insert_sitemap( $sitemap );
		$store->get( $sitemap->id );

		$store->update_query( [ 'item_count' => 5 ], [ 'id' => $sitemap->id ] );

		$this->assertSame( 5, $store->get( $sitemap->id )->item_count );
	}

	public function test_store_get_by_object_type_caches_result(): void {
		$store   = new SitemapStore();
		$sitemap = new Sitemap(
			[
				'object_type'    => 'user',
				'object_subtype' => null,
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
		$store->insert_sitemap( $sitemap );

		$first  = $store->get_by_object_type( 'user' );
		$second = $store->get_by_object_type( 'user' );

		$this->assertInstanceOf( Sitemap::class, $first );
		$this->assertSame( $first, $second );
	}
}
