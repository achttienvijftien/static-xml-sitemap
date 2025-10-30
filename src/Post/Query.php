<?php
/**
 * Query
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Query\AbstractObjectQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Query\ObjectQueryInterface;

/**
 * Class Query
 */
class Query extends AbstractObjectQuery implements ObjectQueryInterface {

	public string $post_type;
	public ?array $post_status = null;

	public function __construct( string $post_type ) {
		$this->post_type = $post_type;
	}

	public function set_post_status( array $post_status ): self {
		$this->post_status = $post_status;

		return $this;
	}

	protected function get_clauses(): array {
		global $wpdb;

		$fields = $this->fields ?? [ 'id', 'modified', 'row_number' ];

		$where = [
			'post_type'   => $wpdb->prepare( 'p.post_type = %s', $this->post_type ),
			'no_password' => "p.post_password = ''",
			'valid_date'  => "p.post_date != '0000-00-00 00:00:00'",
		];
		$joins = [];

		if ( $this->exclude ) {
			$exclude = array_map( 'intval', $this->exclude );

			$where['exclude'] = 'p.ID NOT IN (' . implode( ', ', $exclude ) . ')';
		}

		$where_after = $this->get_where_after();
		if ( $where_after ) {
			$where['after'] = $where_after;
		}

		if ( $this->sitemap ) {
			$joins['sitemap_items'] = "JOIN {$wpdb->prefix}sitemap_posts si" .
				' ON p.ID = si.post_id';
			$where['sitemap']       = $wpdb->prepare(
				'si.sitemap_id = %d',
				$this->sitemap
			);
		}

		if ( $this->post_status ) {
			$post_status = array_map( 'esc_sql', $this->post_status );

			$where['post_status'] = "p.post_status IN ('" . implode( "', '", $post_status ) . "')";
		}

		$clauses = [
			'fields'  => $fields,
			'where'   => $where,
			'join'    => $joins,
			'orderby' => $this->get_orderby(),
			'limits'  => $this->limit,
		];

		return apply_filters( 'static_sitemap_posts_clauses', $clauses, $this );
	}

	protected function get_field( string $field ): ?string {
		switch ( $field ) {
			case 'id':
				$column = 'p.ID';
				break;
			case 'modified':
				$column = 'p.post_modified_gmt';
				break;
			default:
				$column = null;
		}

		return apply_filters( 'static_sitemap_posts_query_field', $column, $field );
	}

	protected function get_table(): string {
		global $wpdb;

		return "$wpdb->posts p";
	}
}
