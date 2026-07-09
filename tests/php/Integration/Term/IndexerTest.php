<?php
/**
 * IndexerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class IndexerTest
 */
class IndexerTest extends TestCase {

	private SitemapProvider $provider;
	private TermItemStore $item_store;
	private SitemapStore $sitemap_store;

	public function set_up(): void {
		parent::set_up();

		$container = $this->create_container();

		$this->provider      = $container->get( SitemapProvider::class );
		$this->item_store    = $container->get( TermItemStore::class );
		$this->sitemap_store = $container->get( SitemapStore::class );
	}

	private function term_taxonomy_id( int $term_id, string $taxonomy ): int {
		return (int) get_term( $term_id, $taxonomy )->term_taxonomy_id;
	}

	private function create_tag_with_post( string $name ): int {
		$tag_id = self::factory()->term->create( [ 'taxonomy' => 'post_tag', 'name' => $name ] );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $tag_id ], 'post_tag' );

		return $tag_id;
	}

	public function test_index_objects_indexes_non_empty_terms_in_order(): void {
		$tag_a = $this->create_tag_with_post( 'Tag A' );
		$tag_b = $this->create_tag_with_post( 'Tag B' );
		$tag_c = $this->create_tag_with_post( 'Tag C' );

		$tag_empty = self::factory()->term->create( [ 'taxonomy' => 'post_tag', 'name' => 'Empty' ] );

		$results = $this->provider->index_objects( [ 'post_tag' ], false );

		$this->assertCount( 1, $results );
		$this->assertSame( 'term', $results[0]['object_type'] );
		$this->assertSame( 'post_tag', $results[0]['object_subtype'] );
		$this->assertSame( 3, $results[0]['objects_indexed'] );
		$this->assertNull( $results[0]['error'] );

		$sitemap = $this->sitemap_store->get_by_object_type( 'term', 'post_tag' );
		$this->assertNotNull( $sitemap );
		$this->assertSame( Sitemap::STATUS_INDEXED, $sitemap->status );
		$this->assertSame( 3, $sitemap->item_count );

		$tt_a = $this->term_taxonomy_id( $tag_a, 'post_tag' );
		$tt_b = $this->term_taxonomy_id( $tag_b, 'post_tag' );
		$tt_c = $this->term_taxonomy_id( $tag_c, 'post_tag' );

		$this->assertSame( 0, $this->item_store->get_one_by_object_id( $tt_a )->item_index );
		$this->assertSame( 1, $this->item_store->get_one_by_object_id( $tt_b )->item_index );
		$this->assertSame( 2, $this->item_store->get_one_by_object_id( $tt_c )->item_index );

		$tt_empty = $this->term_taxonomy_id( $tag_empty, 'post_tag' );
		$this->assertNull( $this->item_store->get_one_by_object_id( $tt_empty ) );
	}

	public function test_index_objects_reports_result_per_taxonomy(): void {
		$this->create_tag_with_post( 'Reported' );

		$results = $this->provider->index_objects( [ 'post_tag' ], false );

		$this->assertCount( 1, $results );
		$this->assertSame( 'term', $results[0]['object_type'] );
		$this->assertSame( 'post_tag', $results[0]['object_subtype'] );
		$this->assertSame( 1, $results[0]['objects_indexed'] );
		$this->assertNull( $results[0]['error'] );
	}
}
