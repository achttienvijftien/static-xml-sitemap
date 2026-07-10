<?php
/**
 * JobStoreTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Job
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Job;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class JobStoreTest
 */
class JobStoreTest extends TestCase {

	private JobStore $store;

	public function set_up(): void {
		parent::set_up();

		$this->store = new JobStore();
	}

	private function claim_id(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	private function count_jobs(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sitemap_jobs" );
	}

	public function test_insert_job_assigns_id(): void {
		$job = $this->store->insert_job( Job::add_item( 1, 100 ) );

		$this->assertInstanceOf( Job::class, $job );
		$this->assertGreaterThan( 0, $job->get_id() );
	}

	public function test_insert_job_persists_fields(): void {
		$job = $this->store->insert_job( Job::add_item( 2, 101 ) );

		$stored = $this->store->get( $job->get_id() );

		$this->assertSame( 2, $stored->sitemap_id );
		$this->assertSame( 101, $stored->object_id );
		$this->assertSame( Job::ADD_ITEM, $stored->action );
		$this->assertNull( $stored->claim_id );
		$this->assertNull( $stored->claimed_at );
	}

	public function test_get_returns_null_for_missing(): void {
		$this->assertNull( $this->store->get( 555555 ) );
	}

	public function test_duplicate_sitemap_object_action_is_deduplicated(): void {
		$first  = $this->store->insert_job( Job::add_item( 3, 200 ) );
		$second = $this->store->insert_job( Job::add_item( 3, 200 ) );

		$this->assertInstanceOf( Job::class, $first );
		$this->assertNull( $second );
		$this->assertSame( 1, $this->count_jobs() );
	}

	public function test_different_action_is_not_deduplicated(): void {
		$item = new PostItem(
			[
				'id'         => 7,
				'post_id'    => 300,
				'sitemap_id' => 4,
				'url'        => '/x',
			]
		);

		$add    = $this->store->insert_job( Job::add_item( 4, 300 ) );
		$remove = $this->store->insert_job( Job::remove_item( $item ) );

		$this->assertInstanceOf( Job::class, $add );
		$this->assertInstanceOf( Job::class, $remove );
		$this->assertSame( 2, $this->count_jobs() );
	}

	public function test_different_object_is_not_deduplicated(): void {
		$this->store->insert_job( Job::add_item( 5, 400 ) );
		$this->store->insert_job( Job::add_item( 5, 401 ) );

		$this->assertSame( 2, $this->count_jobs() );
	}

	public function test_null_object_id_allows_duplicates(): void {
		$make = fn() => new Job(
			[
				'sitemap_id'   => 6,
				'object_id'    => null,
				'action'       => Job::REINDEX_SITEMAP,
				'scheduled_at' => current_time( 'mysql', true ),
			]
		);

		$this->assertInstanceOf( Job::class, $this->store->insert_job( $make() ) );
		$this->assertInstanceOf( Job::class, $this->store->insert_job( $make() ) );
		$this->assertSame( 2, $this->count_jobs() );
	}

	public function test_claim_jobs_marks_and_returns_count(): void {
		$this->store->insert_job( Job::add_item( 10, 1 ) );
		$this->store->insert_job( Job::add_item( 10, 2 ) );

		$claim  = $this->claim_id();
		$result = $this->store->claim_jobs( 10, $claim );

		$this->assertSame( 2, $result );
		$this->assertCount( 2, $this->store->get_by_claim_id( $claim ) );
	}

	public function test_claim_jobs_respects_limit(): void {
		$this->store->insert_job( Job::add_item( 11, 1 ) );
		$this->store->insert_job( Job::add_item( 11, 2 ) );
		$this->store->insert_job( Job::add_item( 11, 3 ) );

		$result = $this->store->claim_jobs( 11, $this->claim_id(), 2 );

		$this->assertSame( 2, $result );
	}

	public function test_two_claims_do_not_overlap(): void {
		$this->store->insert_job( Job::add_item( 12, 1 ) );
		$this->store->insert_job( Job::add_item( 12, 2 ) );
		$this->store->insert_job( Job::add_item( 12, 3 ) );

		$claim_one = $this->claim_id();
		$claim_two = $this->claim_id();

		$first  = $this->store->claim_jobs( 12, $claim_one, 2 );
		$second = $this->store->claim_jobs( 12, $claim_two, 2 );

		$this->assertSame( 2, $first );
		$this->assertSame( 1, $second );

		$ids_one = array_map( fn( $job ) => $job->get_id(), $this->store->get_by_claim_id( $claim_one ) );
		$ids_two = array_map( fn( $job ) => $job->get_id(), $this->store->get_by_claim_id( $claim_two ) );

		$this->assertSame( [], array_intersect( $ids_one, $ids_two ) );
	}

	public function test_claim_ignores_other_sitemaps(): void {
		$this->store->insert_job( Job::add_item( 13, 1 ) );

		$this->assertSame( 0, $this->store->claim_jobs( 99, $this->claim_id() ) );
	}

	public function test_claim_ignores_future_scheduled_jobs(): void {
		$future = new Job(
			[
				'sitemap_id'   => 14,
				'object_id'    => 1,
				'action'       => Job::ADD_ITEM,
				'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
			]
		);
		$this->store->insert_job( $future );

		$this->assertSame( 0, $this->store->claim_jobs( 14, $this->claim_id() ) );
	}

	public function test_get_by_claim_id_ordered_by_id(): void {
		$this->store->insert_job( Job::add_item( 15, 1 ) );
		$this->store->insert_job( Job::add_item( 15, 2 ) );

		$claim = $this->claim_id();
		$this->store->claim_jobs( 15, $claim );

		$jobs = $this->store->get_by_claim_id( $claim );
		$ids  = array_map( fn( $job ) => $job->get_id(), $jobs );

		$sorted = $ids;
		sort( $sorted );
		$this->assertSame( $sorted, $ids );
	}

	public function test_release_claim_frees_jobs(): void {
		$this->store->insert_job( Job::add_item( 16, 1 ) );
		$this->store->insert_job( Job::add_item( 16, 2 ) );

		$claim = $this->claim_id();
		$this->store->claim_jobs( 16, $claim );

		$released = $this->store->release_claim( $claim );

		$this->assertSame( 2, $released );
		$this->assertCount( 0, $this->store->get_by_claim_id( $claim ) );
	}

	public function test_delete_jobs_removes_them(): void {
		$job_one = $this->store->insert_job( Job::add_item( 17, 1 ) );
		$job_two = $this->store->insert_job( Job::add_item( 17, 2 ) );

		$deleted = $this->store->delete_jobs( [ $job_one, $job_two ] );

		$this->assertSame( 2, $deleted );
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_delete_jobs_empty_returns_zero(): void {
		$this->assertSame( 0, $this->store->delete_jobs( [] ) );
	}
}
