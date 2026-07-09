<?php
/**
 * ItemStoreTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItemStore;

/**
 * Class ItemStoreTest
 */
class ItemStoreTest extends TestCase {

	private SitemapStore $sitemap_store;
	private PostItemStore $store;
	private Sitemap $sitemap;

	public function set_up(): void {
		parent::set_up();

		$this->sitemap_store = new SitemapStore();
		$this->store         = new PostItemStore( 1000 );
		$this->sitemap       = $this->make_sitemap( 'post', 'post' );
	}

	private function make_sitemap( string $type, ?string $subtype ): Sitemap {
		$sitemap = new Sitemap(
			[
				'object_type'    => $type,
				'object_subtype' => $subtype,
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
		$this->sitemap_store->insert_sitemap( $sitemap );

		return $sitemap;
	}

	private function new_post_item( int $post_id, ?int $index = null, ?int $next = null ): PostItem {
		return new PostItem(
			[
				'post_id'         => $post_id,
				'sitemap_id'      => $this->sitemap->id,
				'url'             => "/post-$post_id",
				'item_index'      => $index,
				'next_item_index' => $next,
			]
		);
	}

	public function test_insert_assigns_id(): void {
		$item = $this->new_post_item( 10 );

		$result = $this->store->insert_item( $item );

		$this->assertInstanceOf( PostItem::class, $result );
		$this->assertNotNull( $item->get_id() );
		$this->assertGreaterThan( 0, $item->get_id() );
	}

	public function test_insert_and_get_round_trip(): void {
		$item = $this->new_post_item( 11, 3 );
		$this->store->insert_item( $item );

		$fetched = $this->store->get( $item->get_id() );

		$this->assertInstanceOf( PostItem::class, $fetched );
		$this->assertSame( 11, $fetched->post_id );
		$this->assertSame( $this->sitemap->id, $fetched->sitemap_id );
		$this->assertSame( '/post-11', $fetched->url );
		$this->assertSame( 3, $fetched->item_index );
		$this->assertNull( $fetched->next_item_index );
	}

	public function test_get_returns_null_for_missing_id(): void {
		$this->assertNull( $this->store->get( 999999 ) );
	}

	public function test_update_item_persists_changes(): void {
		$item = $this->new_post_item( 12, 0 );
		$this->store->insert_item( $item );

		$item->url        = '/updated';
		$item->item_index = 7;
		$updated          = $this->store->update_item( $item );

		$this->assertNotFalse( $updated );

		$fetched = $this->store->get( $item->get_id() );
		$this->assertSame( '/updated', $fetched->url );
		$this->assertSame( 7, $fetched->item_index );
	}

	public function test_update_query_updates_matching_rows(): void {
		$this->store->insert_item( $this->new_post_item( 13, 0 ) );
		$this->store->insert_item( $this->new_post_item( 14, 1 ) );

		$result = $this->store->update_query(
			[ 'next_item_index' => 'item_index' ],
			[ 'sitemap_id' => $this->sitemap->id ],
			[ 'next_item_index' => '%i' ]
		);

		$this->assertSame( 2, $result );

		$item = $this->store->get_one_by_object_id( 13 );
		$this->assertSame( 0, $item->next_item_index );
	}

	public function test_delete_removes_row(): void {
		$item = $this->new_post_item( 15, 0 );
		$this->store->insert_item( $item );
		$id = $item->get_id();

		$this->store->delete( $item );

		$this->assertNull( $this->store->get( $id ) );
	}

	public function test_delete_query_by_where(): void {
		$this->store->insert_item( $this->new_post_item( 16, 0 ) );
		$this->store->insert_item( $this->new_post_item( 17, null ) );

		$deleted = $this->store->delete_query(
			[
				'sitemap_id' => $this->sitemap->id,
				'item_index' => null,
			]
		);

		$this->assertSame( 1, $deleted );
		$this->assertCount( 0, $this->store->find_by_object_id( 17 ) );
		$this->assertCount( 1, $this->store->find_by_object_id( 16 ) );
	}

	public function test_delete_where_id_in_removes_all(): void {
		$a = $this->new_post_item( 18, 0 );
		$b = $this->new_post_item( 19, 1 );
		$this->store->insert_item( $a );
		$this->store->insert_item( $b );

		$result = $this->store->delete_where_id_in( [ $a->get_id(), $b->get_id() ] );

		$this->assertSame( 2, $result );
		$this->assertNull( $this->store->get( $a->get_id() ) );
		$this->assertNull( $this->store->get( $b->get_id() ) );
	}

	public function test_delete_where_id_in_empty_returns_false(): void {
		$this->assertFalse( $this->store->delete_where_id_in( [] ) );
	}

	public function test_find_by_object_id_returns_all_matches(): void {
		$other_sitemap = $this->make_sitemap( 'post', 'page' );

		$this->store->insert_item( $this->new_post_item( 20, 0 ) );
		$this->store->insert_item(
			new PostItem(
				[
					'post_id'    => 20,
					'sitemap_id' => $other_sitemap->id,
					'url'        => '/other',
				]
			)
		);

		$found = $this->store->find_by_object_id( 20 );

		$this->assertCount( 2, $found );
		$this->assertContainsOnlyInstancesOf( PostItem::class, $found );
	}

	public function test_find_by_object_id_returns_empty_when_none(): void {
		$this->assertSame( [], $this->store->find_by_object_id( 123456 ) );
	}

	public function test_get_one_by_object_id(): void {
		$item = $this->new_post_item( 21, 2 );
		$this->store->insert_item( $item );

		$found = $this->store->get_one_by_object_id( 21 );

		$this->assertInstanceOf( PostItem::class, $found );
		$this->assertSame( 21, $found->post_id );
	}

	public function test_get_one_by_object_id_returns_null_when_none(): void {
		$this->assertNull( $this->store->get_one_by_object_id( 123456 ) );
	}

	public function test_exists_with_scalar_arguments(): void {
		$this->store->insert_item( $this->new_post_item( 22, 0 ) );

		$this->assertTrue( $this->store->exists( $this->sitemap->id, 22 ) );
		$this->assertFalse( $this->store->exists( $this->sitemap->id, 999 ) );
	}

	public function test_exists_with_object_arguments(): void {
		$this->store->insert_item( $this->new_post_item( 23, 0 ) );

		$object = (object) [ 'ID' => 23 ];

		$this->assertTrue( $this->store->exists( $this->sitemap, $object ) );
	}

	public function test_get_last_item_returns_highest_index(): void {
		$this->store->insert_item( $this->new_post_item( 24, 0 ) );
		$this->store->insert_item( $this->new_post_item( 25, 2 ) );
		$this->store->insert_item( $this->new_post_item( 26, 1 ) );

		$last = $this->store->get_last_item( $this->sitemap );

		$this->assertSame( 25, $last->post_id );
		$this->assertSame( 2, $last->item_index );
	}

	public function test_get_by_item_index(): void {
		$this->store->insert_item( $this->new_post_item( 27, 0 ) );
		$this->store->insert_item( $this->new_post_item( 28, 1 ) );

		$item = $this->store->get_by_item_index( $this->sitemap, 1 );

		$this->assertSame( 28, $item->post_id );
		$this->assertNull( $this->store->get_by_item_index( $this->sitemap, 99 ) );
	}

	public function test_sort_by_item_index(): void {
		$items = [
			$this->new_post_item( 30, 2 ),
			$this->new_post_item( 31, 0 ),
			$this->new_post_item( 32, 1 ),
		];

		$this->store->sort_by_item_index( $items );

		$this->assertSame( [ 0, 1, 2 ], array_map( fn( $item ) => $item->item_index, $items ) );
	}

	public function test_where_item_index_compare_greater_equal(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->store->insert_item( $this->new_post_item( 40 + $i, $i ) );
		}

		$items = $this->store->where_item_index_compare( $this->sitemap->id, '>=', 2, 10, 'ASC' );

		$this->assertSame( [ 2, 3 ], array_map( fn( $item ) => $item->item_index, $items ) );
	}

	public function test_where_item_index_compare_less_equal_descending(): void {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->store->insert_item( $this->new_post_item( 50 + $i, $i ) );
		}

		$items = $this->store->where_item_index_compare( $this->sitemap->id, '<=', 2, 10, 'DESC' );

		$this->assertSame( [ 2, 1, 0 ], array_map( fn( $item ) => $item->item_index, $items ) );
	}

	public function test_where_item_index_compare_invalid_operator_returns_empty(): void {
		$this->store->insert_item( $this->new_post_item( 60, 0 ) );

		$this->assertSame( [], $this->store->where_item_index_compare( $this->sitemap->id, '!=', 0, 10, 'ASC' ) );
	}

	public function test_offset_index_shifts_item_index(): void {
		$this->store->insert_item( $this->new_post_item( 70, 0 ) );
		$this->store->insert_item( $this->new_post_item( 71, 1 ) );

		$this->store->offset_index( 10, $this->sitemap->id );

		$this->assertSame( 10, $this->store->get_one_by_object_id( 70 )->item_index );
		$this->assertSame( 11, $this->store->get_one_by_object_id( 71 )->item_index );
	}

	public function test_offset_next_index_with_where_clause(): void {
		$this->store->insert_item( $this->new_post_item( 72, 0, 0 ) );
		$this->store->insert_item( $this->new_post_item( 73, 1, 1 ) );

		$this->store->offset_next_index( 5, $this->sitemap->id, [ 'next_item_index >= %d', [ 1 ] ] );

		$this->assertSame( 0, $this->store->get_one_by_object_id( 72 )->next_item_index );
		$this->assertSame( 6, $this->store->get_one_by_object_id( 73 )->next_item_index );
	}

	public function test_clear_next_index(): void {
		$this->store->insert_item( $this->new_post_item( 74, 0, 5 ) );

		$this->assertTrue( $this->store->clear_next_index( $this->sitemap->id ) );
		$this->assertNull( $this->store->get_one_by_object_id( 74 )->next_item_index );
	}

	public function test_commit_next_index(): void {
		$this->store->insert_item( $this->new_post_item( 75, 0, 9 ) );

		$this->assertTrue( $this->store->commit_next_index( $this->sitemap->id ) );

		$item = $this->store->get_one_by_object_id( 75 );
		$this->assertSame( 9, $item->item_index );
		$this->assertNull( $item->next_item_index );
	}

	public function test_get_field_types_defaults_unknown_to_string(): void {
		$types = $this->store->get_field_types( [ 'post_id', 'unknown_field' ] );

		$this->assertSame( '%d', $types['post_id'] );
		$this->assertSame( '%s', $types['unknown_field'] );
	}

	public function test_get_table_returns_prefixed_name(): void {
		global $wpdb;

		$this->assertSame( "{$wpdb->prefix}sitemap_posts", $this->store->get_table() );
	}

	public function test_update_sitemap_stats_reflects_last_item(): void {
		$post_ids = [ static::factory()->post->create(), static::factory()->post->create() ];
		sort( $post_ids );

		$this->store->insert_item( $this->new_post_item( $post_ids[0], 0 ) );
		$this->store->insert_item( $this->new_post_item( $post_ids[1], 1 ) );

		$updated = $this->store->update_sitemap_stats( $this->sitemap );

		$this->assertSame( 2, $updated->item_count );
		$this->assertSame( 1, $updated->last_item_index );
		$this->assertSame( $post_ids[1], $updated->last_object_id );
	}

	public function test_paginate_returns_paginator_and_pages(): void {
		$store = new PostItemStore( 2 );
		for ( $i = 0; $i < 5; $i++ ) {
			$item = new PostItem(
				[
					'post_id'    => 80 + $i,
					'sitemap_id' => $this->sitemap->id,
					'url'        => "/p$i",
					'item_index' => $i,
				]
			);
			$store->insert_item( $item );
		}

		$this->sitemap->item_count      = 5;
		$this->sitemap->last_item_index = 4;

		$paginator = $store->paginate( $this->sitemap );

		$this->assertInstanceOf( Paginator::class, $paginator );
		$this->assertSame( 5, $paginator->get_total() );
		$this->assertSame( 3, $paginator->get_last_page() );
		$this->assertSame( [ 1, 2, 3 ], $paginator->get_pages() );
	}

	public function test_paginate_get_items_per_page(): void {
		$store = new PostItemStore( 2 );
		for ( $i = 0; $i < 5; $i++ ) {
			$item = new PostItem(
				[
					'post_id'    => 90 + $i,
					'sitemap_id' => $this->sitemap->id,
					'url'        => "/p$i",
					'item_index' => $i,
				]
			);
			$store->insert_item( $item );
		}

		$this->sitemap->item_count      = 5;
		$this->sitemap->last_item_index = 4;

		$paginator = $store->paginate( $this->sitemap );

		$this->assertSame( [ 0, 1 ], array_map( fn( $item ) => $item->item_index, $paginator->get_items( 1 ) ) );
		$this->assertSame( [ 2, 3 ], array_map( fn( $item ) => $item->item_index, $paginator->get_items( 2 ) ) );
		$this->assertSame( [ 4 ], array_map( fn( $item ) => $item->item_index, $paginator->get_items( 3 ) ) );
	}

	public function test_user_item_store_round_trip(): void {
		$sitemap = $this->make_sitemap( 'user', null );
		$store   = new UserItemStore( 1000 );

		$item = new UserItem(
			[
				'user_id'    => 5,
				'sitemap_id' => $sitemap->id,
				'url'        => '/author/5',
				'item_index' => 0,
			]
		);
		$store->insert_item( $item );

		$fetched = $store->get( $item->get_id() );
		$this->assertInstanceOf( UserItem::class, $fetched );
		$this->assertSame( 5, $fetched->user_id );
		$this->assertSame( '/author/5', $fetched->url );

		$this->assertSame( 5, $store->get_one_by_object_id( 5 )->user_id );
		$this->assertTrue( $store->exists( $sitemap->id, 5 ) );
	}

	public function test_term_item_store_round_trip(): void {
		$sitemap = $this->make_sitemap( 'term', 'category' );
		$store   = new TermItemStore( 1000 );

		$item = new TermItem(
			[
				'term_taxonomy_id'        => 8,
				'sitemap_id'              => $sitemap->id,
				'url'                     => '/cat/8',
				'last_modified'           => '2024-01-02 03:04:05',
				'last_modified_object_id' => 42,
				'item_index'              => 0,
			]
		);
		$store->insert_item( $item );

		$fetched = $store->get( $item->get_id() );
		$this->assertInstanceOf( TermItem::class, $fetched );
		$this->assertSame( 8, $fetched->term_taxonomy_id );
		$this->assertSame( '2024-01-02 03:04:05', $fetched->last_modified );
		$this->assertSame( 42, $fetched->last_modified_object_id );

		$this->assertSame( 8, $store->get_one_by_object_id( 8 )->term_taxonomy_id );
	}

	public function test_term_get_last_modified_returns_most_recently_modified_row(): void {
		$sitemap = $this->make_sitemap( 'term', 'post_tag' );
		$store   = new TermItemStore( 1000 );

		$store->insert_item(
			new TermItem(
				[
					'term_taxonomy_id'        => 1,
					'sitemap_id'              => $sitemap->id,
					'url'                     => '/t/1',
					'last_modified'           => '2024-01-01 00:00:00',
					'last_modified_object_id' => 1,
					'item_index'              => 0,
				]
			)
		);
		$store->insert_item(
			new TermItem(
				[
					'term_taxonomy_id'        => 2,
					'sitemap_id'              => $sitemap->id,
					'url'                     => '/t/2',
					'last_modified'           => '2024-06-01 00:00:00',
					'last_modified_object_id' => 2,
					'item_index'              => 1,
				]
			)
		);

		$last_modified = $store->get_last_modified( $sitemap );

		$this->assertInstanceOf( TermItem::class, $last_modified );
		$this->assertSame( '2024-06-01 00:00:00', $last_modified->last_modified );
	}
}
