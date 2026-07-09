<?php
/**
 * PostRulesTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\PostWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Query as PostQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class PostRulesTest
 */
class PostRulesTest extends TestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/YoastSeoStub.php';
	}

	public function set_up(): void {
		parent::set_up();

		YoastSeoStub::reset();
	}

	private function make_wpseo(): WordPressSeo {
		return $this->create_container()->get( WordPressSeo::class );
	}

	public function test_post_statuses_defaults_to_publish(): void {
		$this->assertSame( [ 'publish' ], $this->make_wpseo()->post_statuses( [], 'post' ) );
	}

	public function test_post_statuses_adds_inherit_for_attachment(): void {
		$statuses = $this->make_wpseo()->post_statuses( [ 'publish' ], 'attachment' );

		$this->assertContains( 'inherit', $statuses );
		$this->assertContains( 'publish', $statuses );
	}

	public function test_post_statuses_respects_filter(): void {
		add_filter(
			'wpseo_sitemap_post_statuses',
			static fn() => [ 'publish', 'private' ]
		);

		$this->assertSame( [ 'publish', 'private' ], $this->make_wpseo()->post_statuses( [], 'post' ) );
	}

	public function test_posts_clauses_leaves_non_attachment_untouched(): void {
		$clauses = [ 'join' => [], 'where' => [] ];
		$query   = new PostQuery( 'post' );

		$this->assertSame( $clauses, $this->make_wpseo()->posts_clauses( $clauses, $query ) );
	}

	public function test_posts_clauses_adds_attachment_parent_status_clause(): void {
		$clauses = [ 'join' => [], 'where' => [] ];
		$query   = ( new PostQuery( 'attachment' ) )->set_post_status( [ 'publish', 'inherit' ] );

		$result = $this->make_wpseo()->posts_clauses( $clauses, $query );

		$this->assertNotEmpty( $result['join'] );
		$this->assertStringContainsString( "p2.post_status IN ('publish')", implode( ' ', $result['where'] ) );
	}

	public function test_posts_clauses_ignores_attachment_with_only_inherit_status(): void {
		$clauses = [ 'join' => [], 'where' => [] ];
		$query   = ( new PostQuery( 'attachment' ) )->set_post_status( [ 'inherit' ] );

		$this->assertSame( $clauses, $this->make_wpseo()->posts_clauses( $clauses, $query ) );
	}

	public function test_excluded_posts_includes_front_page_and_posts_page(): void {
		update_option( 'page_on_front', 42 );
		update_option( 'page_for_posts', 99 );

		$excluded = $this->make_wpseo()->excluded_posts( [], 'page' );

		$this->assertContains( 42, $excluded );
		$this->assertContains( 99, $excluded );
	}

	public function test_excluded_posts_does_not_affect_non_page_type(): void {
		update_option( 'page_on_front', 42 );

		$this->assertNotContains( 42, $this->make_wpseo()->excluded_posts( [], 'post' ) );
	}

	public function test_excluded_posts_respects_filter(): void {
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );

		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', static fn() => [ 7, 8 ] );

		$excluded = $this->make_wpseo()->excluded_posts( [], 'page' );

		$this->assertContains( 7, $excluded );
		$this->assertContains( 8, $excluded );
	}

	public function test_post_types_filters_out_excluded_post_type(): void {
		add_filter(
			'wpseo_sitemap_exclude_post_type',
			static fn( $excluded, $post_type ) => 'page' === $post_type ? true : $excluded,
			10,
			2
		);

		$post_types = $this->make_wpseo()->post_types( [ 'post', 'page' ] );

		$this->assertContains( 'post', $post_types );
		$this->assertNotContains( 'page', $post_types );
	}

	public function test_post_types_keeps_accessible_indexable_types(): void {
		$this->assertSame(
			[ 'post', 'page' ],
			array_values( $this->make_wpseo()->post_types( [ 'post', 'page' ] ) )
		);
	}

	public function test_post_types_drops_inaccessible_type(): void {
		YoastSeoStub::$accessible_post_types = [ 'post' ];

		$this->assertSame(
			[ 'post' ],
			array_values( $this->make_wpseo()->post_types( [ 'post', 'page' ] ) )
		);
	}

	public function test_post_indexable_returns_input_when_already_false(): void {
		$post = get_post( self::factory()->post->create() );

		$this->assertFalse( $this->make_wpseo()->post_indexable( false, $post ) );
	}

	public function test_post_indexable_true_for_published_post(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );

		$this->assertTrue( $this->make_wpseo()->post_indexable( true, $post ) );
	}

	public function test_post_indexable_false_for_noindex_meta(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', '1' );

		$this->assertFalse( $this->make_wpseo()->post_indexable( true, get_post( $post_id ) ) );
	}

	public function test_post_indexable_false_for_canonical_mismatch(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, '_yoast_wpseo_canonical', 'https://example.org/somewhere-else' );

		$this->assertFalse( $this->make_wpseo()->post_indexable( true, get_post( $post_id ) ) );
	}

	public function test_post_indexable_false_for_non_indexable_status(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'draft' ] ) );

		$this->assertFalse( $this->make_wpseo()->post_indexable( true, $post ) );
	}

	public function test_post_indexable_false_for_excluded_front_page(): void {
		$page_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );
		update_option( 'page_on_front', $page_id );

		$this->assertFalse( $this->make_wpseo()->post_indexable( true, get_post( $page_id ) ) );
	}

	public function test_post_url_returns_url_when_no_canonical(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );
		$url  = get_permalink( $post->ID );

		$this->assertSame( $url, $this->make_wpseo()->post_url( $url, $post ) );
	}

	public function test_post_url_returns_url_when_canonical_matches(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$url     = get_permalink( $post_id );
		update_post_meta( $post_id, '_yoast_wpseo_canonical', $url );

		$this->assertSame( $url, $this->make_wpseo()->post_url( $url, get_post( $post_id ) ) );
	}

	public function test_post_url_returns_null_when_canonical_differs(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, '_yoast_wpseo_canonical', 'https://example.org/other' );

		$this->assertNull( $this->make_wpseo()->post_url( get_permalink( $post_id ), get_post( $post_id ) ) );
	}

	public function test_post_url_applies_wpseo_filter(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );

		add_filter( 'wpseo_xml_sitemap_post_url', static fn() => 'https://example.org/filtered' );

		$this->assertSame(
			'https://example.org/filtered',
			$this->make_wpseo()->post_url( get_permalink( $post->ID ), $post )
		);
	}

	public function test_post_item_data_returns_item_data_by_default(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );

		$this->assertSame(
			[ 'url' => '/example' ],
			$this->make_wpseo()->post_item_data( [ 'url' => '/example' ], $post )
		);
	}

	public function test_post_item_data_returns_null_when_filter_empties_loc(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );

		add_filter( 'wpseo_sitemap_entry', static fn( $url ) => array_merge( $url, [ 'loc' => '' ] ) );

		$this->assertNull( $this->make_wpseo()->post_item_data( [ 'url' => '/example' ], $post ) );
	}

	public function test_post_item_data_uses_filtered_loc(): void {
		$post = get_post( self::factory()->post->create( [ 'post_status' => 'publish' ] ) );

		add_filter( 'wpseo_sitemap_entry', static fn( $url ) => array_merge( $url, [ 'loc' => '/changed' ] ) );

		$this->assertSame(
			[ 'url' => '/changed' ],
			$this->make_wpseo()->post_item_data( [ 'url' => '/example' ], $post )
		);
	}

	public function test_post_invalidations_short_circuits_on_object_exists(): void {
		$input = Invalidations::OBJECT_EXISTS | Invalidations::ITEM_INDEX;

		$this->assertSame( $input, $this->make_wpseo()->post_invalidations( $input, 0 ) );
	}

	public function test_post_invalidations_clears_item_index_without_modified_event(): void {
		$this->assertSame( 0, $this->make_wpseo()->post_invalidations( Invalidations::ITEM_INDEX, 0 ) );
	}

	public function test_post_invalidations_noindex_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->post_invalidations( 0, PostWatcher::NOINDEX_META_UPDATED )
		);
	}

	public function test_post_invalidations_canonical_event_sets_is_indexable_and_url(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE | Invalidations::ITEM_URL,
			$this->make_wpseo()->post_invalidations( 0, PostWatcher::CANONICAL_META_UPDATED )
		);
	}

	public function test_post_invalidations_modified_event_sets_item_index(): void {
		$this->assertSame(
			Invalidations::ITEM_INDEX,
			$this->make_wpseo()->post_invalidations( 0, PostWatcher::POST_MODIFIED_UPDATED )
		);
	}

	public function test_is_post_status_indexable(): void {
		$wpseo = $this->make_wpseo();

		$this->assertTrue( $wpseo->is_post_status_indexable( 'publish', 'post' ) );
		$this->assertFalse( $wpseo->is_post_status_indexable( 'draft', 'post' ) );
		$this->assertTrue( $wpseo->is_post_status_indexable( 'inherit', 'attachment' ) );
		$this->assertTrue( $wpseo->is_post_status_indexable( 'publish', 'attachment' ) );
	}
}
