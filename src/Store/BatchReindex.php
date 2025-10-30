<?php
/**
 * BatchReindex
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;

/**
 * Class BatchReindex
 */
class BatchReindex {

	private Sitemap $sitemap;
	private SitemapStore $sitemap_store;
	private ItemStoreInterface $item_store;

	private int $item_count;

	/**
	 * @var SitemapItemInterface[]
	 */
	private array $insert = [];
	/**
	 * @var SitemapItemInterface[]
	 */
	private array $remove = [];
	private Logger $logger;
	private array $offsets;
	private bool $reindex_all = false;

	public function __construct( Sitemap $sitemap, SitemapStore $sitemap_store, ItemStoreInterface $item_store, Logger $logger ) {
		$this->item_store    = $item_store;
		$this->sitemap_store = $sitemap_store;
		$this->sitemap       = $sitemap;
		$this->logger        = $logger;

		$this->item_count = $sitemap->item_count;
	}

	public function reindex( ...$items ): self {
		$this->insert( ...$items );
		$this->remove( ...$items );

		return $this;
	}

	public function insert( ...$items ): self {
		$this->item_count += count( $items );
		array_push( $this->insert, ...$items );

		return $this;
	}

	public function remove( ...$items ): self {
		$this->item_count -= count( $items );
		array_push( $this->remove, ...$items );

		return $this;
	}

	public function reindex_sitemap(): self {
		$this->reindex_all = true;

		return $this;
	}

	public function commit(): bool {
		if ( $this->reindex_all ) {
			return $this->reindex_all();
		}

		return $this->reindex_items();
	}

	private function reindex_all(): bool {
		$exclude = array_map( fn( $item ) => $item->get_id(), $this->remove );

		foreach ( $this->insert as $insert ) {
			if ( ! $insert->exists() ) {
				$this->item_store->insert_item( $insert );
			}
		}

		if ( false === $this->item_store->recalculate_index( $this->sitemap, $exclude ) ) {
			return false;
		}

		$delete_removed = $this->item_store->delete_query(
			[
				'sitemap_id' => $this->sitemap->id,
				'item_index' => null,
			],
		);

		return false !== $delete_removed;
	}

	private function reindex_items(): bool {
		global $wpdb;

		$logger = $this->logger->for_source( __METHOD__ );

		$this->item_store->sort_by_object( $this->insert );
		$this->item_store->sort_by_item_index( $this->remove );

		$result = $this->item_store->update_query(
			[ 'next_item_index' => 'item_index' ],
			[ 'sitemap_id' => $this->sitemap->id ],
			[ 'next_item_index' => '%i' ],
		);

		if ( false === $result ) {
			$logger->warning(
				"Error updating next_item_index for $this->sitemap: $wpdb->last_error"
			);

			return false;
		}

		foreach ( $this->remove as $item ) {
			$item->set_next_item_index( null );
			$this->item_store->update_item( $item );
		}

		$inserts_by_index = [];
		foreach ( $this->insert as $item ) {
			$index = $this->item_store->get_index_for_item( $item, $this->sitemap, 'next_item_index' );
			if ( null === $index ) {
				continue;
			}
			$inserts_by_index[ $index ][] = $item;
		}

		$remove_indexes = array_filter( array_map( fn( $item ) => $item->item_index, $this->remove ) );

		$inserts = array_map( 'count', $inserts_by_index );
		$removes = array_combine(
			$remove_indexes,
			array_fill( 0, count( $remove_indexes ), 1 )
		);

		$offset_indexes = array_unique( array_merge( array_keys( $inserts ), array_keys( $removes ) ) );

		sort( $offset_indexes, SORT_NUMERIC );

		$offsets     = [];
		$prev_offset = 0;

		foreach ( $offset_indexes as $index ) {
			$offsets[ $index ] = $prev_offset
				+ ( $inserts[ $index ] ?? 0 )
				- ( $removes[ $index ] ?? 0 );

			$prev_offset = $offsets[ $index ];
		}

		$this->offsets = $this->merge_duplicate_offsets( $offsets );

		$offset_indexes = array_keys( $this->offsets );

		for ( $i = 0; $i < count( $offset_indexes ); $i++ ) {
			$index_offset = $offsets[ $offset_indexes[ $i ] ];
			if ( 0 === $index_offset ) {
				continue;
			}

			$min_index = $offset_indexes[ $i ];
			$max_index = null;

			if ( isset( $offset_indexes[ $i + 1 ] ) ) {
				$max_index = $offset_indexes[ $i + 1 ] - 1;
			}

			$where_clause = $max_index ? 'next_item_index BETWEEN %d AND %d' : 'next_item_index >= %d';
			$where_data   = array_filter( [ $min_index, $max_index ] );

			$this->item_store->offset_next_index(
				$index_offset,
				$this->sitemap->id,
				[ $where_clause, $where_data ],
			);

			$logger->debug(
				'offset_next_index('
				. json_encode(
					[
						'sitemap' => $this->sitemap->id,
						'offset'  => $index_offset,
						'where'   => [ $where_clause, $where_data ],
					]
				)
				. ')'
			);
		}

		foreach ( $inserts_by_index as $index => $items ) {
			$insert_index = $index + $this->get_offset_for_index( $index - 1 );

			foreach ( $items as $item ) {
				if ( ! $item->exists() ) {
					$item = $this->item_store->insert_item( $item );
				}

				if ( ! $item ) {
					continue;
				}

				$item->set_next_item_index( $insert_index++ );
				$this->item_store->update_item( $item );
			}
		}

		$result = $this->item_store->commit_next_index( $this->sitemap->id );

		if ( false === $result ) {
			$logger->warning(
				"Error updating item_index for $this->sitemap: $wpdb->last_error"
			);

			$this->item_store->clear_next_index( $this->sitemap->id );

			return false;
		}

		$this->item_store->update_sitemap_stats( $this->sitemap );
		$this->sitemap_store->update_sitemap( $this->sitemap );

		$this->item_store->delete_query(
			[
				'sitemap_id' => $this->sitemap->id,
				'item_index' => null,
			],
		);

		return true;
	}

	private function merge_duplicate_offsets( array $offsets ): array {
		$unique_offsets = [];
		$prev_offset    = null;

		foreach ( $offsets as $index => $offset ) {
			if ( $offset !== $prev_offset ) {
				$unique_offsets[ $index ] = $offset;
			}
			$prev_offset = $offset;
		}

		return $unique_offsets;
	}

	private function get_offset_for_index( $index ) {
		$offset_for_index = 0;

		foreach ( $this->offsets as $offset_index => $offset ) {
			if ( $offset_index > $index ) {
				break;
			}
			$offset_for_index = $offset;
		}

		return $offset_for_index;
	}
}
