<?php
/**
 * SitemapTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Sitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Sitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class SitemapTest
 */
class SitemapTest extends TestCase {

	public function test_for_object_type_initializes_defaults(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );

		$this->assertSame( 'post', $sitemap->object_type );
		$this->assertSame( 'post', $sitemap->object_subtype );
		$this->assertSame( 0, $sitemap->item_count );
		$this->assertSame( Sitemap::STATUS_UNINDEXED, $sitemap->status );
		$this->assertNull( $sitemap->last_modified );
		$this->assertNull( $sitemap->last_object_id );
		$this->assertNull( $sitemap->last_item_index );
	}

	public function test_append_updates_sitemap_and_item_state(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );

		$first_id  = self::factory()->post->create();
		$second_id = self::factory()->post->create();

		$first_item = new PostItem(
			[
				'post_id'    => $first_id,
				'url'        => '/first',
				'sitemap_id' => 1,
			]
		);
		$second_item = new PostItem(
			[
				'post_id'    => $second_id,
				'url'        => '/second',
				'sitemap_id' => 1,
			]
		);

		$sitemap->append( $first_item );

		$this->assertSame( 0, $first_item->get_item_index() );
		$this->assertSame( 1, $sitemap->item_count );
		$this->assertSame( $first_id, $sitemap->last_object_id );
		$this->assertSame( 0, $sitemap->last_item_index );
		$this->assertSame( get_post( $first_id )->post_modified_gmt, $sitemap->last_modified );

		$sitemap->append( $second_item );

		$this->assertSame( 1, $second_item->get_item_index() );
		$this->assertSame( 2, $sitemap->item_count );
		$this->assertSame( $second_id, $sitemap->last_object_id );
		$this->assertSame( 1, $sitemap->last_item_index );
	}

	public function test_status_helpers(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );

		$this->assertFalse( $sitemap->is_indexing() );
		$this->assertFalse( $sitemap->is_updating() );

		$sitemap->status = Sitemap::STATUS_INDEXING;
		$this->assertTrue( $sitemap->is_indexing() );

		$sitemap->status = Sitemap::STATUS_UPDATING;
		$this->assertTrue( $sitemap->is_updating() );
	}

	public function test_get_description_for_each_object_type(): void {
		$this->assertSame(
			'post type page',
			Sitemap::for_object_type( 'post', 'page' )->get_description()
		);
		$this->assertSame( 'authors', Sitemap::for_object_type( 'user' )->get_description() );
		$this->assertSame(
			'taxonomy category',
			Sitemap::for_object_type( 'term', 'category' )->get_description()
		);
		$this->assertSame( '', ( new Sitemap( [] ) )->get_description() );
	}

	public function test_reset_reinitializes_state(): void {
		$sitemap                  = Sitemap::for_object_type( 'post', 'post' );
		$sitemap->item_count      = 42;
		$sitemap->status          = Sitemap::STATUS_INDEXED;
		$sitemap->last_object_id  = 99;
		$sitemap->last_item_index = 41;

		$sitemap->reset();

		$this->assertSame( 0, $sitemap->item_count );
		$this->assertSame( Sitemap::STATUS_UNINDEXED, $sitemap->status );
		$this->assertNull( $sitemap->last_object_id );
		$this->assertNull( $sitemap->last_item_index );
	}

	public function test_get_lock_returns_lock_instance(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );
		$sitemap->id = 7;

		$this->assertInstanceOf( Lock::class, Sitemap::get_lock( $sitemap ) );
		$this->assertInstanceOf( Lock::class, Sitemap::get_lock( 7 ) );
	}

	public function test_to_string_contains_description(): void {
		$sitemap = Sitemap::for_object_type( 'post', 'post' );

		$this->assertStringContainsString( 'Sitemap {', (string) $sitemap );
		$this->assertStringContainsString( 'post type post', (string) $sitemap );
	}

	public function test_item_trait_exposes_url_and_indices(): void {
		$item = new PostItem(
			[
				'post_id'    => 5,
				'url'        => '/foo/bar',
				'sitemap_id' => 3,
			]
		);

		$this->assertSame( home_url( '/foo/bar' ), $item->get_url() );
		$this->assertSame( 3, $item->get_sitemap_id() );

		$item->set_item_index( 9 );
		$this->assertSame( 9, $item->get_item_index() );

		$item->set_next_item_index( 11 );
		$this->assertSame( 11, $item->get_next_item_index() );
	}
}
