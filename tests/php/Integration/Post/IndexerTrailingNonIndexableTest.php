<?php
/**
 * IndexerTrailingNonIndexableTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;

/**
 * Class IndexerTrailingNonIndexableTest
 */
class IndexerTrailingNonIndexableTest extends PostTestCase {

	public function test_trailing_non_indexable_post_does_not_leave_sitemap_indexing(): void {
		$this->boot();

		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$noindex = static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$calls = 0;
		add_filter(
			'static_sitemap_post_indexable',
			function ( $indexable, \WP_Post $post ) use ( $noindex, &$calls ) {
				if ( ++$calls > 50 ) {
					throw new \RuntimeException( 'Indexer keeps re-fetching the same non-indexable post' );
				}

				return $post->ID === $noindex ? false : $indexable;
			},
			10,
			2
		);

		$this->provider->index_objects( [ 'post' ] );

		$sitemap = $this->get_post_sitemap();

		$this->assertLessThanOrEqual( 50, $calls );
		$this->assertSame( Sitemap::STATUS_INDEXED, $sitemap->status );
		$this->assertContains(
			(int) $sitemap->id,
			array_map( fn( Sitemap $viewable ) => $viewable->id, $this->sitemap_store->get_viewable_sitemaps() )
		);
	}
}
