<?php
/**
 * AbstractObjectQuery
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Query
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Query;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;

/**
 * Class AbstractObjectQuery
 */
abstract class AbstractObjectQuery implements ObjectQueryInterface {

	public ?array $after = null;
	public ?array $fields = null;
	public ?int $limit = null;
	public ?array $exclude = null;
	public ?bool $indexable = null;
	public ?string $orderby = null;
	public string $order = 'ASC';
	public ?int $sitemap = null;

	abstract protected function get_clauses(): array;

	/**
	 * Set the "after" cursor for keyset pagination.
	 *
	 * @param string $field The field name to use for ordering (e.g., 'modified', 'name')
	 * @param mixed  $value The value or item containing field to compare against (e.g., date, string)
	 * @param int    $id The object ID to use as tiebreaker
	 *
	 * @return self
	 */
	public function set_after( string $field, $value, int $id ): self {
		if ( $value instanceof SitemapItemInterface ) {
			$value = $value->get_field( $field );
		}

		$this->after = [
			'field' => $field,
			'value' => $value,
			'id'    => $id,
		];

		return $this;
	}

	public function set_orderby( ?string $orderby ): self {
		$this->orderby = $orderby;

		return $this;
	}

	public function set_order( string $order ): self {
		$this->order = $order;

		return $this;
	}

	public function set_fields( array $fields ): self {
		$this->fields = $fields;

		return $this;
	}

	public function set_limit( int $limit ): self {
		$this->limit = $limit;

		return $this;
	}

	public function set_exclude( ?array $exclude ): self {
		$this->exclude = $exclude;

		return $this;
	}

	public function set_indexable( bool $indexable ): self {
		$this->indexable = $indexable;

		return $this;
	}

	public function set_sitemap( int $sitemap_id ): self {
		$this->sitemap = $sitemap_id;

		return $this;
	}

	public function get_query(): string {
		$clauses = $this->get_query_clauses();

		$query = "SELECT ${clauses['select']}\n";
		$query .= "FROM ${clauses['from']}\n";

		if ( ! empty( $clauses['join'] ) ) {
			$query .= "${clauses['join']}\n";
		}

		if ( ! empty( $clauses['where'] ) ) {
			$query .= "WHERE {$clauses['where']}\n";
		}

		if ( ! empty( $clauses['orderby'] ) ) {
			$query .= "ORDER BY ${clauses['orderby']}\n";
		}

		if ( ! empty( $clauses['limit'] ) ) {
			$query .= "LIMIT ${clauses['limit']}\n";
		}

		return $query;
	}

	public function get_query_clauses(): array {
		$clauses = $this->get_clauses();

		$clauses = array_merge(
			[
				'fields'  => [],
				'where'   => [],
				'join'    => [],
				'orderby' => [],
				'limits'  => null,
			],
			$clauses
		);

		$select = $this->get_select( $clauses['fields'] );
		if ( in_array( 'row_number', $clauses['fields'], true ) ) {
			$select[] = $this->get_row_number_sql( $clauses['orderby'] );
		}

		$select  = implode( ', ', $select );
		$where   = $this->get_where_clause( $clauses['where'] );
		$join    = implode( "\n", $clauses['join'] );
		$orderby = implode( ', ', $clauses['orderby'] );
		$limit   = $clauses['limits'];

		$query = [
			'select' => $select,
			'from'   => $this->get_table(),
		];

		if ( ! empty( $where ) ) {
			$query['where'] = $where;
		}

		if ( ! empty( $join ) ) {
			$query['join'] = $join;
		}

		if ( ! empty( $orderby ) ) {
			$query['orderby'] = $orderby;
			if ( 'DESC' === strtoupper( $this->order ) ) {
				$query['orderby'] .= ' DESC';
			}
		}

		if ( ! empty( $limit ) ) {
			$query['limit'] = $limit;
		}

		return $query;
	}

	protected function get_where_clause( array $clauses ): string {
		if ( empty( $clauses ) ) {
			return '';
		}

		$relation = 'AND';

		$flattened_clauses = [];

		foreach ( $clauses as $key => $value ) {
			if ( 'relation' === $key && 'OR' === $value ) {
				$relation = $value;
				continue;
			}

			$clause = is_array( $value ) ? $this->get_where_clause( $value ) : $value;

			if ( ! empty( $clause ) ) {
				$flattened_clauses[] = $clause;
			}
		}

		if ( count( $flattened_clauses ) === 0 ) {
			return '';
		}

		return '(' . implode( " $relation ", $flattened_clauses ) . ')';
	}

	abstract protected function get_field( string $field ): ?string;

	protected function get_row_number_sql( array $orderby ): ?string {
		if ( ! $orderby ) {
			return null;
		}

		$orderby = implode( ', ', $orderby );
		if ( 'DESC' === strtoupper( $this->order ) ) {
			$orderby .= ' DESC';
		}

		return "ROW_NUMBER() OVER (ORDER BY $orderby) AS `row_number`";
	}

	protected function get_select( array $fields ): array {
		global $wpdb;

		$select = array_reduce(
			$fields,
			function ( array $select, string $field ) use ( $wpdb ) {
				$column = $this->get_field( $field );

				if ( $column ) {
					$select[ $field ] = $wpdb->prepare( "$column as %i", $field );
				}

				return $select;
			},
			[]
		);

		return array_filter( $select );
	}

	abstract protected function get_table(): string;

	protected function get_orderby(): array {
		$orderby = [];

		if ( $this->orderby ) {
			$fields  = array_unique( [ $this->orderby, 'id' ] );
			$orderby = array_map( fn( $field ) => $this->get_field( $field ), $fields );
			$orderby = array_filter( $orderby );
		}

		return $orderby;
	}

	protected function get_where_after(): ?string {
		global $wpdb;

		$after = $this->after;

		if ( ! isset( $after['id'], $after['value'], $after['field'] ) ) {
			return null;
		}

		$field = $this->get_field( $after['field'] );
		$id    = $this->get_field( 'id' );

		if ( ! $field || ! $id ) {
			return null;
		}

		$format = is_int( $after['value'] ) ? '%d' : '%s';

		return $wpdb->prepare( "($field, $id) > ($format, %d)", $after['value'], $after['id'] );
	}
}
