<?php
/**
 * SitemapProviderTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class SitemapProviderTest
 */
class SitemapProviderTest extends PostTestCase {

	public function test_add_to_sitemap_fast_path_appends_item_directly(): void {
		$this->boot();
		$this->provider->index_objects( [ 'post' ] );

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->provider->add_to_sitemap( get_post( $post_id ) );

		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->assertNotNull( $item );
		$this->assertSame( 0, $item->item_index );

		$sitemap = $this->get_post_sitemap();
		$this->assertSame( 1, (int) $sitemap->item_count );
		$this->assertSame( 0, (int) $sitemap->last_item_index );
		$this->assertSame( $post_id, (int) $sitemap->last_object_id );
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_add_to_sitemap_slow_path_queues_add_item_job_when_locked(): void {
		$this->boot();
		$this->provider->index_objects( [ 'post' ] );

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );

		$sitemap = $this->sitemap_store->get_by_object_type( 'post', 'post' );
		$lock    = Sitemap::get_lock( $sitemap->id )->set_max_tries( 1 );
		$this->assertTrue( $lock->acquire() );

		$this->provider->add_to_sitemap( get_post( $post_id ) );

		$lock->release();

		$this->assertNull( $this->item_store->get_one_by_object_id( $post_id ) );

		$add_jobs = $this->get_jobs( Job::ADD_ITEM );
		$this->assertCount( 1, $add_jobs );
		$this->assertSame( $post_id, (int) $add_jobs[0]->object_id );
	}

	public function test_process_invalidations_deleted_queues_remove_job(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );

		wp_delete_post( $post_id, true );
		$this->provider->process_watches( $post_id, Watcher::POST_DELETED );

		$remove_jobs = $this->get_jobs( Job::REMOVE_ITEM );
		$this->assertCount( 1, $remove_jobs );
		$this->assertSame( $item->id, (int) $remove_jobs[0]->sitemap_item_id );
	}

	public function test_process_invalidations_permalink_change_updates_url_in_place(): void {
		$this->boot();
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = static::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'first-slug',
			]
		);
		$this->provider->index_objects( [ 'post' ] );

		wp_update_post(
			[
				'ID'        => $post_id,
				'post_name' => 'second-slug',
			]
		);
		$this->provider->process_watches( $post_id, Watcher::POST_PERMALINK_UPDATED );

		$this->assertSame( '/second-slug/', $this->item_store->get_one_by_object_id( $post_id )->url );
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_process_invalidations_item_index_queues_reindex_job(): void {
		$this->boot();
		add_filter( 'static_sitemap_post_invalidations', fn() => Invalidations::ITEM_INDEX );

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );

		$this->provider->process_watches( $post_id, Watcher::POST_SAVED );

		$reindex_jobs = $this->get_jobs( Job::REINDEX_ITEM );
		$this->assertCount( 1, $reindex_jobs );
		$this->assertSame( $item->id, (int) $reindex_jobs[0]->sitemap_item_id );
	}

	public function test_run_jobs_reindexes_a_non_last_item(): void {
		$this->boot();
		add_filter( 'static_sitemap_posts_orderby', fn() => 'modified' );

		$post_1 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_2 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_3 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_4 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->set_post_modified( $post_1, '2020-01-01 00:00:00' );
		$this->set_post_modified( $post_2, '2020-01-02 00:00:00' );
		$this->set_post_modified( $post_3, '2020-01-03 00:00:00' );
		$this->set_post_modified( $post_4, '2020-01-04 00:00:00' );

		$this->provider->index_objects( [ 'post' ] );

		$this->set_post_modified( $post_2, '2020-01-05 00:00:00' );
		$item = $this->item_store->get_one_by_object_id( $post_2 );
		$this->job_store->insert_job( Job::reindex_item( $item ) );

		$this->provider->run_jobs( [ 'post' ] );

		$this->assertSame(
			[
				$post_1 => 0,
				$post_3 => 1,
				$post_4 => 2,
				$post_2 => 3,
			],
			$this->get_item_index_map()
		);
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_run_jobs_does_not_reindex_the_last_item(): void {
		$this->boot();
		add_filter( 'static_sitemap_posts_orderby', fn() => 'modified' );

		$post_1 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_2 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$post_3 = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->set_post_modified( $post_1, '2020-01-01 00:00:00' );
		$this->set_post_modified( $post_2, '2020-01-02 00:00:00' );
		$this->set_post_modified( $post_3, '2020-01-03 00:00:00' );

		$this->provider->index_objects( [ 'post' ] );

		$this->set_post_modified( $post_3, '2020-01-09 00:00:00' );
		$item = $this->item_store->get_one_by_object_id( $post_3 );
		$this->job_store->insert_job( Job::reindex_item( $item ) );

		$this->provider->run_jobs( [ 'post' ] );

		$this->assertSame(
			[
				$post_1 => 0,
				$post_2 => 1,
				$post_3 => 2,
			],
			$this->get_item_index_map()
		);
		$this->assertSame( 3, (int) $this->get_post_sitemap()->item_count );
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_run_jobs_executes_queued_add_item_job_and_clears_jobs(): void {
		$this->boot();
		static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$sitemap = $this->sitemap_store->get_by_object_type( 'post', 'post' );
		$this->job_store->insert_job( Job::add_item( $sitemap->id, $post_id ) );

		$this->provider->run_jobs( [ 'post' ] );

		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->assertNotNull( $item );
		$this->assertSame( 1, $item->item_index );
		$this->assertSame( 0, $this->count_jobs() );
		$this->assertSame( 2, (int) $this->get_post_sitemap()->item_count );
		$this->assertSame( Sitemap::STATUS_INDEXED, $this->get_post_sitemap()->status );
	}

	public function test_run_jobs_refuses_when_sitemap_is_indexing(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );

		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->job_store->insert_job( Job::remove_item( $item ) );

		$sitemap         = $this->sitemap_store->get_by_object_type( 'post', 'post' );
		$sitemap->status = Sitemap::STATUS_INDEXING;
		$this->sitemap_store->update_sitemap( $sitemap );

		$this->provider->run_jobs( [ 'post' ] );

		$this->assertSame( 1, $this->count_jobs() );
		$this->assertSame( Sitemap::STATUS_INDEXING, $this->get_post_sitemap()->status );
		$this->assertNotNull( $this->item_store->get_one_by_object_id( $post_id ) );
	}

	public function test_run_jobs_treats_stale_updating_status_as_indexed(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );

		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->job_store->insert_job( Job::remove_item( $item ) );

		$sitemap         = $this->sitemap_store->get_by_object_type( 'post', 'post' );
		$sitemap->status = Sitemap::STATUS_UPDATING;
		$this->sitemap_store->update_sitemap( $sitemap );

		$this->provider->run_jobs( [ 'post' ] );

		$this->assertSame( 0, $this->count_jobs() );
		$this->assertSame( Sitemap::STATUS_INDEXED, $this->get_post_sitemap()->status );
		$this->assertNull( $this->item_store->get_one_by_object_id( $post_id ) );
	}
}
