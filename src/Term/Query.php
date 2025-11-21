<?php
/**
 * Query
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Query\AbstractObjectQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Query\ObjectQueryInterface;

/**
 * Class Query
 */
class Query extends AbstractObjectQuery implements ObjectQueryInterface {

	public string $taxonomy;
	public bool $hide_empty = true;
	public bool $hierarchical = false;

	private TermCache $cache;

	public function __construct( string $taxonomy ) {
		$this->taxonomy = $taxonomy;
		$this->cache    = new TermCache();
	}

	protected function get_clauses(): array {
		global $wpdb;

		$fields = $this->fields ?? [ 'id', 'row_number' ];

		$where = [
			'taxonomy' => $wpdb->prepare( 'tt.taxonomy = %s', $this->taxonomy ),
		];

		$joins = [];

		if ( $this->exclude ) {
			$exclude = array_map( 'intval', $this->exclude );

			$where['exclude'] = 'tt.term_id NOT IN (' . implode( ', ', $exclude ) . ')';
		}

		$where_after = $this->get_where_after();
		if ( $where_after ) {
			$where['after'] = $where_after;
		}

		if ( $this->sitemap ) {
			$joins['sitemap_items'] = "JOIN {$wpdb->prefix}sitemap_terms si" .
				' ON tt.term_taxonomy_id = si.term_taxonomy_id';
			$where['sitemap']       = $wpdb->prepare(
				'si.sitemap_id = %d',
				$this->sitemap
			);
		}

		$hierarchical = $this->hierarchical && is_taxonomy_hierarchical( $this->taxonomy );

		if ( true === $this->indexable && $this->hide_empty && ! $hierarchical ) {
			$where['hide_empty'] = 'tt.count > 0';
		}

		if ( true === $this->indexable && $this->hide_empty && $hierarchical ) {
			$terms = $this->cache->get_hierarchical_term_count( $this->taxonomy );
			$terms = array_filter( $terms, fn( $term ) => $term['count'] > 0 );

			// SQL IN needs at least one value so use one that will never match anything.
			$not_empty_term_ids = [ 0 ];

			if ( count( $terms ) > 0 ) {
				$not_empty_term_ids = array_map( 'intval', array_column( $terms, 'term_id' ) );
			}

			$where['hide_empty'] = 'tt.term_id IN (' . implode( ', ', $not_empty_term_ids ) . ')';
		}

		$clauses = [
			'fields'  => $fields,
			'where'   => $where,
			'join'    => $joins,
			'orderby' => $this->get_orderby(),
			'limits'  => $this->limit,
		];

		return apply_filters( 'static_sitemap_terms_clauses', $clauses, $this );
	}

	protected function get_field( string $field ): ?string {
		switch ( $field ) {
			case 'id':
				$column = 'tt.term_taxonomy_id';
				break;
			case 'term_id':
				$column = 'tt.term_id';
				break;
			default:
				$column = null;
		}

		return apply_filters( 'static_sitemap_terms_query_field', $column, $field );
	}

	protected function get_table(): string {
		global $wpdb;

		return "$wpdb->term_taxonomy tt";
	}

	public function set_hide_empty( bool $hide_empty ): Query {
		$this->hide_empty = $hide_empty;

		return $this;
	}

	public function set_hierarchical( bool $hierarchical ): Query {
		$this->hierarchical = $hierarchical;

		return $this;
	}

}
