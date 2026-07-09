<?php
/**
 * BatchReindexTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\BatchReindex;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class BatchReindexTest
 */
class BatchReindexTest extends TestCase {

	private SitemapStore $sitemap_store;
	private PostItemStore $item_store;
	private Logger $logger;

	public function set_up(): void {
		parent::set_up();

		$this->sitemap_store = new SitemapStore();
		$this->item_store    = new PostItemStore( 1000 );
		$this->logger        = new Logger();
	}

	/**
	 * @return int[]
	 */
	private function create_posts( int $count ): array {
		$posts = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$posts[] = static::factory()->post->create();
		}
		sort( $posts );

		return $posts;
	}

	private function make_sitemap( int $item_count, ?int $last_item_index, ?int $last_object_id ): Sitemap {
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
		$this->sitemap_store->insert_sitemap( $sitemap );

		$sitemap->item_count      = $item_count;
		$sitemap->last_item_index = $last_item_index;
		$sitemap->last_object_id  = $last_object_id;
		$this->sitemap_store->update_sitemap( $sitemap );

		return $sitemap;
	}

	private function insert_item( Sitemap $sitemap, int $post_id, ?int $index ): PostItem {
		$item = new PostItem(
			[
				'post_id'    => $post_id,
				'sitemap_id' => $sitemap->id,
				'url'        => "/post-$post_id",
				'item_index' => $index,
			]
		);
		$this->item_store->insert_item( $item );

		return $item;
	}

	private function new_item( Sitemap $sitemap, int $post_id ): PostItem {
		return new PostItem(
			[
				'post_id'    => $post_id,
				'sitemap_id' => $sitemap->id,
				'url'        => "/post-$post_id",
			]
		);
	}

	private function reindex( Sitemap $sitemap ): BatchReindex {
		return new BatchReindex( $sitemap, $this->sitemap_store, $this->item_store, $this->logger );
	}

	/**
	 * @return array<int, int|null>
	 */
	private function index_by_post( Sitemap $sitemap ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, item_index FROM {$wpdb->prefix}sitemap_posts WHERE sitemap_id = %d ORDER BY item_index",
				$sitemap->id
			)
		);

		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->post_id ] = null === $row->item_index ? null : (int) $row->item_index;
		}

		return $map;
	}

	public function test_insert_in_middle(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 2, 1, $posts[2] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[2], 1 );

		$ok = $this->reindex( $sitemap )->insert( $this->new_item( $sitemap, $posts[1] ) )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 3, $sitemap->item_count );
		$this->assertSame( 2, $sitemap->last_item_index );
	}

	public function test_insert_at_end(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 2, 1, $posts[1] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[1], 1 );

		$ok = $this->reindex( $sitemap )->insert( $this->new_item( $sitemap, $posts[2] ) )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 3, $sitemap->item_count );
	}

	public function test_insert_multiple_in_middle(): void {
		$posts   = $this->create_posts( 4 );
		$sitemap = $this->make_sitemap( 2, 1, $posts[3] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[3], 1 );

		$ok = $this->reindex( $sitemap )
			->insert( $this->new_item( $sitemap, $posts[1] ), $this->new_item( $sitemap, $posts[2] ) )
			->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2, $posts[3] => 3 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 4, $sitemap->item_count );
		$this->assertSame( 3, $sitemap->last_item_index );
	}

	public function test_remove_from_middle(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 3, 2, $posts[2] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$middle = $this->insert_item( $sitemap, $posts[1], 1 );
		$this->insert_item( $sitemap, $posts[2], 2 );

		$ok = $this->reindex( $sitemap )->remove( $middle )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[2] => 1 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 2, $sitemap->item_count );
		$this->assertSame( 1, $sitemap->last_item_index );
	}

	public function test_remove_last(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 3, 2, $posts[2] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[1], 1 );
		$last = $this->insert_item( $sitemap, $posts[2], 2 );

		$ok = $this->reindex( $sitemap )->remove( $last )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 2, $sitemap->item_count );
		$this->assertSame( 1, $sitemap->last_item_index );
	}

	public function test_insert_middle_and_remove_last_combined(): void {
		$posts   = $this->create_posts( 5 );
		$sitemap = $this->make_sitemap( 4, 3, $posts[4] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[1], 1 );
		$this->insert_item( $sitemap, $posts[3], 2 );
		$last = $this->insert_item( $sitemap, $posts[4], 3 );

		$ok = $this->reindex( $sitemap )
			->insert( $this->new_item( $sitemap, $posts[2] ) )
			->remove( $last )
			->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2, $posts[3] => 3 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 4, $sitemap->item_count );
		$this->assertSame( 3, $sitemap->last_item_index );
	}

	public function test_commit_without_changes_returns_true(): void {
		$posts   = $this->create_posts( 2 );
		$sitemap = $this->make_sitemap( 2, 1, $posts[1] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[1], 1 );

		$ok = $this->reindex( $sitemap )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1 ],
			$this->index_by_post( $sitemap )
		);
	}

	public function test_reindex_item_already_in_position_is_noop(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 3, 2, $posts[2] );
		$this->insert_item( $sitemap, $posts[0], 0 );
		$middle = $this->insert_item( $sitemap, $posts[1], 1 );
		$this->insert_item( $sitemap, $posts[2], 2 );

		$ok = $this->reindex( $sitemap )->reindex( $middle )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2 ],
			$this->index_by_post( $sitemap )
		);
	}

	public function test_insert_into_empty_sitemap_starts_at_index_zero(): void {
		$posts   = $this->create_posts( 1 );
		$sitemap = $this->make_sitemap( 0, null, null );

		$ok = $this->reindex( $sitemap )->insert( $this->new_item( $sitemap, $posts[0] ) )->commit();

		$this->assertTrue( $ok );
		$this->assertSame( [ $posts[0] => 0 ], $this->index_by_post( $sitemap ) );
		$this->assertSame( 1, $sitemap->item_count );
	}

	public function test_remove_first_item_shifts_remaining_items_down(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 3, 2, $posts[2] );
		$first   = $this->insert_item( $sitemap, $posts[0], 0 );
		$this->insert_item( $sitemap, $posts[1], 1 );
		$this->insert_item( $sitemap, $posts[2], 2 );

		$ok = $this->reindex( $sitemap )->remove( $first )->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[1] => 0, $posts[2] => 1 ],
			$this->index_by_post( $sitemap )
		);
		$this->assertSame( 2, $sitemap->item_count );
	}

	public function test_reindex_sitemap_recalculates_all_indexes(): void {
		$posts   = $this->create_posts( 3 );
		$sitemap = $this->make_sitemap( 3, 2, $posts[2] );
		$this->insert_item( $sitemap, $posts[0], 2 );
		$this->insert_item( $sitemap, $posts[1], 0 );
		$this->insert_item( $sitemap, $posts[2], 1 );

		$ok = $this->reindex( $sitemap )->reindex_sitemap()->commit();

		$this->assertTrue( $ok );
		$this->assertSame(
			[ $posts[0] => 0, $posts[1] => 1, $posts[2] => 2 ],
			$this->index_by_post( $sitemap )
		);
	}
}
