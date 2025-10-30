<?php
/**
 * Indexer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Term;
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Indexer\AbstractIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;

/**
 * Class Indexer
 */
class Indexer extends AbstractIndexer {

	use WithLockTrait;

	private ?array $excluded_term_ids = null;

	public function get_excluded_term_ids(): ?array {
		if ( ! isset( $this->excluded_term_ids ) ) {
			$this->excluded_term_ids = apply_filters( 'static_sitemap_excluded_terms', [] );
		}

		return $this->excluded_term_ids;
	}

	protected function get_sitemap_items(
		int $count,
		string $last_indexed_value = null,
		int $last_indexed_id = null,
		string $object_subtype = null
	) {
		global $wpdb;

		if ( null === $object_subtype ) {
			return [];
		}

		$orderby = $this->get_orderby();
		$fields  = array_unique( [ 'id', $orderby ] );

		$query = ( new Query( $object_subtype ) )
			->set_fields( $fields )
			->set_indexable( true )
			->set_orderby( $orderby )
			->set_limit( $count );

		$query = apply_filters( 'static_sitemap_terms_query', $query );

		$excluded_term_ids = $this->get_excluded_term_ids();

		if ( $excluded_term_ids ) {
			$query->set_exclude( $excluded_term_ids );
		}

		if ( $last_indexed_value && $last_indexed_id ) {
			$query->set_after( $orderby, $last_indexed_value, $last_indexed_id );
		}

		return $wpdb->get_results( $query->get_query() );
	}

	protected function get_orderby(): string {
		return apply_filters( 'static_sitemap_terms_orderby', 'term_id' );
	}

	protected function before_index( int $sitemap_id ): void {
		do_action( 'static_sitemap_index_terms', $sitemap_id );
	}

	protected function after_index( int $sitemap_id, int $total_items_inserted ): void {
		do_action( 'static_sitemap_indexed_terms', $sitemap_id, $total_items_inserted );
	}

}
