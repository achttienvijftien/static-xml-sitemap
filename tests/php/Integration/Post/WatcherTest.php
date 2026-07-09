<?php
/**
 * WatcherTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;

/**
 * Class WatcherTest
 */
class WatcherTest extends PostTestCase {

	public function test_publishing_new_post_adds_item(): void {
		$this->boot();
		$this->provider->index_objects( [ 'post' ] );
		$this->register_watch_hooks();

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $post_id );

		$this->assertNotNull( $item );
		$this->assertSame( 0, $item->item_index );
		$this->assertSame( 1, (int) $this->get_post_sitemap()->item_count );
	}

	public function test_new_post_is_not_added_when_no_sitemap_exists(): void {
		$this->boot();
		$this->register_watch_hooks();

		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->watcher->process_events();

		$this->assertNull( $this->item_store->get_one_by_object_id( $post_id ) );
		$this->assertNull( $this->get_post_sitemap() );
	}

	public function test_draft_to_publish_transition_adds_item(): void {
		$this->boot();
		$this->provider->index_objects( [ 'post' ] );
		$this->register_watch_hooks();

		$post_id = static::factory()->post->create( [ 'post_status' => 'draft' ] );
		$this->watcher->process_events();

		$this->assertNull( $this->item_store->get_one_by_object_id( $post_id ) );

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);
		$this->watcher->process_events();

		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->assertNotNull( $item );
		$this->assertSame( 0, $item->item_index );
	}

	public function test_unpublishing_post_queues_removal(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->register_watch_hooks();

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);
		$this->watcher->process_events();

		$remove_jobs = $this->get_jobs( Job::REMOVE_ITEM );
		$this->assertCount( 1, $remove_jobs );
		$this->assertSame( $item->id, (int) $remove_jobs[0]->sitemap_item_id );
		$this->assertSame( $post_id, (int) $remove_jobs[0]->object_id );
	}

	public function test_changing_post_type_queues_removal(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->register_watch_hooks();

		wp_update_post(
			[
				'ID'        => $post_id,
				'post_type' => 'page',
			]
		);
		$this->watcher->process_events();

		$remove_jobs = $this->get_jobs( Job::REMOVE_ITEM );
		$this->assertCount( 1, $remove_jobs );
		$this->assertSame( $item->id, (int) $remove_jobs[0]->sitemap_item_id );
	}

	public function test_changing_slug_updates_url_in_place(): void {
		$this->boot();
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = static::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_name'   => 'original-slug',
			]
		);
		$this->provider->index_objects( [ 'post' ] );

		$this->assertSame( '/original-slug/', $this->item_store->get_one_by_object_id( $post_id )->url );

		$this->register_watch_hooks();

		wp_update_post(
			[
				'ID'        => $post_id,
				'post_name' => 'renamed-slug',
			]
		);
		$this->watcher->process_events();

		$this->assertSame( '/renamed-slug/', $this->item_store->get_one_by_object_id( $post_id )->url );
		$this->assertSame( 0, $this->count_jobs() );
	}

	public function test_deleting_post_queues_removal(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->register_watch_hooks();

		wp_delete_post( $post_id, true );
		$this->watcher->process_events();

		$remove_jobs = $this->get_jobs( Job::REMOVE_ITEM );
		$this->assertCount( 1, $remove_jobs );
		$this->assertSame( $item->id, (int) $remove_jobs[0]->sitemap_item_id );
	}

	public function test_updating_post_meta_does_not_invalidate_item(): void {
		$this->boot();
		$post_id = static::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->provider->index_objects( [ 'post' ] );
		$item = $this->item_store->get_one_by_object_id( $post_id );
		$this->register_watch_hooks();

		update_post_meta( $post_id, '_some_meta_key', 'value' );
		$this->watcher->process_events();

		$this->assertSame( 0, $this->count_jobs() );
		$this->assertSame( $item->item_index, $this->item_store->get_one_by_object_id( $post_id )->item_index );
	}
}
