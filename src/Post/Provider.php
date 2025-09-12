<?php
/**
 * Provider
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobRunner;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\BatchReindex;

/**
 * Class Provider
 */
class Provider {

	use WithLockTrait;

	private SitemapStore $sitemap_store;
	private PostItemStore $post_item_store;
	private JobStore $job_store;
	private Logger $logger;
	private int $page_size;

	public function __construct( SitemapStore $sitemap_store, PostItemStore $post_item_store, JobStore $job_store, Logger $logger, int $page_size ) {
		$this->sitemap_store   = $sitemap_store;
		$this->post_item_store = $post_item_store;
		$this->job_store       = $job_store;
		$this->logger          = $logger;
		$this->page_size       = $page_size;
	}

	public function process_invalidations( int $post_id, int $invalidations ): void {
		$this->log_invalidations( $post_id, $invalidations );

		$post               = get_post( $post_id );
		$items              = $this->post_item_store->find_by_object_id( $post_id );
		$current_sitemap    = null;
		$current_sitemap_id = null;
		$current_item       = null;
		$post_deleted       = $invalidations & Watcher::POST_DELETED;
		$post_excluded      = ! $this->is_indexable( $post );

		if ( $post && $this->post_type_has_sitemap( $post ) ) {
			$current_sitemap    = $this->sitemap_store->get_by_object_type( 'post', $post->post_type );
			$current_sitemap_id = $current_sitemap ? $current_sitemap->id : null;
		}

		// First, keep only the sitemap post that belongs to the sitemap $post should be in (if any).
		foreach ( $items as $item ) {
			if ( $current_sitemap_id === $item->sitemap_id ) {
				$current_item = $item;
			}

			if ( $post_deleted || $post_excluded || ! $post || $current_item !== $item ) {
				$this->job_store->insert_job( Job::remove_item( $item ) );
			}
		}

		if ( $post_deleted || $post_excluded || ! $post || ! $current_sitemap ) {
			return;
		}

		// There is no item for this post in the current sitemap. Can happen when post type changes.
		if ( ! $current_item ) {
			$this->add_to_sitemap( $post );

			return;
		}

		// Permalink updates can be processed immediately.
		if ( $invalidations & Watcher::POST_PERMALINK_UPDATED ) {
			$this->update_item_url( $current_item );
		}

		if ( $invalidations & Watcher::POST_MODIFIED_UPDATED
			&& $current_item->post_id !== $current_sitemap->last_object_id
		) {
			$this->job_store->insert_job( Job::reindex_item( $current_item ) );
		}
	}

	private function log_invalidations( int $post_id, int $invalidations ) {
		$logger = $this->logger->for_source( __METHOD__ );

		$message = "Post $post_id:";

		if ( $invalidations & Watcher::POST_DELETED ) {
			$message .= ' POST_DELETED';
		}

		if ( $invalidations & Watcher::POST_PERMALINK_UPDATED ) {
			$message .= ' POST_PERMALINK_UPDATED';
		}

		if ( $invalidations & Watcher::POST_MODIFIED_UPDATED ) {
			$message .= ' POST_MODIFIED_UPDATED';
		}

		if ( $invalidations & Watcher::POST_TYPE_UPDATED ) {
			$message .= ' POST_TYPE_UPDATED';
		}

		if ( $invalidations & Watcher::POST_STATUS_UPDATED ) {
			$message .= ' POST_STATUS_UPDATED';
		}

		if ( $invalidations & Watcher::POST_META_UPDATED ) {
			$message .= ' POST_META_UPDATED';
		}

		$logger->debug( $message );
	}

	private function is_indexable( \WP_Post $post ) {
		$indexable = in_array( $post->post_status, [ 'publish', 'inherit' ], true );

		return apply_filters( 'static_sitemap_post_indexable', $indexable, $post );
	}

	public function post_type_has_sitemap( \WP_Post $post ): bool {
		return in_array( $post->post_type, $this->get_post_types(), true );
	}

	public function get_post_types(): array {
		$post_types = get_post_types( [ 'public' => true ] );
		$post_types = array_filter( $post_types, 'is_post_type_viewable' );

		return apply_filters( 'static_sitemap_post_types', $post_types );
	}

	public function add_to_sitemap( \WP_Post $post ): void {
		$logger = $this->logger->for_source( __METHOD__ );

		$logger->debug( "Adding $post->ID to sitemap" );

		$item = $this->get_item_for_post( $post );

		if ( ! $item || $item->exists() ) {
			return;
		}

		$appended = $this->with_lock(
			Sitemap::get_lock( $item->sitemap_id )->set_wait( 0 ),
			fn() => $this->append_to_sitemap( $item, $item->sitemap_id ),
		);

		if ( is_wp_error( $appended ) ) {
			$logger->error( "Could not append item $item: {$appended->get_error_message()}" );

			return;
		}

		if ( ! $appended ) {
			$logger->debug( "Failed to append item, queue add $post->ID to sitemap" );
			$this->job_store->insert_job( Job::add_item( $item->sitemap_id, $post->ID ) );

			return;
		}

		$logger->debug( "Appended $post->ID to sitemap" );
	}

	/**
	 * @param \WP_Post|int $post
	 *
	 * @return PostItem|null
	 */
	public function get_item_for_post( $post ): ?PostItem {
		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post );
		}

		if ( ! $post ) {
			return null;
		}

		if ( ! $this->is_indexable( $post ) ) {
			return null;
		}

		$sitemap = $this->sitemap_store->get_by_object_type( 'post', $post->post_type );

		if ( ! $sitemap ) {
			return null;
		}

		if ( $this->post_item_store->exists( $sitemap, $post->ID ) ) {
			return $this->post_item_store->get( $post->ID );
		}

		return PostItem::for_post( $post, $sitemap );
	}

	private function append_to_sitemap( PostItem $item, int $sitemap_id ) {
		$sitemap = $this->sitemap_store->get( $sitemap_id );

		if ( ! $sitemap ) {
			return new \WP_Error( 'sitemap_not_found', "Sitemap with id $sitemap_id not found" );
		}

		if ( $sitemap->is_updating() ) {
			return false;
		}

		$last_item = $sitemap->last_object_id
			? new PostItem( [ 'post_id' => $sitemap->last_object_id ] )
			: null;

		if ( $last_item && PostItem::compare( $item, $last_item ) < 0 ) {
			return false;
		}

		if ( ! $this->post_item_store->insert_item( $item ) ) {
			return new \WP_Error( 'insert_item_failed', "Could not insert $item" );
		}

		$sitemap->append( $item );

		if ( ! $this->post_item_store->update_item( $item ) ) {
			return new \WP_Error( 'update_item_failed', "Could not update $item" );
		}
		if ( ! $this->sitemap_store->update_sitemap( $sitemap ) ) {
			return new \WP_Error( 'update_sitemap_failed', "Could not update $sitemap" );
		}

		return true;
	}

	private function update_item_url( PostItem $item ) {
		$url = get_permalink( $item->post_id );
		$url = apply_filters( 'static_sitemap_post_url', $url, $item->get_object() );

		$item->url = $url;
		$this->post_item_store->update_item( $item );
	}

	public function run_jobs( ?array $post_types = null ): void {
		$logger = $this->logger->for_source( __METHOD__ );

		$sitemaps = $this->sitemap_store->find_by_object_type( 'post' );

		foreach ( $sitemaps as $sitemap ) {
			if ( $post_types && ! in_array( $sitemap->object_subtype, $post_types, true ) ) {
				continue;
			}

			$jobs_run = $this->with_lock(
				Sitemap::get_lock( $sitemap->id )->set_wait( 0 ),
				fn() => $this->run_jobs_for_sitemap( $sitemap->id )
			);

			if ( is_wp_error( $jobs_run ) ) {
				$logger->error( "Error while running jobs for sitemap $sitemap: {$jobs_run->get_error_message()}" );
				continue;
			}

			if ( null === $jobs_run ) {
				$logger->info( "Could not acquire lock to run jobs for $sitemap" );
				continue;
			}

			$logger->info( "Ran $jobs_run jobs for $sitemap" );
		}
	}

	private function run_jobs_for_sitemap( int $sitemap_id ) {
		$logger  = $this->logger->for_source( __METHOD__ );
		$sitemap = $this->sitemap_store->get( $sitemap_id );

		if ( ! $sitemap ) {
			return new \WP_Error( 'sitemap_not_found', "Sitemap with id $sitemap_id not found" );
		}

		if ( $sitemap->is_updating() ) {
			$logger->warning( "$sitemap is already being updated. Skipping" );

			return 0;
		}

		$runner = new JobRunner( $this->job_store, $this->post_item_store, $sitemap, $this->logger );

		$jobs_claimed = $runner->claim_jobs();

		if ( is_wp_error( $jobs_claimed ) ) {
			return new \WP_Error(
				'claim_jobs_failed',
				"Failed to claim jobs for $sitemap: {$jobs_claimed->get_error_message()}"
			);
		}

		if ( $jobs_claimed < 1 ) {
			$logger->info( "No jobs claimed for $sitemap" );

			return 0;
		}

		$logger->info( "Running jobs for sitemap $sitemap->id" );

		try {
			$sitemap->status = Sitemap::STATUS_UPDATING;
			$this->sitemap_store->update_sitemap( $sitemap );

			$inserted_items = [];

			[
				Job::ADD_ITEM        => $add_objects,
				Job::REMOVE_ITEM     => $remove_items,
				Job::REINDEX_ITEM    => $reindex_items,
				Job::REINDEX_SITEMAP => $reindex_sitemap,
			] = $runner->get_deduplicated_jobs();

			if ( $add_objects ) {
				$inserted_items = $this->insert_items_for_posts( $add_objects );
			}

			$logger->info( "Batch reindexing $sitemap->id" );
			if ( $reindex_sitemap ) {
				$logger->info( "Recalculating full $sitemap index" );
			}

			$batch_reindex = $this->get_batch_reindex( $sitemap )
				->insert( ...$inserted_items )
				->remove( ...$remove_items )
				->reindex( ...$reindex_items );

			if ( $reindex_sitemap ) {
				$batch_reindex->reindex_sitemap();
			}

			return $batch_reindex->commit() ? $jobs_claimed : 0;
		} finally {
			$runner->delete_jobs()->release_claim();
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}
	}

	private function insert_items_for_posts( array $object_ids ): array {
		$logger   = $this->logger->for_source( __METHOD__ );
		$inserted = [];

		foreach ( $object_ids as $object_id ) {
			$item = $this->get_item_for_post( $object_id );

			if ( ! $item || $item->exists() ) {
				continue;
			}

			$item = $this->post_item_store->insert_item( $item );

			if ( ! $item ) {
				$logger->error( "Could not insert item $item" );
				continue;
			}

			$inserted[] = $item;
		}

		return $inserted;
	}

	private function get_batch_reindex( Sitemap $sitemap ): BatchReindex {
		return new BatchReindex(
			$sitemap,
			$this->sitemap_store,
			$this->post_item_store,
			$this->logger
		);
	}

	public function create_index( array $post_types = [], bool $create_sitemap = true, bool $force_recreate = false ) {
		$indexable_post_types = $this->get_post_types();

		if ( empty( $post_types ) ) {
			$post_types = $indexable_post_types;
		}

		return $this->get_indexer()->create_index( $post_types, $create_sitemap, $force_recreate );
	}

	private function get_indexer(): Indexer {
		return new Indexer(
			$this,
			$this->post_item_store,
			$this->sitemap_store,
			$this->logger,
			$this->page_size
		);
	}

}
