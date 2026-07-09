<?php
/**
 * JobRunnerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Job
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Job;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobRunner;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class JobRunnerTest
 */
class JobRunnerTest extends TestCase {

	private JobStore $job_store;
	private PostItemStore $item_store;
	private SitemapStore $sitemap_store;
	private Logger $logger;
	private Sitemap $sitemap;

	public function set_up(): void {
		parent::set_up();

		$this->job_store     = new JobStore();
		$this->item_store    = new PostItemStore( 1000 );
		$this->sitemap_store = new SitemapStore();
		$this->logger        = new Logger();
		$this->sitemap       = $this->make_sitemap();
	}

	private function make_sitemap(): Sitemap {
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => 'post',
				'status'         => Sitemap::STATUS_INDEXED,
			]
		);
		$this->sitemap_store->insert_sitemap( $sitemap );

		return $sitemap;
	}

	private function runner(): JobRunner {
		return new JobRunner( $this->job_store, $this->item_store, $this->sitemap, $this->logger );
	}

	private function insert_item( int $post_id ): PostItem {
		$item = new PostItem(
			[
				'post_id'    => $post_id,
				'sitemap_id' => $this->sitemap->id,
				'url'        => "/post-$post_id",
				'item_index' => 0,
			]
		);
		$this->item_store->insert_item( $item );

		return $item;
	}

	public function test_claim_jobs_returns_count_and_populates_jobs(): void {
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 1 ) );
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 2 ) );

		$runner  = $this->runner();
		$claimed = $runner->claim_jobs();

		$this->assertSame( 2, $claimed );

		[ Job::ADD_ITEM => $add ] = $runner->get_deduplicated_jobs();
		$this->assertSame( [ 1 => 1, 2 => 2 ], $add );

		$runner->release_claim();
	}

	public function test_claim_jobs_returns_zero_when_none(): void {
		$this->assertSame( 0, $this->runner()->claim_jobs() );
	}

	public function test_two_runners_do_not_claim_same_jobs(): void {
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 1 ) );
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 2 ) );
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 3 ) );

		$runner_one = $this->runner();
		$runner_two = $this->runner();

		$first  = $runner_one->claim_jobs();
		$second = $runner_two->claim_jobs();

		$this->assertSame( 3, $first );
		$this->assertSame( 0, $second );

		$runner_one->release_claim();
		$runner_two->release_claim();
	}

	public function test_release_claim_frees_jobs_for_next_runner(): void {
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 1 ) );

		$runner_one = $this->runner();
		$this->assertSame( 1, $runner_one->claim_jobs() );
		$runner_one->release_claim();

		$runner_two = $this->runner();
		$this->assertSame( 1, $runner_two->claim_jobs() );
		$runner_two->release_claim();
	}

	public function test_delete_jobs_removes_claimed_jobs(): void {
		global $wpdb;

		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 1 ) );
		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 2 ) );

		$runner = $this->runner();
		$runner->claim_jobs();
		$runner->delete_jobs()->release_claim();

		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}sitemap_jobs WHERE sitemap_id = %d",
				$this->sitemap->id
			)
		);
		$this->assertSame( 0, $remaining );
	}

	public function test_remove_job_cancels_matching_add(): void {
		$item = $this->insert_item( 500 );

		$this->job_store->insert_job( Job::add_item( $this->sitemap->id, 500 ) );
		$this->job_store->insert_job( Job::remove_item( $item ) );

		$runner = $this->runner();
		$runner->claim_jobs();

		[
			Job::ADD_ITEM    => $add,
			Job::REMOVE_ITEM => $remove,
		] = $runner->get_deduplicated_jobs();

		$this->assertSame( [], $add );
		$this->assertArrayHasKey( $item->get_id(), $remove );

		$runner->release_claim();
	}

	public function test_reindex_sitemap_clears_reindex_items(): void {
		$item = $this->insert_item( 600 );

		$this->job_store->insert_job( Job::reindex_item( $item ) );
		$this->job_store->insert_job(
			new Job(
				[
					'sitemap_id'   => $this->sitemap->id,
					'object_id'    => null,
					'action'       => Job::REINDEX_SITEMAP,
					'scheduled_at' => current_time( 'mysql', true ),
				]
			)
		);

		$runner = $this->runner();
		$runner->claim_jobs();

		[
			Job::REINDEX_ITEM    => $reindex_items,
			Job::REINDEX_SITEMAP => $reindex_sitemap,
		] = $runner->get_deduplicated_jobs();

		$this->assertSame( [], $reindex_items );
		$this->assertSame( [ $this->sitemap->id => $this->sitemap->id ], $reindex_sitemap );

		$runner->release_claim();
	}

	public function test_update_last_modified_skipped_when_item_removed(): void {
		$item = $this->insert_item( 700 );

		$this->job_store->insert_job( Job::remove_item( $item ) );
		$this->job_store->insert_job( Job::update_last_modified( $item ) );

		$runner = $this->runner();
		$runner->claim_jobs();

		[
			Job::REMOVE_ITEM          => $remove,
			Job::UPDATE_LAST_MODIFIED => $update_last_modified,
		] = $runner->get_deduplicated_jobs();

		$this->assertArrayHasKey( $item->get_id(), $remove );
		$this->assertSame( [], $update_last_modified );

		$runner->release_claim();
	}
}
