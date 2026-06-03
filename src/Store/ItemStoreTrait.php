<?php
/**
 * ItemStoreTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItem;

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

	/**
	 * Returns the one by object id.
	 *
	 * @param int $object_id
	 *
	 * @return PostItem|UserItem|TermItem|null
	 */
	public function get_one_by_object_id( int $object_id ) {
		global $wpdb;

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $this->table WHERE %i = %d LIMIT 1",
				$this->object_id_column,
				$object_id
			)
		);

		$item = $items[0] ?? null;

		return $item ? new $this->class( $item ) : null;
	}

	public function sort_by_item_index( array &$objects ): array {
		usort( $objects, fn( $a, $b ) => $a->item_index <=> $b->item_index );

		return $objects;
	}

	public function offset_next_index( int $offset, int $sitemap_id, $where = null ) {
		return $this->offset_index( $offset, $sitemap_id, $where, 'next_item_index' );
	}

	public function offset_index( int $offset, int $sitemap_id, $where = null, $field = 'item_index' ) {
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

	public function clear_next_index( int $sitemap_id ): bool {
		return (bool) $this->query(
			"UPDATE $this->table
				SET next_item_index = NULL
				WHERE sitemap_id = %d",
			[ $sitemap_id ]
		);
	}

	public function commit_next_index( int $sitemap_id ): bool {
		return (bool) $this->query(
			"UPDATE $this->table " .
			'SET item_index = next_item_index, next_item_index = NULL ' .
			'WHERE sitemap_id = %d',
			[ $sitemap_id ]
		);
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

	public function get_last_item( Sitemap $sitemap ): ?SitemapItemInterface {
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

	public function where_item_index_compare(
		int $sitemap_id,
		string $compare,
		int $item_index,
		int $limit,
		string $order = 'ASC'
	): array {
		global $wpdb;

		if ( ! in_array( $compare, [ '<=', '>=' ], true ) ) {
			return [];
		}

		$order = strtoupper( $order );

		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'ASC';
		}

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $this->table si " .
				"WHERE si.sitemap_id = %d AND si.item_index $compare %d " .
				"ORDER BY si.item_index $order " .
				'LIMIT %d',
				$sitemap_id,
				$item_index,
				$limit
			)
		);

		if ( ! $items ) {
			return [];
		}

		return array_map( fn( $item ) => new $this->class( $item ), $items );
	}

	protected function get_by_id( int $item_id ): ?\stdClass {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE id = %d", $item_id )
		);

		return $item instanceof \stdClass ? $item : null;
	}

	public function recalculate_index( Sitemap $sitemap, array $exclude = [] ): ?Sitemap {
		$object_query = ( $this->get_object_query( $sitemap->object_subtype ) )
			->set_fields( [ 'id', 'row_number' ] )
			->set_sitemap( $sitemap->id )
			->set_orderby( $this->get_orderby() )
			->get_query();

		$this->clear_next_index( $sitemap->id );

		$id_not_in = '';
		$exclude   = array_map( 'intval', $exclude );
		if ( $exclude ) {
			$id_not_in = ' AND si.id NOT IN (' . implode( ',', $exclude ) . ')';
		}

		$update = $this->query(
			"UPDATE %i as si, ($object_query) AS o" .
			'SET si.next_item_index = o.`row_number` - 1' .
			'WHERE si.%i = o.id' . $id_not_in,
			[ $this->table, $this->object_id_column ]
		);

		$this->commit_next_index( $sitemap->id );

		return $update ? $this->update_sitemap_stats( $sitemap ) : null;
	}

	public function update_sitemap_stats( Sitemap $sitemap ): Sitemap {
		$last_item     = $this->get_last_item( $sitemap );
		$last_modified = $last_item;

		if ( 'modified' !== $this->get_orderby() ) {
			$last_modified = $this->get_last_modified( $sitemap );
		}

		if ( ! $last_item ) {
			return $sitemap;
		}

		$sitemap->last_modified   = $last_modified ? $last_modified->get_modified() : null;
		$sitemap->last_object_id  = $last_item->get_object_id();
		$sitemap->last_item_index = $last_item->get_item_index();
		$sitemap->item_count      = $last_item->get_item_index() + 1;

		return $sitemap;
	}

}
