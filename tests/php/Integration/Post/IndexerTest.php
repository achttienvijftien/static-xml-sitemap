<?php
/**
 * IndexerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;

/**
 * Class IndexerTest
 */
class IndexerTest extends PostTestCase {

	public function test_index_objects_creates_items_in_order_with_correct_index(): void {
		$this->boot();

		$post_1 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_2 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_3 = static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$results = $this->provider->index_objects( [ 'post' ] );

		$this->assertSame( 3, $results[0]['objects_indexed'] );
		$this->assertNull( $results[0]['error'] );

		$this->assertSame(
			[
				$post_1 => 0,
				$post_2 => 1,
				$post_3 => 2,
			],
			$this->get_item_index_map()
		);

		$rows = $this->get_item_rows();
		$this->assertSame( '/?p=' . $post_1, $rows[0]->url );
		$this->assertSame( '/?p=' . $post_3, $rows[2]->url );
	}

	public function test_index_objects_sets_sitemap_status_and_stats(): void {
		$this->boot();

		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$last = static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->provider->index_objects( [ 'post' ] );

		$sitemap = $this->get_post_sitemap();

		$this->assertNotNull( $sitemap );
		$this->assertSame( Sitemap::STATUS_INDEXED, $sitemap->status );
		$this->assertSame( 3, (int) $sitemap->item_count );
		$this->assertSame( 2, (int) $sitemap->last_item_index );
		$this->assertSame( $last, (int) $sitemap->last_object_id );
		$this->assertNull( $sitemap->last_modified );
	}

	public function test_index_objects_skips_non_published_posts(): void {
		$this->boot();

		$published = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		static::factory()->post->create( [ 'post_status' => 'draft' ] );
		static::factory()->post->create( [ 'post_status' => 'private' ] );

		$results = $this->provider->index_objects( [ 'post' ] );

		$this->assertSame( 1, $results[0]['objects_indexed'] );
		$this->assertSame( [ $published => 0 ], $this->get_item_index_map() );
	}

	public function test_index_objects_reports_error_when_already_indexed(): void {
		$this->boot();

		static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->provider->index_objects( [ 'post' ] );
		$results = $this->provider->index_objects( [ 'post' ] );

		$this->assertSame( 0, $results[0]['objects_indexed'] );
		$this->assertInstanceOf( \WP_Error::class, $results[0]['error'] );
		$this->assertSame( 'already_indexed', $results[0]['error']->get_error_code() );
	}

	public function test_force_recreate_rebuilds_the_sitemap(): void {
		$this->boot();

		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->provider->index_objects( [ 'post' ] );
		$this->assertCount( 2, $this->get_item_rows() );

		static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$results = $this->provider->index_objects( [ 'post' ], true );

		$this->assertSame( 3, $results[0]['objects_indexed'] );

		$rows = $this->get_item_rows();
		$this->assertCount( 3, $rows );
		$this->assertSame( [ 0, 1, 2 ], array_map( fn( $row ) => (int) $row->item_index, $rows ) );
		$this->assertSame( 3, (int) $this->get_post_sitemap()->item_count );
	}

	public function test_empty_post_status_list_is_a_no_op(): void {
		$this->boot();

		add_filter( 'static_sitemap_post_statuses', fn() => [] );

		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$results = $this->provider->index_objects( [ 'post' ] );

		$this->assertSame( 0, $results[0]['objects_indexed'] );
		$this->assertNull( $results[0]['error'] );

		$sitemap = $this->get_post_sitemap();
		$this->assertNotNull( $sitemap );
		$this->assertSame( Sitemap::STATUS_INDEXED, $sitemap->status );
		$this->assertSame( 0, (int) $sitemap->item_count );
		$this->assertCount( 0, $this->get_item_rows() );
	}
}
