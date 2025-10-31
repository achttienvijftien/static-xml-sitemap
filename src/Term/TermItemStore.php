<?php
/**
 * TermItemStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class TermItemStore
 *
 * @implements ItemStoreInterface<TermItem>
 */
class TermItemStore implements ItemStoreInterface {

	use StoreTrait, ItemStoreTrait;

	public function __construct( int $page_size ) {
		global $wpdb;

		$this->table            = "{$wpdb->prefix}sitemap_terms";
		$this->object_table     = $wpdb->term_taxonomy;
		$this->object_id_column = 'term_taxonomy_id';
		$this->field_types      = [
			'term_taxonomy_id'        => '%d',
			'sitemap_id'              => '%d',
			'url'                     => '%s',
			'last_modified'           => '%s',
			'last_modified_object_id' => '%d',
			'item_index'              => '%d',
			'next_item_index'         => '%d',
		];
		$this->class            = TermItem::class;
		$this->page_size        = $page_size;
	}

	protected function get_orderby(): string {
		return apply_filters( 'static_sitemap_terms_orderby', 'term_id' );
	}

	public function get_index_after_item( SitemapItemInterface $item, string $field = 'item_index' ): ?int {
		global $wpdb;

		if ( ! $item instanceof TermItem ) {
			return null;
		}

		$term = $item->get_object();

		if ( ! $term ) {
			return null;
		}

		$orderby = apply_filters( 'static_sitemap_terms_orderby', 'term_id' );

		$terms_query = ( new Query( $term->taxonomy ) )
			->set_after( $orderby, $item, $term->ID )
			->set_orderby( $orderby )
			->get_query_clauses();

		$terms_from    = $terms_query['from'];
		$terms_joins   = $terms_query['joins'] ?? '';
		$terms_where   = isset( $terms_query['where'] ) ? ' AND ' . $terms_query['where'] : '';
		$terms_orderby = $terms_query['orderby'] ?? '';

		$target_item_index = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT si.%i 
				 FROM $this->table si
				 JOIN $terms_from ON si.term_taxonomy_id = tt.term_taxonomy_id
				 $terms_joins
				 WHERE si.sitemap_id = %d 
				 AND si.%i IS NOT NULL
				 $terms_where
				 ORDER BY $terms_orderby
				 LIMIT 1",
				$field,
				$item->sitemap_id,
				$field,
				$term->term_modified_gmt,
				$term->ID
			)
		);

		return null !== $target_item_index ? (int) $target_item_index : null;
	}

	public function sort_by_object( array &$items ): array {
		if ( ! array_all( $items, fn( $item ) => $item instanceof TermItem ) ) {
			return $items;
		}

		usort( $items, [ TermItem::class, 'compare_objects' ] );

		return $items;
	}

	public function get( int $id ): ?TermItem {
		$item = $this->get_by_id( $id );

		return $item ? new TermItem( $item ) : null;
	}

	public function get_last_modified( Sitemap $sitemap ): ?TermItem {
		global $wpdb;

		$last_modified = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table " .
				"WHERE sitemap_id = %d AND last_modified IS NOT NULL " .
				"ORDER BY last_modified, last_modified_object_id DESC " .
				"LIMIT 1",
				$sitemap->id
			)
		);

		return $last_modified ? new TermItem( $last_modified ) : null;
	}

	public function get_object_query( string $object_subtype = null ): Query {
		return new Query( $object_subtype );
	}

	public function paginate( Sitemap $sitemap ): Paginator {
		$order = Paginator::ORDER_ASCENDING;
		$order = apply_filters( 'static_sitemap_terms_pagination_order', $order );

		return new Paginator( $sitemap, $this, $this->page_size, $order );
	}
}
