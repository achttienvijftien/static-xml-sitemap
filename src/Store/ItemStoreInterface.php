<?php
/**
 * ItemStoreInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;

/**
 * @template T of SitemapItemInterface
 */
interface ItemStoreInterface {

	/**
	 * @phpstan-param T            $item
	 *
	 * @param SitemapItemInterface $item
	 *
	 * @return false|int
	 */
	public function update_item( SitemapItemInterface $item );

	/**
	 * @phpstan-param T $item
	 *
	 * @phpstan-return T|false
	 */
	public function insert_item( SitemapItemInterface $item );

	/**
	 * @phpstan-param T[] $items
	 *
	 * @param array       $items
	 *
	 * @phpstan-return T[]
	 */
	public function sort_by_object( array &$items ): array;

	/**
	 * @phpstan-param T[] $objects
	 *
	 * @param array       $objects
	 *
	 * @phpstan-return T[]
	 */
	public function sort_by_item_index( array &$objects ): array;

	/**
	 * Returns the item_index for object.
	 *
	 * @phpstan-param  T           $item
	 *
	 * @param SitemapItemInterface $item
	 * @param Sitemap              $sitemap
	 * @param string               $field
	 *
	 * @return int|null
	 */
	public function get_index_for_item( SitemapItemInterface $item, Sitemap $sitemap, string $field = 'item_index' ): ?int;

	/**
	 * Find Sitemap objects by its object id.
	 *
	 * @param int $object_id
	 *
	 * @phpstan-return T[]
	 * @return null|SitemapItemInterface
	 */
	public function find_by_object_id( int $object_id );

	public function update_query( array $data, $where = null, $format = null, $where_format = null );

	public function query( string $query, array $prepare = null );

	public function delete_query( ?array $where = null, $where_format = null );

	/**
	 * @param Sitemap $sitemap
	 *
	 * @phpstan-return T
	 * @return SitemapItemInterface|null
	 */
	public function get_last_modified_object( Sitemap $sitemap );

	/**
	 * @param Sitemap $sitemap
	 *
	 * @phpstan-return Paginator<T>
	 * @return Paginator
	 */
	public function paginate( Sitemap $sitemap ): Paginator;

	/**
	 * @phpstan-return T|null
	 */
	public function get_by_item_index( Sitemap $sitemap, int $item_index ): ?SitemapItemInterface;

	/**
	 * @param int $item_index
	 * @param int $limit
	 *
	 * @phpstan-return T[]
	 * @return array
	 */
	public function where_item_index_lte( int $item_index, int $limit ): array;

	/**
	 * @param Sitemap $sitemap
	 *
	 * @return int|bool
	 */
	public function recalculate_index( Sitemap $sitemap );

	public function update_next_index( int $offset, int $sitemap_id, $where = null );

	public function update_index( int $offset, int $sitemap_id, $where = null, $field = 'item_index' );
}
