<?php
/**
 * PostItemStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

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

	public function get_index_for_item( SitemapItemInterface $item, Sitemap $sitemap, string $field = 'item_index' ): ?int {
		global $wpdb;

		if ( ! $item instanceof PostItem ) {
			return null;
		}

		$post = $item->get_object();

		if ( ! $post ) {
			return null;
		}

		$target_item_index = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT si.%i 
				 FROM $this->table si
				 JOIN $wpdb->posts p ON si.post_id = p.ID
				 WHERE si.sitemap_id = %d 
				 AND si.%i IS NOT NULL
				 AND (p.post_modified_gmt, p.ID) > (%s, %d)
				 ORDER BY p.post_modified_gmt, p.ID
				 LIMIT 1",
				$sitemap->id,
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

		usort( $items, [ PostItem::class, 'compare' ] );

		return $items;
	}

	public function get( int $id ): ?PostItem {
		global $wpdb;

		$item = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE id = %d", $id )
		);

		return $item ? new PostItem( $item ) : null;
	}

}
