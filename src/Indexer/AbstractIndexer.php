<?php
/**
 * AbstractIndexer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Indexer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Indexer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\DateTime;

/**
 * Class AbstractIndexer
 */
abstract class AbstractIndexer {

	use WithLockTrait;

	protected ProviderInterface $provider;
	protected ItemStoreInterface $item_store;
	protected SitemapStore $sitemap_store;
	protected ?Lock $lock = null;
	protected Logger $logger;
	protected bool $force_recreate = false;
	protected int $page_size;

	public function __construct(
		ProviderInterface $provider,
		ItemStoreInterface $item_store,
		SitemapStore $sitemap_store,
		Logger $logger,
		int $page_size
	) {
		$this->provider      = $provider;
		$this->item_store    = $item_store;
		$this->sitemap_store = $sitemap_store;
		$this->logger        = $logger;
		$this->page_size     = $page_size;
	}

	public function set_force_recreate( bool $force_recreate ): AbstractIndexer {
		$this->force_recreate = $force_recreate;

		return $this;
	}

	public function run( string $object_subtype = null ) {
		$logger = $this->logger->for_source( __METHOD__ );

		$sitemap = $this->sitemap_store->get_by_object_type(
			$this->provider->get_object_type(),
			$object_subtype
		);

		if ( ! $sitemap ) {
			$sitemap = Sitemap::for_object_type( $this->provider->get_object_type(), $object_subtype );

			if ( ! $this->sitemap_store->insert_sitemap( $sitemap ) ) {
				return new \WP_Error( 'insert_sitemap_failed', "Could not insert $sitemap" );
			}
		}

		$this->lock = Sitemap::get_lock( $sitemap );

		add_action( 'shutdown', [ $this, 'handle_unexpected_shutdown' ] );

		$item_count = $this->with_lock( $this->lock, fn() => $this->index_sitemap( $sitemap->id ) );

		$this->lock = null;

		if ( is_wp_error( $item_count ) ) {
			$logger->warning( $item_count->get_error_message() );

			return $item_count;
		}

		if ( null === $item_count ) {
			$logger->warning( "Could not lock sitemap $sitemap" );

			return new \WP_Error( 'sitemap_lock_failed', "Could not lock $sitemap" );
		}

		$logger->info( "Inserted $item_count items in total in $sitemap" );

		return $item_count;
	}

	abstract protected function get_sitemap_items(
		int $count,
		string $last_indexed_value = null,
		int $last_indexed_id = null,
		string $object_subtype = null
	);

	protected function index_sitemap( int $sitemap_id ) {
		global $wpdb;

		$this->sitemap_store->invalidate_cache( $sitemap_id );

		$sitemap = $this->sitemap_store->get( $sitemap_id );

		$logger = $this->logger->for_source( __METHOD__ );

		if ( $sitemap->status === Sitemap::STATUS_INDEXED && ! $this->force_recreate ) {
			return new \WP_Error( 'already_indexed', "$sitemap index already created" );
		}

		$this->before_index( $sitemap_id );

		$object_type    = $sitemap->object_type;
		$object_subtype = $sitemap->object_subtype;

		if ( $this->force_recreate ) {
			$sitemap->reset();

			$this->item_store->delete_query( [ 'sitemap_id' => $sitemap->id ] );
		}

		$sitemap->status = Sitemap::STATUS_INDEXING;
		$this->sitemap_store->update_sitemap( $sitemap );

		$item_index           = $sitemap->last_item_index ?? 0;
		$last_indexed_value   = $sitemap->last_indexed_value;
		$last_indexed_id      = $sitemap->last_indexed_id;
		$total_items_inserted = 0;
		$error                = false;

		$orderby = $this->get_orderby();

		do {
			$items = $this->get_sitemap_items(
				$this->page_size,
				$last_indexed_value,
				$last_indexed_id,
				$object_subtype,
			);

			if ( ! is_array( $items ) ) {
				$logger->warning( "Error getting sitemap items for {$sitemap->get_description()}: $wpdb->last_error" );
				$error = true;
				break;
			}

			$items_inserted = 0;

			foreach ( $items as $item_data ) {
				$object_id       = (int) $item_data->id;
				$object_modified = $item_data->modified ?? null;

				$last_indexed_value = $item_data->$orderby ?? null;
				$last_indexed_id    = $object_id;

				$item = $this->provider->get_item_for_object( $object_id );

				if ( ! $item ) {
					continue;
				}

				if ( $item->exists() ) {
					$logger->warning( "Not updating existing item for $object_type $object_id" );
					continue;
				}

				$item->item_index = $item_index;

				if ( ! $this->item_store->insert_item( $item ) ) {
					$logger->warning(
						"Error inserting sitemap item for $object_type $object_id: $wpdb->last_error"
					);
					$error = true;
					break 2;
				}

				$items_inserted++;

				$sitemap->last_modified      = DateTime::to_mysql( $object_modified );
				$sitemap->last_object_id     = $object_id;
				$sitemap->last_item_index    = $item_index;
				$sitemap->last_indexed_value = $last_indexed_value;
				$sitemap->last_indexed_id    = $last_indexed_id;
				$sitemap->item_count++;
				$this->sitemap_store->update_sitemap( $sitemap );

				$item_index++;
			}

			$total_items_inserted += $items_inserted;
		} while ( count( $items ) > 0 );

		if ( ! $error ) {
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}

		$this->after_index( $sitemap_id, $total_items_inserted );

		return $total_items_inserted;
	}

	public function handle_unexpected_shutdown(): void {
		if ( $this->lock ) {
			$this->lock->release();
		}
	}

	abstract protected function get_orderby(): string;

	abstract protected function before_index( int $sitemap_id ): void;

	abstract protected function after_index( int $sitemap_id, int $total_items_inserted ): void;
}
