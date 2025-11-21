<?php
/**
 * AbstractProvider
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Provider
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Provider;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobRunner;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\BatchReindex;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ObjectType;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\WatcherInterface;

abstract class AbstractProvider implements ProviderInterface {

	use WithLockTrait;

	/**
	 * @var class-string
	 */
	protected string $object_class;
	protected WatcherInterface $watcher;
	protected SitemapStore $sitemap_store;
	protected ItemStoreInterface $item_store;
	protected JobStore $job_store;
	protected Logger $logger;
	protected int $page_size;

	public function __construct(
		WatcherInterface $watcher,
		SitemapStore $sitemap_store,
		ItemStoreInterface $item_store,
		JobStore $job_store,
		Logger $logger,
		int $page_size
	) {
		$this->watcher       = $watcher;
		$this->sitemap_store = $sitemap_store;
		$this->item_store    = $item_store;
		$this->job_store     = $job_store;
		$this->logger        = $logger;
		$this->page_size     = $page_size;
	}

	abstract public function handles_object( $object ): bool;

	/**
	 * @param \WP_User|\WP_Post|\WP_Term $object
	 *
	 * @return void
	 */
	public function add_to_sitemap( $object ): void {
		$logger = $this->logger->for_source( __METHOD__ );

		if ( ! $this->handles_object( $object ) ) {
			$logger->warning(
				sprintf(
					'Passed object class %s does not match expected class %s',
					get_class( $object ),
					$this->object_class
				)
			);

			return;
		}

		$item = $this->get_item_for_object( $object );

		if ( ! $item || $item->exists() ) {
			return;
		}

		$object_type = ObjectType::get_type( $object );
		$object_id   = $object instanceof \WP_Term ? $object->term_taxonomy_id : $object->ID;

		$logger->debug( "Adding $object_type $object_id to sitemap" );

		$force_queue_add = apply_filters( 'static_sitemap_force_queue_add', false, $object_type );

		$appended = false;

		if ( ! $force_queue_add ) {
			$appended = $this->with_lock(
				Sitemap::get_lock( $item->sitemap_id )->set_wait( 0 ),
				fn() => $this->append_to_sitemap( $item, $item->sitemap_id ),
			);
		}

		if ( is_wp_error( $appended ) ) {
			$logger->error( "Could not append item $item: {$appended->get_error_message()}" );

			return;
		}

		if ( ! $appended ) {
			$logger->debug( "Failed to append item, queue add $object_type $object_id to sitemap" );
			$this->job_store->insert_job( Job::add_item( $item->sitemap_id, $object_id ) );

			return;
		}

		$logger->debug( "Appended $object_type $object_id to sitemap" );
	}

	protected function append_to_sitemap( SitemapItemInterface $item, int $sitemap_id ) {
		$logger = $this->logger->for_source( __METHOD__ );
		$this->sitemap_store->invalidate_cache( $sitemap_id );
		$sitemap = $this->sitemap_store->get( $sitemap_id );

		if ( ! $sitemap ) {
			return new \WP_Error( 'sitemap_not_found', "Sitemap with id $sitemap_id not found" );
		}

		if ( $sitemap->is_updating() ) {
			$logger->warning( "$sitemap has stale UPDATING status, restoring status to INDEXED" );
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}

		if ( $sitemap->last_object_id && $this->compare_objects( $item->get_object(), $sitemap->last_object_id ) < 0 ) {
			return false;
		}

		if ( ! $this->item_store->insert_item( $item ) ) {
			return new \WP_Error( 'insert_item_failed', "Could not insert $item" );
		}

		$sitemap->append( $item );

		if ( ! $this->item_store->update_item( $item ) ) {
			return new \WP_Error( 'update_item_failed', "Could not update $item" );
		}
		if ( ! $this->sitemap_store->update_sitemap( $sitemap ) ) {
			return new \WP_Error( 'update_sitemap_failed', "Could not update $sitemap" );
		}

		return true;
	}

	abstract public function is_indexable( $object );

	/**
	 * @param \WP_Post|\WP_User|\WP_Term|int $object
	 *
	 * @return PostItem|UserItem|TermItem|null
	 */
	public function get_item_for_object( $object ) {
		if ( is_int( $object ) ) {
			$object = $this->get_object_by_id( $object );
		}

		if ( ! $this->handles_object( $object ) ) {
			return null;
		}

		if ( ! $this->is_indexable( $object ) ) {
			return null;
		}

		$object_type    = ObjectType::get_type( $object );
		$object_subtype = ObjectType::get_subtype( $object );

		$sitemap = $this->sitemap_store->get_by_object_type( $object_type, $object_subtype );

		if ( ! $sitemap ) {
			return null;
		}

		if ( $this->item_store->exists( $sitemap, $object->ID ) ) {
			return $this->item_store->get_one_by_object_id( $object->ID );
		}

		if ( $object instanceof \WP_User ) {
			return UserItem::for_object( $object, $sitemap );
		}
		if ( $object instanceof \WP_Post ) {
			return PostItem::for_object( $object, $sitemap );
		}
		if ( $object instanceof \WP_Term ) {
			return TermItem::for_object( $object, $sitemap );
		}

		return null;
	}

	public function run_jobs( ?array $subtypes = null ): void {
		$logger = $this->logger->for_source( __METHOD__ );

		$sitemaps = $this->sitemap_store->find_by_object_type( $this->get_object_type() );

		foreach ( $sitemaps as $sitemap ) {
			if ( $subtypes && ! in_array( $sitemap->object_subtype, $subtypes, true ) ) {
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

	protected function run_jobs_for_sitemap( int $sitemap_id ) {
		$logger = $this->logger->for_source( __METHOD__ );
		$this->sitemap_store->invalidate_cache( $sitemap_id );
		$sitemap = $this->sitemap_store->get( $sitemap_id );

		if ( ! $sitemap ) {
			return new \WP_Error( 'sitemap_not_found', "Sitemap with id $sitemap_id not found" );
		}

		if ( $sitemap->is_updating() ) {
			$logger->warning( "$sitemap has stale UPDATING status, restoring status to INDEXED" );
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}

		$runner = new JobRunner( $this->job_store, $this->item_store, $sitemap, $this->logger );

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
				Job::ADD_ITEM             => $add_objects,
				Job::REMOVE_ITEM          => $remove_items,
				Job::REINDEX_ITEM         => $reindex_items,
				Job::REINDEX_SITEMAP      => $reindex_sitemap,
				Job::UPDATE_LAST_MODIFIED => $update_last_modified,
			] = $runner->get_deduplicated_jobs();

			if ( $add_objects ) {
				$inserted_items = $this->insert_items_for_objects( $add_objects );
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

			if ( $update_last_modified ) {
				$this->update_last_modified( $update_last_modified );
			}

			return $batch_reindex->commit() ? $jobs_claimed : 0;
		} finally {
			$runner->delete_jobs()->release_claim();
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}
	}

	protected function update_last_modified( array $items ): void {
		// Do nothing by default.
	}

	protected function insert_items_for_objects( array $object_ids ): array {
		$logger   = $this->logger->for_source( __METHOD__ );
		$inserted = [];

		foreach ( $object_ids as $object_id ) {
			$item = $this->get_item_for_object( $object_id );

			if ( ! $item || $item->exists() ) {
				continue;
			}

			$item = $this->item_store->insert_item( $item );

			if ( ! $item ) {
				$logger->error( "Could not insert item $item" );
				continue;
			}

			$inserted[] = $item;
		}

		return $inserted;
	}

	protected function get_batch_reindex( Sitemap $sitemap ): BatchReindex {
		return new BatchReindex(
			$sitemap,
			$this->sitemap_store,
			$this->item_store,
			$this->logger
		);
	}

	/**
	 * @param int $object_id
	 *
	 * @return \WP_Post|\WP_User|\WP_Term|null
	 */
	abstract protected function get_object_by_id( int $object_id );

	public function add_hooks(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$this->watcher->add_hooks();
	}

	public function process_watches( int $object_id, int $events ): void {
		$object = $this->get_object_by_id( $object_id );
		$item   = $this->item_store->get_one_by_object_id( $object_id );

		$invalidations = $this->get_invalidations( $events );

		$this->process_invalidations( $invalidations, $object, $item );
	}

	abstract protected function get_invalidations( int $events ): int;

	/**
	 * @param int                             $invalidations
	 * @param \WP_Post|\WP_User|\WP_Term|null $object
	 * @param SitemapItemInterface|null       $item
	 *
	 * @return void
	 */
	protected function process_invalidations( int $invalidations, $object, ?SitemapItemInterface $item ): void {
		if ( ! $invalidations ) {
			return;
		}

		$is_indexable = $object && $this->is_indexable( $object );

		if ( ! $item ) {
			if ( $is_indexable ) {
				$this->add_to_sitemap( $object );
			}

			return;
		}

		// Url updates can be processed immediately.
		if ( $invalidations & Invalidations::ITEM_URL ) {
			$this->update_item_url( $item );
		}

		if ( $invalidations & Invalidations::OBJECT_EXISTS
			|| $invalidations & Invalidations::IS_INDEXABLE && ! $is_indexable
		) {
			$this->job_store->insert_job( Job::remove_item( $item ) );

			return;
		}

		if ( $invalidations & Invalidations::ITEM_INDEX && $is_indexable ) {
			// Only trigger reindex if currently indexable, otherwise reindex might override remove.
			$this->job_store->insert_job( Job::reindex_item( $item ) );
		}

		if ( $invalidations & Invalidations::ITEM_LAST_MODIFIED ) {
			$this->job_store->insert_job( Job::update_last_modified( $item ) );
		}
	}
}
