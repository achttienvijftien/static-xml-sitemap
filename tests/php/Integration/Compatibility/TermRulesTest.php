<?php
/**
 * TermRulesTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\TermWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Query as TermQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class TermRulesTest
 */
class TermRulesTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		wp_cache_flush();
	}

	private function make_wpseo(): WordPressSeo {
		return $this->create_container()->get( WordPressSeo::class );
	}

	private function create_tag_with_post(): \WP_Term {
		$term_id = self::factory()->tag->create();
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'post_tag' );

		return get_term( $term_id, 'post_tag' );
	}

	public function test_taxonomies_keeps_public_valid_taxonomies(): void {
		$taxonomies = $this->make_wpseo()->taxonomies( [ 'category', 'post_tag' ] );

		$this->assertContains( 'category', $taxonomies );
		$this->assertContains( 'post_tag', $taxonomies );
	}

	public function test_taxonomies_excludes_noindexed_taxonomy(): void {
		update_option( 'wpseo_titles', [ 'noindex-tax-category' => true ] );

		$taxonomies = $this->make_wpseo()->taxonomies( [ 'category', 'post_tag' ] );

		$this->assertNotContains( 'category', $taxonomies );
		$this->assertContains( 'post_tag', $taxonomies );
	}

	public function test_taxonomies_excludes_non_public_taxonomy(): void {
		$this->assertSame( [], $this->make_wpseo()->taxonomies( [ 'nav_menu' ] ) );
	}

	public function test_taxonomies_respects_exclude_filter(): void {
		add_filter(
			'wpseo_sitemap_exclude_taxonomy',
			static fn( $excluded, $taxonomy ) => 'category' === $taxonomy ? true : $excluded,
			10,
			2
		);

		$this->assertNotContains( 'category', $this->make_wpseo()->taxonomies( [ 'category', 'post_tag' ] ) );
	}

	public function test_term_indexable_returns_input_when_already_false(): void {
		$term = $this->create_tag_with_post();

		$this->assertFalse( $this->make_wpseo()->term_indexable( false, $term ) );
	}

	public function test_term_indexable_false_for_noindex_meta(): void {
		$term = $this->create_tag_with_post();
		update_term_meta( $term->term_id, 'wpseo_noindex', 'noindex' );

		$this->assertFalse( $this->make_wpseo()->term_indexable( true, $term ) );
	}

	public function test_term_indexable_true_for_non_empty_non_hierarchical_term(): void {
		$term = $this->create_tag_with_post();

		$this->assertTrue( $this->make_wpseo()->term_indexable( true, $term ) );
	}

	public function test_term_indexable_false_for_empty_non_hierarchical_term(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );

		$this->assertFalse( $this->make_wpseo()->term_indexable( true, $term ) );
	}

	public function test_term_indexable_true_when_hide_empty_disabled(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );

		add_filter( 'wpseo_sitemap_exclude_empty_terms', static fn() => false );

		$this->assertTrue( $this->make_wpseo()->term_indexable( true, $term ) );
	}

	public function test_term_indexable_true_for_non_empty_hierarchical_term(): void {
		$term_id = self::factory()->category->create();
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $term_id ], 'category' );

		$this->assertTrue( $this->make_wpseo()->term_indexable( true, get_term( $term_id, 'category' ) ) );
	}

	public function test_term_url_returns_url_when_no_canonical(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );

		$this->assertSame( '/tag', $this->make_wpseo()->term_url( '/tag', $term ) );
	}

	public function test_term_url_returns_url_when_canonical_matches(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );
		update_term_meta( $term->term_id, 'wpseo_canonical', '/tag' );

		$this->assertSame( '/tag', $this->make_wpseo()->term_url( '/tag', $term ) );
	}

	public function test_term_url_returns_null_when_canonical_differs(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );
		update_term_meta( $term->term_id, 'wpseo_canonical', '/somewhere-else' );

		$this->assertNull( $this->make_wpseo()->term_url( '/tag', $term ) );
	}

	public function test_term_item_data_returns_item_data_by_default(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );

		$this->assertSame(
			[ 'url' => '/tag/news' ],
			$this->make_wpseo()->term_item_data( [ 'url' => '/tag/news' ], $term )
		);
	}

	public function test_term_item_data_returns_null_when_filter_empties_loc(): void {
		$term = get_term( self::factory()->tag->create(), 'post_tag' );

		add_filter( 'wpseo_sitemap_entry', static fn( $url ) => array_merge( $url, [ 'loc' => '' ] ) );

		$this->assertNull( $this->make_wpseo()->term_item_data( [ 'url' => '/tag/news' ], $term ) );
	}

	public function test_term_invalidations_short_circuits_on_object_exists(): void {
		$input = Invalidations::OBJECT_EXISTS | Invalidations::ITEM_INDEX;

		$this->assertSame( $input, $this->make_wpseo()->term_invalidations( $input, 0 ) );
	}

	public function test_term_invalidations_noindex_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->term_invalidations( 0, TermWatcher::NOINDEX_META_UPDATED )
		);
	}

	public function test_term_invalidations_canonical_event_sets_is_indexable_and_url(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE | Invalidations::ITEM_URL,
			$this->make_wpseo()->term_invalidations( 0, TermWatcher::CANONICAL_META_UPDATED )
		);
	}

	public function test_term_invalidations_last_modified_event_sets_last_modified(): void {
		$this->assertSame(
			Invalidations::ITEM_LAST_MODIFIED,
			$this->make_wpseo()->term_invalidations( 0, TermWatcher::TERM_LAST_MODIFIED_UPDATED )
		);
	}

	public function test_term_invalidations_child_count_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->term_invalidations( 0, TermWatcher::CHILD_TERM_COUNT_UPDATED )
		);
	}

	public function test_excluded_terms_respects_filter(): void {
		add_filter( 'wpseo_exclude_from_sitemap_by_term_ids', static fn() => [ 5, 6 ] );

		$excluded = $this->make_wpseo()->excluded_terms( [] );

		$this->assertContains( 5, $excluded );
		$this->assertContains( 6, $excluded );
	}

	public function test_excluded_terms_merges_existing_ids(): void {
		$excluded = $this->make_wpseo()->excluded_terms( [ 1, 2 ] );

		$this->assertContains( 1, $excluded );
		$this->assertContains( 2, $excluded );
	}

	public function test_terms_query_passes_through_non_term_query(): void {
		$this->assertSame( 'not-a-query', $this->make_wpseo()->terms_query( 'not-a-query' ) );
	}

	public function test_terms_query_leaves_non_indexable_query_untouched(): void {
		$query = new TermQuery( 'category' );

		$result = $this->make_wpseo()->terms_query( $query );

		$this->assertFalse( $result->hierarchical );
	}

	public function test_terms_query_sets_hierarchical_and_hide_empty_for_indexable_query(): void {
		$query = ( new TermQuery( 'category' ) )->set_indexable( true );

		$result = $this->make_wpseo()->terms_query( $query );

		$this->assertTrue( $result->hierarchical );
		$this->assertTrue( $result->hide_empty );
	}

	public function test_terms_query_disables_hide_empty_via_filter(): void {
		add_filter( 'wpseo_sitemap_exclude_empty_terms', static fn() => false );

		$query = ( new TermQuery( 'category' ) )->set_indexable( true );

		$this->assertFalse( $this->make_wpseo()->terms_query( $query )->hide_empty );
	}

	public function test_terms_query_field_maps_known_fields(): void {
		$wpseo = $this->make_wpseo();

		$this->assertSame( 'si.last_modified', $wpseo->terms_query_field( 'x', 'modified' ) );
		$this->assertSame( 't.name', $wpseo->terms_query_field( 'x', 'name' ) );
		$this->assertSame( 'x', $wpseo->terms_query_field( 'x', 'id' ) );
	}

	public function test_terms_orderby(): void {
		$this->assertSame( 'name', $this->make_wpseo()->terms_orderby() );
	}

	public function test_terms_clauses_adds_sitemap_items_join_for_modified_field(): void {
		$clauses = [ 'fields' => [ 'modified' ], 'join' => [], 'where' => [] ];

		$result = $this->make_wpseo()->terms_clauses( $clauses, new TermQuery( 'category' ) );

		$this->assertArrayHasKey( 'sitemap_items', $result['join'] );
	}

	public function test_terms_clauses_adds_terms_join_for_name_field(): void {
		$clauses = [ 'fields' => [ 'name' ], 'join' => [], 'where' => [] ];

		$result = $this->make_wpseo()->terms_clauses( $clauses, new TermQuery( 'category' ) );

		$this->assertArrayHasKey( 'terms', $result['join'] );
	}

	public function test_terms_clauses_adds_after_clause_for_modified_orderby(): void {
		$clauses = [ 'fields' => [ 'modified' ], 'join' => [], 'where' => [] ];
		$query   = ( new TermQuery( 'category' ) )->set_orderby( 'modified' );
		$query->set_after( 'modified', '2020-01-01 00:00:00', 5 );

		$result = $this->make_wpseo()->terms_clauses( $clauses, $query );

		$this->assertArrayHasKey( 'after', $result['where'] );
	}

	public function test_force_queue_add_always_queues_terms(): void {
		$this->assertTrue( $this->make_wpseo()->force_queue_add( false, 'term' ) );
	}

	public function test_force_queue_add_passes_through_other_types(): void {
		$wpseo = $this->make_wpseo();

		$this->assertFalse( $wpseo->force_queue_add( false, 'post' ) );
		$this->assertTrue( $wpseo->force_queue_add( true, 'post' ) );
	}
}
