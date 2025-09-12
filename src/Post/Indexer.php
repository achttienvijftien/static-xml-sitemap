<?php
/**
 *
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;

/**
 * Class Indexer
 */
class Indexer {

	use WithLockTrait;

	private int $page_size;
	private Provider $post_sitemaps;
	private PostItemStore $post_item_store;
	private SitemapStore $sitemap_store;
	private ?Lock $lock = null;
	private Logger $logger;

	public function __construct(
		Provider $post_sitemaps,
		PostItemStore $post_item_store,
		SitemapStore $sitemap_store,
		Logger $logger,
		int $page_size
	) {
		$this->post_sitemaps   = $post_sitemaps;
		$this->post_item_store = $post_item_store;
		$this->sitemap_store   = $sitemap_store;
		$this->logger          = $logger;
		$this->page_size       = $page_size;
	}

	public function create_index( array $post_types = [], bool $create_sitemap = true, bool $force_recreate = false ) {
		$logger = $this->logger->for_source( __METHOD__ );

		foreach ( $post_types as $post_type ) {
			$logger->info( "Creating index for post type $post_type" );

			$sitemap = $this->sitemap_store->get_by_object_type( 'post', $post_type );
			if ( ! $sitemap && $create_sitemap ) {
				$sitemap = $this->create_sitemap( $post_type );
			}

			if ( ! $sitemap && $create_sitemap ) {
				return new \WP_Error( 'insert_sitemap_failed', "Could not insert sitemap for post type $post_type" );
			}

			if ( ! $sitemap ) {
				return new \WP_Error( 'sitemap_not_found', "Sitemap not found for post type $post_type" );
			}

			$this->lock = Sitemap::get_lock( $sitemap );

			add_action( 'shutdown', [ $this, 'handle_unexpected_shutdown' ] );

			$item_count = $this->with_lock(
				$this->lock,
				fn() => $this->index_sitemap( $sitemap->id, $force_recreate )
			);

			$this->lock = null;

			if ( is_wp_error( $item_count ) ) {
				$logger->warning( $item_count->get_error_message() );
				continue;
			}

			if ( null === $item_count ) {
				$logger->warning( "Could not lock sitemap for post type $post_type" );
				continue;
			}

			$logger->info( "Inserted $item_count items in total in sitemap for post type $post_type" );
		}

		return true;
	}

	private function create_sitemap( string $post_type ): ?Sitemap {
		$sitemap = new Sitemap(
			[
				'object_type'    => 'post',
				'object_subtype' => $post_type,
				'item_count'     => 0,
				'status'         => Sitemap::STATUS_UNINDEXED,
			]
		);

		$sitemap = $this->sitemap_store->insert_sitemap( $sitemap );

		return $sitemap ?: null;
	}

	private function index_sitemap( int $sitemap_id, bool $force_recreate = false ) {
		global $wpdb;

		$sitemap = $this->sitemap_store->get( $sitemap_id );

		$logger = $this->logger->for_source( __METHOD__ );

		if ( $sitemap->status === Sitemap::STATUS_INDEXED && ! $force_recreate ) {
			return new \WP_Error( 'already_indexed', "$sitemap index already created" );
		}

		$post_type = $sitemap->object_subtype;

		if ( $force_recreate ) {
			$sitemap->item_count      = 0;
			$sitemap->last_modified   = null;
			$sitemap->last_object_id  = null;
			$sitemap->last_item_index = null;

			$this->post_item_store->delete_query( [ 'sitemap_id' => $sitemap->id ] );
		}

		$sitemap->status = Sitemap::STATUS_INDEXING;
		$this->sitemap_store->update_sitemap( $sitemap );

		$item_index           = $sitemap->last_item_index ?? 0;
		$last_modified_after  = $sitemap->last_modified;
		$last_object_id       = $sitemap->last_object_id;
		$total_items_inserted = 0;
		$error                = false;

		do {
			$items = $this->get_sitemap_items(
				$post_type,
				$this->page_size,
				$item_index,
				$last_modified_after,
				$last_object_id
			);

			if ( false === $items ) {
				$logger->warning( "Error getting sitemap items for post type $post_type: $wpdb->last_error" );
				$error = true;
				break;
			}

			$items_inserted = 0;

			foreach ( $items as $item_data ) {
				$last_modified_after = $item_data->post_modified_gmt;
				$last_object_id      = $item_data->ID;

				$item = $this->post_sitemaps->get_item_for_post( $item_data->ID );

				if ( ! $item ) {
					continue;
				}

				$item->item_index = $item_data->item_index;

				if ( ! $item->exists() && ! $this->post_item_store->insert_item( $item ) ) {
					$logger->warning( "Error inserting sitemap item for post $item_data->ID: $wpdb->last_error" );
					$error = true;
					break 2;
				}

				$items_inserted++;
				$item_index++;
				$sitemap->last_modified   = $item_data->post_modified_gmt;
				$sitemap->last_object_id  = $item_data->ID;
				$sitemap->last_item_index = $item_data->item_index;
				$sitemap->item_count++;
				$this->sitemap_store->update_sitemap( $sitemap );
			}

			if ( $items_inserted > 0 ) {
				$logger->info( "Inserted $items_inserted items in sitemap for post type $post_type" );
			}

			$total_items_inserted += $items_inserted;
		} while ( count( $items ) > 0 );

		if ( ! $error ) {
			$sitemap->status = Sitemap::STATUS_INDEXED;
			$this->sitemap_store->update_sitemap( $sitemap );
		}

		return $total_items_inserted;
	}

	private function get_sitemap_items(
		string $post_type,
		int $count,
		int $item_index_offset,
		string $last_modified_after = null,
		int $last_object_id = null
	) {
		global $wpdb;

		$logger = $this->logger->for_source( __METHOD__ );

		$excluded_post_ids = apply_filters( 'static_sitemap_excluded_posts', [], $post_type );
		$post_statuses     = apply_filters( 'static_sitemap_post_statuses', [ 'publish' ], $post_type );

		if ( empty( $post_statuses ) ) {
			$logger->warning( "No post statuses indexable for post type $post_type" );

			return [];
		}

		$post_statuses     = array_map( 'esc_sql', $post_statuses );
		$excluded_post_ids = array_map( 'esc_sql', $excluded_post_ids );

		$where = $wpdb->prepare(
			"p.post_status IN ('" . implode( "', '", $post_statuses ) . "')
				AND p.post_type = %s
				AND p.post_password = ''
				AND p.post_date != '0000-00-00 00:00:00'",
			$post_type
		);

		if ( $excluded_post_ids ) {
			$where .= ' AND p.ID NOT IN (' . implode( ', ', $excluded_post_ids ) . ')';
		}

		if ( $last_modified_after && $last_object_id ) {
			$where .= $wpdb->prepare(
				' AND (p.post_modified_gmt, p.ID) > (%s, %d)',
				$last_modified_after,
				$last_object_id
			);
		}

		$where = apply_filters( 'static_sitemap_posts_where', "($where) ", $post_type, $post_statuses );
		$joins = apply_filters( 'static_sitemap_posts_joins', '', $post_type, $post_statuses );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_modified_gmt, ROW_NUMBER() OVER (ORDER BY p.post_modified_gmt, p.ID) - 1 + %d AS item_index
					FROM $wpdb->posts p USE INDEX (type_status_date)
					WHERE $where
					$joins
					ORDER BY p.post_modified_gmt, p.ID
					LIMIT %d",
				$item_index_offset,
				$count,
			)
		);
	}

	public function handle_unexpected_shutdown(): void {
		if ( $this->lock ) {
			$this->lock->release();
		}
	}

}
