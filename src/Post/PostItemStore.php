<?php
/**
 * PostItemStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class PostItemStore
 *
 * @implements ItemStoreInterface<PostItem>
 */
class PostItemStore implements ItemStoreInterface {

	use StoreTrait, ItemStoreTrait;

	public function __construct( int $page_size ) {
		global $wpdb;

		$this->table            = "{$wpdb->prefix}sitemap_posts";
		$this->object_table     = $wpdb->posts;
		$this->object_id_column = 'post_id';
		$this->field_types      = [
			'post_id'         => '%d',
			'sitemap_id'      => '%d',
			'url'             => '%s',
			'item_index'      => '%d',
			'next_item_index' => '%d',
		];
		$this->class            = PostItem::class;
		$this->page_size        = $page_size;
	}

	protected function get_orderby(): string {
		return apply_filters( 'static_sitemap_posts_orderby', 'id' );
	}

	public function get_index_for_item( SitemapItemInterface $item, Sitemap $sitemap, string $field = 'item_index' ): ?int {
		global $wpdb;

		if ( ! $item instanceof PostItem ) {
			return null;
		}

		$post = $item->get_object();

		if ( ! $post ) {
			return null;
		}

		$orderby = $this->get_orderby();

		$posts_query = ( new Query( $post->post_type ) )
			->set_after( $orderby, $item->get_field( $orderby ), $post->ID )
			->set_orderby( $orderby )
			->get_query_clauses();

		$posts_from    = $posts_query['from'];
		$posts_joins   = $posts_query['joins'] ?? '';
		$posts_where   = isset( $posts_query['where'] ) ? ' AND ' . $posts_query['where'] : '';
		$posts_orderby = $posts_query['orderby'] ?? '';

		$target_item_index = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT si.%i 
				 FROM $this->table si
				 JOIN $posts_from ON si.post_id = p.ID
				 $posts_joins
				 WHERE si.sitemap_id = %d 
				 AND si.%i IS NOT NULL
				 $posts_where
				 ORDER BY $posts_orderby
				 LIMIT 1",
				$field,
				$sitemap->id,
				$field,
				$post->post_modified_gmt,
				$post->ID
			)
		);

		return null !== $target_item_index ? (int) $target_item_index : $sitemap->item_count;
	}

	public function sort_by_object( array &$items ): array {
		if ( ! array_all( $items, fn( $item ) => $item instanceof PostItem ) ) {
			return $items;
		}

		usort( $items, [ PostItem::class, 'compare_objects' ] );

		return $items;
	}

	public function get( int $id ): ?PostItem {
		$item = $this->get_by_id( $id );

		return $item ? new PostItem( $item ) : null;
	}

	public function get_last_modified( Sitemap $sitemap ) {
		global $wpdb;

		$post_id = $wpdb->get_var(
			( new Query( $sitemap->object_subtype ) )
				->set_fields( [ 'id' ] )
				->set_orderby( 'modified' )
				->set_order( 'DESC' )
				->set_sitemap( $sitemap->id )
				->set_limit( 1 )
		);

		if ( ! $post_id ) {
			return null;
		}

		return $this->get_one_by_object_id( $post_id );
	}

	public function get_object_query( string $object_subtype = null ): Query {
		return new Query( $object_subtype );
	}

	public function paginate( Sitemap $sitemap ): Paginator {
		$order = Paginator::ORDER_ASCENDING;
		$order = apply_filters( 'static_sitemap_posts_pagination_order', $order );

		return new Paginator( $sitemap, $this, $this->page_size, $order );
	}
}
