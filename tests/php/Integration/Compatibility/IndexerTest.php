<?php
/**
 * IndexerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\TermIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\UserIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class IndexerTest
 */
class IndexerTest extends TestCase {

	private function make_user_indexer(): UserIndexer {
		return $this->create_container()->get( UserIndexer::class );
	}

	private function make_term_indexer(): TermIndexer {
		return $this->create_container()->get( TermIndexer::class );
	}

	public function test_index_authors_backfills_profile_updated_for_capable_users(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );

		$this->make_user_indexer()->index_authors();

		$this->assertNotEmpty( get_user_meta( $user_id, '_yoast_wpseo_profile_updated', true ) );
	}

	public function test_index_authors_skips_incapable_users(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		$this->make_user_indexer()->index_authors();

		$this->assertEmpty( get_user_meta( $user_id, '_yoast_wpseo_profile_updated', true ) );
	}

	public function test_index_authors_does_not_overwrite_existing_meta(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		update_user_meta( $user_id, '_yoast_wpseo_profile_updated', 12345 );

		$this->make_user_indexer()->index_authors();

		$this->assertSame( '12345', get_user_meta( $user_id, '_yoast_wpseo_profile_updated', true ) );
	}

	public function test_indexed_terms_is_noop_when_nothing_inserted(): void {
		$this->assertNull( $this->make_term_indexer()->indexed_terms( 999, 0 ) );
	}

	public function test_indexed_terms_is_noop_for_unknown_sitemap(): void {
		$this->assertNull( $this->make_term_indexer()->indexed_terms( 999999, 5 ) );
	}
}
