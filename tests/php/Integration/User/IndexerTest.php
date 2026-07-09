<?php
/**
 * IndexerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItemStore;

/**
 * Class IndexerTest
 */
class IndexerTest extends TestCase {

	private SitemapProvider $provider;
	private UserItemStore $item_store;
	private SitemapStore $sitemap_store;

	public function set_up(): void {
		parent::set_up();

		$container = $this->create_container();

		$this->provider      = $container->get( SitemapProvider::class );
		$this->item_store    = $container->get( UserItemStore::class );
		$this->sitemap_store = $container->get( SitemapStore::class );
	}

	private function delete_all_posts(): void {
		$posts = get_posts(
			[ 'numberposts' => -1, 'post_type' => 'any', 'post_status' => 'any' ]
		);

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	public function test_index_objects_indexes_authors_with_published_posts_in_order(): void {
		$this->delete_all_posts();

		$author_a = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_author_a' ] );
		$author_b = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_author_b' ] );
		$author_c = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_author_c' ] );

		foreach ( [ $author_a, $author_b, $author_c ] as $author ) {
			self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );
		}

		self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_author_0_no_posts' ] );

		$results = $this->provider->index_objects( [], false );

		$this->assertCount( 1, $results );
		$this->assertSame( 'user', $results[0]['object_type'] );
		$this->assertNull( $results[0]['object_subtype'] );
		$this->assertSame( 3, $results[0]['objects_indexed'] );
		$this->assertNull( $results[0]['error'] );

		$sitemap = $this->sitemap_store->get_by_object_type( 'user' );
		$this->assertNotNull( $sitemap );
		$this->assertSame( Sitemap::STATUS_INDEXED, $sitemap->status );
		$this->assertSame( 3, $sitemap->item_count );

		$this->assertSame( 0, $this->item_store->get_one_by_object_id( $author_a )->item_index );
		$this->assertSame( 1, $this->item_store->get_one_by_object_id( $author_b )->item_index );
		$this->assertSame( 2, $this->item_store->get_one_by_object_id( $author_c )->item_index );
	}

	public function test_index_objects_skips_authors_without_published_posts(): void {
		$this->delete_all_posts();

		$author  = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_with_post' ] );
		$no_post = self::factory()->user->create( [ 'role' => 'author', 'user_login' => 'index_0_no_post' ] );

		self::factory()->post->create( [ 'post_author' => $author, 'post_status' => 'publish' ] );

		$this->provider->index_objects( [], false );

		$this->assertNotNull( $this->item_store->get_one_by_object_id( $author ) );
		$this->assertNull( $this->item_store->get_one_by_object_id( $no_post ) );
	}
}
