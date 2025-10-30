<?php
/**
 * JobRunner
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Job;

use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;

/**
 * Class JobRunner
 */
class JobRunner {

	private JobStore $job_store;
	private ItemStoreInterface $item_store;
	private Sitemap $sitemap;
	private Logger $logger;

	private array $jobs = [];
	private bool $running = false;
	private ?string $claim_id = null;

	public function __construct( JobStore $job_store, ItemStoreInterface $item_store, Sitemap $sitemap, Logger $logger ) {
		$this->job_store  = $job_store;
		$this->item_store = $item_store;
		$this->sitemap    = $sitemap;
		$this->logger     = $logger;
	}

	/**
	 * @return int|\WP_Error
	 */
	public function claim_jobs() {
		$logger         = $this->logger->for_source( __METHOD__ );
		$this->claim_id = $this->generate_claim_id();
		$jobs_claimed   = $this->job_store->claim_jobs( $this->sitemap->id, $this->claim_id );

		if ( is_wp_error( $jobs_claimed ) ) {
			$logger->warning( $jobs_claimed->get_error_message() );

			return $jobs_claimed;
		}

		if ( $jobs_claimed < 1 ) {
			return $jobs_claimed;
		}

		$this->running = true;

		add_action( 'shutdown', [ $this, 'handle_unexpected_shutdown' ] );

		$this->jobs = $this->job_store->get_by_claim_id( $this->claim_id );

		return $jobs_claimed;
	}

	private function generate_claim_id(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * @return Job[][]
	 */
	public function get_deduplicated_jobs(): array {
		$add_item             = [];
		$remove_item          = [];
		$reindex_item         = [];
		$reindex_sitemap      = [];
		$update_last_modified = [];

		foreach ( $this->jobs as $job ) {
			if ( Job::REINDEX_SITEMAP === $job->action ) {
				$reindex_sitemap[ $job->sitemap_id ] = $job->sitemap_id;

				$reindex_item = [];
			}

			if ( Job::ADD_ITEM === $job->action ) {
				$add_item[ $job->object_id ] = $job->object_id;
			}

			if ( Job::REMOVE_ITEM === $job->action ) {
				$item = $this->item_store->get( $job->sitemap_item_id );
				if ( ! $item ) {
					continue;
				}

				$remove_item[ $item->id ] = $item;

				unset( $add_item[ $job->object_id ] );
				unset( $reindex_item[ $item->id ] );
				unset( $update_last_modified[ $item->id ] );
			}

			if ( Job::REINDEX_ITEM === $job->action ) {
				if ( $reindex_sitemap ) {
					continue;
				}

				$item = $this->item_store->get( $job->sitemap_item_id );
				if ( ! $item ) {
					continue;
				}

				$reindex_item[ $item->id ] = $item;
			}

			if ( Job::UPDATE_LAST_MODIFIED === $job->action ) {
				if ( isset( $remove_item[ $job->sitemap_item_id ] ) ) {
					continue;
				}

				$item = $this->item_store->get( $job->sitemap_item_id );
				if ( ! $item ) {
					continue;
				}

				$update_last_modified[ $item->id ] = $item;
			}
		}

		return [
			Job::ADD_ITEM             => $add_item,
			Job::REMOVE_ITEM          => $remove_item,
			Job::REINDEX_ITEM         => $reindex_item,
			Job::REINDEX_SITEMAP      => $reindex_sitemap,
			Job::UPDATE_LAST_MODIFIED => $update_last_modified,
		];
	}

	public function delete_jobs(): self {
		$this->job_store->delete_jobs( $this->jobs );

		return $this;
	}

	public function handle_unexpected_shutdown() {
		if ( $this->running && $this->claim_id ) {
			$this->release_claim();
		}
	}

	public function release_claim(): void {
		if ( $this->claim_id ) {
			$this->job_store->release_claim( $this->claim_id );
			$this->claim_id = null;
		}
		$this->running = false;
	}

}
