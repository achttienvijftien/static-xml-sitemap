<?php
/**
 * ItemStoreTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;

/**
 * Trait ItemStoreTrait
 */
trait ItemStoreTrait {

	protected string $object_table;
	protected string $object_id_column;
	/**
	 * @var class-string
	 */
	protected string $class;
	protected int $page_size = 1000;

	public function exists( $sitemap, $object ): bool {
		global $wpdb;

		$sitemap_id = is_object( $sitemap ) ? $sitemap->id : (int) $sitemap;
		$object_id  = is_object( $object ) ? $object->ID : $object;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM $this->table WHERE %i = %d AND sitemap_id = %d",
				$this->object_id_column,
				$object_id,
				$sitemap_id
			)
		);
	}

	public function insert_item( SitemapItemInterface $item ) {
		return $this->insert( $item );
	}

	public function update_item( SitemapItemInterface $item ) {
		return $this->update( $item );
	}

	public function find_by_object_id( int $object_id ): array {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $this->table WHERE %i = %d",
				$this->object_id_column,
				$object_id
			)
		);

		if ( ! $items ) {
			return [];
		}

		return array_map( fn( $item ) => new  $this->class ( $item ), $items );
	}

	public function sort_by_item_index( array &$objects ): array {
		usort( $objects, fn( $a, $b ) => $a->item_index <=> $b->item_index );

		return $objects;
	}

	public function update_next_index( int $offset, int $sitemap_id, $where = null ) {
		return $this->update_index( $offset, $sitemap_id, $where, 'next_item_index' );
	}

	public function update_index( int $offset, int $sitemap_id, $where = null, $field = 'item_index' ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"UPDATE $this->table
				SET %i = %i + %d
				WHERE sitemap_id = %d
				AND %i IS NOT NULL",
			$field,
			$field,
			$offset,
			$sitemap_id,
			$field,
		);

		if ( $where ) {
			$where        = (array) $where;
			$where_clause = $where[0];
			$where_data   = $where[1] ?? null;

			if ( $where_data ) {
				$where_clause = $wpdb->prepare( $where_clause, $where_data );
			}

			$query .= " AND $where_clause";
		}

		return $wpdb->query( $query );
	}

	/**
	 * @param array|null $where
	 * @param null       $where_format
	 *
	 * @return int|false
	 */
	public function delete_query( ?array $where = null, $where_format = null ) {
		global $wpdb;

		return $wpdb->delete( $this->table, $where, $where_format );
	}

	public function delete( SitemapItemInterface $object ) {
		global $wpdb;

		return $wpdb->delete( $this->table, [ 'id' => $object->id ] );
	}

	public function get_last_modified_object( Sitemap $sitemap ): ?SitemapItemInterface {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table si
				 WHERE si.sitemap_id = %d 
				 ORDER BY si.item_index DESC
				 LIMIT 1",
				$sitemap->id
			)
		);

		return $item ? new $this->class( $item ) : null;
	}

	public function paginate( Sitemap $sitemap ): Paginator {
		return new Paginator( $sitemap, $this, $this->page_size );
	}

	public function get_by_item_index( Sitemap $sitemap, int $item_index ): ?SitemapItemInterface {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $this->table si
				 WHERE si.sitemap_id = %d AND si.item_index = %d
				 LIMIT 1",
				$sitemap->id,
				$item_index
			)
		);

		return $item ? new $this->class( $item ) : null;
	}

	/**
	 * @param int $item_index
	 * @param int $limit
	 *
	 * @return array
	 */
	public function where_item_index_lte( int $item_index, int $limit ): array {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $this->table si
				WHERE si.item_index <= %d
				ORDER BY si.item_index DESC
				LIMIT %d",
				$item_index,
				$limit
			)
		);

		if ( ! $items ) {
			return [];
		}

		return array_map( fn( $item ) => new $this->class( $item ), $items );
	}

	/**
	 * @param Sitemap $sitemap
	 *
	 * @return int|bool
	 */
	public function recalculate_index( Sitemap $sitemap ) {
		// todo
		return 0;
	}
}
