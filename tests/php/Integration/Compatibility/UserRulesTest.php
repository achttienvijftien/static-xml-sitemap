<?php
/**
 * UserRulesTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\UserWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Query as UserQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class UserRulesTest
 */
class UserRulesTest extends TestCase {

	private function make_wpseo(): WordPressSeo {
		return $this->create_container()->get( WordPressSeo::class );
	}

	public function test_authors_enabled_returns_input_when_already_false(): void {
		$this->assertFalse( $this->make_wpseo()->authors_enabled( false ) );
	}

	public function test_authors_enabled_true_without_yoast_options(): void {
		delete_option( 'wpseo_titles' );

		$this->assertTrue( $this->make_wpseo()->authors_enabled( true ) );
	}

	public function test_authors_enabled_false_when_authors_disabled(): void {
		update_option( 'wpseo_titles', [ 'disable-author' => true ] );

		$this->assertFalse( $this->make_wpseo()->authors_enabled( true ) );
	}

	public function test_authors_enabled_false_when_authors_noindexed(): void {
		update_option( 'wpseo_titles', [ 'noindex-author-wpseo' => true ] );

		$this->assertFalse( $this->make_wpseo()->authors_enabled( true ) );
	}

	public function test_user_modified_returns_null_without_profile_updated(): void {
		$user = get_userdata( self::factory()->user->create() );

		$this->assertNull( $this->make_wpseo()->user_modified( null, $user ) );
	}

	public function test_user_modified_uses_profile_updated_meta(): void {
		$timestamp = 1700000000;
		$user_id   = self::factory()->user->create();
		update_user_meta( $user_id, '_yoast_wpseo_profile_updated', $timestamp );

		$this->assertSame(
			gmdate( 'Y-m-d H:i:s', $timestamp ),
			$this->make_wpseo()->user_modified( null, get_userdata( $user_id ) )
		);
	}

	public function test_user_compare_callback_orders_by_profile_updated(): void {
		$older = self::factory()->user->create();
		$newer = self::factory()->user->create();
		update_user_meta( $older, '_yoast_wpseo_profile_updated', 1000 );
		update_user_meta( $newer, '_yoast_wpseo_profile_updated', 2000 );

		$compare = $this->make_wpseo()->user_compare_callback();

		$this->assertLessThan( 0, $compare( get_userdata( $older ), get_userdata( $newer ) ) );
		$this->assertGreaterThan( 0, $compare( get_userdata( $newer ), get_userdata( $older ) ) );
	}

	public function test_user_indexable_returns_input_when_already_false(): void {
		$user = get_userdata( self::factory()->user->create() );

		$this->assertFalse( $this->make_wpseo()->user_indexable( false, $user ) );
	}

	public function test_user_indexable_false_when_excluded_by_filter(): void {
		$user = get_userdata( self::factory()->user->create() );

		add_filter( 'wpseo_sitemap_exclude_author', static fn() => [] );

		$this->assertFalse( $this->make_wpseo()->user_indexable( true, $user ) );
	}

	public function test_user_indexable_true_and_backfills_profile_updated(): void {
		$user_id = self::factory()->user->create();

		$this->assertTrue( $this->make_wpseo()->user_indexable( true, get_userdata( $user_id ) ) );
		$this->assertNotEmpty( get_user_meta( $user_id, '_yoast_wpseo_profile_updated', true ) );
	}

	public function test_user_item_data_returns_item_data_by_default(): void {
		$user = get_userdata( self::factory()->user->create() );

		$result = $this->make_wpseo()->user_item_data( [ 'url' => '/author/jane' ], $user );

		$this->assertSame( '/author/jane', $result['url'] );
	}

	public function test_user_item_data_returns_null_when_filter_empties_loc(): void {
		$user = get_userdata( self::factory()->user->create() );

		add_filter( 'wpseo_sitemap_entry', static fn( $url ) => array_merge( $url, [ 'loc' => '' ] ) );

		$this->assertNull( $this->make_wpseo()->user_item_data( [ 'url' => '/author/jane' ], $user ) );
	}

	public function test_user_invalidations_short_circuits_on_object_exists(): void {
		$input = Invalidations::OBJECT_EXISTS | Invalidations::ITEM_INDEX;

		$this->assertSame( $input, $this->make_wpseo()->user_invalidations( $input, 0 ) );
	}

	public function test_user_invalidations_clears_item_index_without_profile_event(): void {
		$this->assertSame( 0, $this->make_wpseo()->user_invalidations( Invalidations::ITEM_INDEX, 0 ) );
	}

	public function test_user_invalidations_profile_event_sets_item_index(): void {
		$this->assertSame(
			Invalidations::ITEM_INDEX,
			$this->make_wpseo()->user_invalidations( 0, UserWatcher::PROFILE_UPDATED_UPDATED )
		);
	}

	public function test_user_invalidations_noindex_author_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->user_invalidations( 0, UserWatcher::NOINDEX_AUTHOR_UPDATED )
		);
	}

	public function test_user_invalidations_user_level_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->user_invalidations( 0, UserWatcher::USER_LEVEL_UPDATED )
		);
	}

	public function test_user_invalidations_roles_event_sets_is_indexable(): void {
		$this->assertSame(
			Invalidations::IS_INDEXABLE,
			$this->make_wpseo()->user_invalidations( 0, UserWatcher::USER_ROLES_UPDATED )
		);
	}

	public function test_authors_query_field_maps_modified_to_from_unixtime(): void {
		$column = $this->make_wpseo()->authors_query_field( 'u.ID', 'modified' );

		$this->assertStringContainsString( 'FROM_UNIXTIME', $column );
	}

	public function test_authors_query_field_passes_through_other_fields(): void {
		$this->assertSame( 'u.ID', $this->make_wpseo()->authors_query_field( 'u.ID', 'id' ) );
	}

	public function test_authors_orderby_and_pagination_order(): void {
		$wpseo = $this->make_wpseo();

		$this->assertSame( 'modified', $wpseo->authors_orderby() );
		$this->assertSame( 'DESC', $wpseo->authors_pagination_order() );
	}

	public function test_authors_clauses_adds_profile_updated_join_for_modified_orderby(): void {
		$clauses = [ 'fields' => [ 'id' ], 'join' => [], 'where' => [] ];
		$query   = ( new UserQuery() )->set_orderby( 'modified' );

		$result = $this->make_wpseo()->authors_clauses( $clauses, $query );

		$this->assertArrayHasKey( 'meta_profile_updated', $result['join'] );
		$this->assertStringContainsString( '_yoast_wpseo_profile_updated', $result['join']['meta_profile_updated'] );
	}

	public function test_authors_clauses_adds_noindex_join_for_indexable_query(): void {
		update_option( 'wpseo_titles', [ 'noindex-author-noposts-wpseo' => false ] );

		$clauses = [ 'fields' => [ 'id' ], 'join' => [], 'where' => [] ];
		$query   = ( new UserQuery() )->set_indexable( true );

		$result = $this->make_wpseo()->authors_clauses( $clauses, $query );

		$this->assertArrayHasKey( 'meta_noindex', $result['join'] );
		$this->assertStringContainsString( 'wpseo_noindex_author', $result['join']['meta_noindex'] );
	}
}
