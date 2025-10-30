<?php
/**
 *
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Indexer\AbstractIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;

/**
 * Class Indexer
 */
class Indexer extends AbstractIndexer {

	use WithLockTrait;

	private array $excluded_post_ids = [];
	private array $post_statuses = [];

	public function get_post_statuses( string $object_subtype ): array {
		if ( ! isset( $this->post_statuses[ $object_subtype ] ) ) {
			$this->post_statuses[ $object_subtype ]
				= apply_filters( 'static_sitemap_post_statuses', [ 'publish' ], $object_subtype );
		}

		return $this->post_statuses[ $object_subtype ];
	}

	public function get_excluded_post_ids( string $object_subtype ): ?array {
		if ( ! isset( $this->excluded_post_ids[ $object_subtype ] ) ) {
			$this->excluded_post_ids[ $object_subtype ]
				= apply_filters( 'static_sitemap_excluded_posts', [], $object_subtype );
		}

		return $this->excluded_post_ids[ $object_subtype ];
	}

	protected function get_sitemap_items(
		int $count,
		string $last_indexed_value = null,
		int $last_indexed_id = null,
		string $object_subtype = null
	) {
		global $wpdb;

		$logger = $this->logger->for_source( __METHOD__ );

		if ( null === $object_subtype ) {
			return [];
		}

		$post_statuses = $this->get_post_statuses( $object_subtype );

		if ( empty( $post_statuses ) ) {
			$logger->warning( "No post statuses indexable for post type $object_subtype" );

			return [];
		}

		$orderby = $this->get_orderby();
		$fields  = array_unique( [ 'id', $orderby ] );

		$query = ( new Query( $object_subtype ) )
			->set_fields( $fields )
			->set_indexable( true )
			->set_post_status( $post_statuses )
			->set_orderby( $orderby )
			->set_limit( $count );

		$excluded_post_ids = $this->get_excluded_post_ids( $object_subtype );

		if ( $excluded_post_ids ) {
			$query->set_exclude( $excluded_post_ids );
		}

		if ( $last_indexed_value && $last_indexed_id ) {
			$query->set_after( $orderby, $last_indexed_value, $last_indexed_id );
		}

		return $wpdb->get_results( $query->get_query() );
	}

	protected function get_orderby(): string {
		return apply_filters( 'static_sitemap_posts_orderby', 'id' );
	}

	protected function before_index( int $sitemap_id ): void {
		do_action( 'static_sitemap_index_posts', $sitemap_id );
	}

	protected function after_index( int $sitemap_id, int $total_items_inserted ): void {
		do_action( 'static_sitemap_indexed_posts', $sitemap_id, $total_items_inserted );
	}
}
