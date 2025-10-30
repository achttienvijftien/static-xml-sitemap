<?php
/**
 * Query
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Query\AbstractObjectQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Query\ObjectQueryInterface;

/**
 * Class Query
 */
class Query extends AbstractObjectQuery implements ObjectQueryInterface {

	protected function get_clauses(): array {
		global $wpdb;

		$fields = $this->fields ?? [ 'id', 'modified', 'row_number' ];

		$where = [];
		$joins = [];

		if ( $this->exclude ) {
			$exclude = array_map( 'intval', $this->exclude );

			$where['exclude'] = 'u.ID NOT IN (' . implode( ', ', $exclude ) . ')';
		}

		$where_after = $this->get_where_after();
		if ( $where_after ) {
			$where['after'] = $where_after;
		}

		if ( $this->sitemap ) {
			$joins['sitemap_items'] = "JOIN {$wpdb->prefix}sitemap_users si" .
				' ON u.ID = si.user_id';
			$where['sitemap']       = $wpdb->prepare(
				'si.sitemap_id = %d',
				$this->sitemap
			);
		}

		$clauses = [
			'fields'  => $fields,
			'where'   => $where,
			'join'    => $joins,
			'orderby' => $this->get_orderby(),
			'limits'  => $this->limit,
		];

		return apply_filters( 'static_sitemap_authors_clauses', $clauses, $this );
	}

	protected function get_field( string $field ): ?string {
		switch ( $field ) {
			case 'id':
				$column = 'u.ID';
				break;
			case 'user_login':
				$column = 'u.user_login';
				break;
			case 'modified':
				$column = 'u.user_registered';
				break;
			default:
				$column = null;
		}

		return apply_filters( 'static_sitemap_authors_query_field', $column, $field );
	}

	protected function get_table(): string {
		global $wpdb;

		return "$wpdb->users u";
	}
}
