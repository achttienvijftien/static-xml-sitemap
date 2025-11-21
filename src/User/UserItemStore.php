<?php
/**
 * UserItemStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class UserItemStore
 */
class UserItemStore implements ItemStoreInterface {

	use StoreTrait, ItemStoreTrait;

	public function __construct( int $page_size ) {
		global $wpdb;

		$this->table            = "{$wpdb->prefix}sitemap_users";
		$this->object_table     = $wpdb->users;
		$this->object_id_column = 'user_id';
		$this->field_types      = [
			'user_id'         => '%d',
			'sitemap_id'      => '%d',
			'url'             => '%s',
			'item_index'      => '%d',
			'next_item_index' => '%d',
		];
		$this->class            = UserItem::class;
		$this->page_size        = $page_size;
	}

	public function get_orderby(): string {
		return apply_filters( 'static_sitemap_authors_orderby', 'user_login' );
	}

	public function get( int $id ): ?UserItem {
		$item = $this->get_by_id( $id );

		return $item ? new UserItem( $item ) : null;
	}

	public function sort_by_object( array &$items ): array {
		if ( ! array_all( $items, fn( $item ) => $item instanceof UserItem ) ) {
			return $items;
		}

		usort( $items, [ UserItem::class, 'compare_objects' ] );

		return $items;
	}

	public function get_index_after_item( SitemapItemInterface $item, string $field = 'item_index' ): ?int {
		global $wpdb;

		if ( ! $item instanceof UserItem ) {
			return null;
		}

		/** @var \WP_User $user */
		$user = $item->get_object();

		if ( ! $user ) {
			return null;
		}

		$orderby = apply_filters( 'static_sitemap_authors_orderby', 'user_login' );

		$users_query = ( new Query() )
			->set_after( $orderby, $item, $user->ID )
			->set_orderby( $orderby )
			->get_query_clauses();

		$users_from    = $users_query['from'];
		$users_joins   = $users_query['join'] ?? '';
		$users_where   = isset( $users_query['where'] ) ? ' AND ' . $users_query['where'] : '';
		$users_orderby = $users_query['orderby'];

		$target_item_index = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT si.%i 
				 FROM $this->table si
				 JOIN $users_from ON si.user_id = u.ID
				 $users_joins
				 WHERE si.sitemap_id = %d 
				  AND si.%i IS NOT NULL
				  $users_where
				 ORDER BY $users_orderby
				 LIMIT 1",
				$field,
				$item->sitemap_id,
				$field
			)
		);

		return null !== $target_item_index ? (int) $target_item_index : null;
	}

	public function get_last_modified( Sitemap $sitemap ): ?UserItem {
		global $wpdb;

		$user_id = $wpdb->get_var(
			( new Query() )->set_fields( [ 'id' ] )
				->set_orderby( 'modified' )
				->set_order( 'DESC' )
				->set_sitemap( $sitemap->id )
				->set_limit( 1 )
		);

		if ( ! $user_id ) {
			return null;
		}

		return $this->get_one_by_object_id( $user_id );
	}

	public function get_object_query( string $object_subtype = null ): Query {
		return new Query();
	}

	public function paginate( Sitemap $sitemap ): Paginator {
		$order = Paginator::ORDER_ASCENDING;
		$order = apply_filters( 'static_sitemap_authors_pagination_order', $order );

		return new Paginator( $sitemap, $this, $this->page_size, $order );
	}

}
