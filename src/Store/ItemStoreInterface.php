<?php
/**
 * ItemStoreInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Query as PostQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Query\ObjectQueryInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Query as TermQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Query as UserQuery;

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
	 * @param \WP_Post[]|\WP_User[] $objects
	 *
	 * @phpstan-return T[]
	 */
	public function sort_by_item_index( array &$objects ): array;

	/**
	 * Returns the (next) item index of the sitemap item at which $item should be inserted.
	 *
	 * @phpstan-param  T           $item
	 *
	 * @param SitemapItemInterface $item Sitemap item.
	 * @param string               $field
	 *
	 * @return int|null
	 */
	public function get_index_for_item( SitemapItemInterface $item, string $field = 'item_index' ): ?int;

	/**
	 * Find Sitemap items by its object id.
	 *
	 * @param int $object_id
	 *
	 * @phpstan-return T[]
	 * @return SitemapItemInterface[]
	 */
	public function find_by_object_id( int $object_id ): array;

	/**
	 * Get the first sitemap item with a matching object id.
	 *
	 * @param int $object_id
	 *
	 * @phpstan-return T
	 * @return null|SitemapItemInterface
	 */
	public function get_one_by_object_id( int $object_id );

	public function update_query( array $data, $where = null, $format = null, $where_format = null );

	public function query( string $query, array $prepare = null );

	public function delete_query( ?array $where = null, $where_format = null );

	/**
	 * @param Sitemap $sitemap
	 *
	 * @phpstan-return T
	 * @return SitemapItemInterface|null
	 */
	public function get_last_item( Sitemap $sitemap );

	/**
	 * @param Sitemap $sitemap
	 *
	 * @phpstan-return T
	 * @return SitemapItemInterface|null
	 */
	public function get_last_modified( Sitemap $sitemap );

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
	 * @param int    $sitemap_id
	 * @param string $compare
	 * @param int    $item_index
	 * @param int    $limit
	 * @param string $order
	 *
	 * @return array
	 */
	public function where_item_index_compare( int $sitemap_id, string $compare, int $item_index, int $limit, string $order ): array;

	/**
	 * @param Sitemap $sitemap
	 * @param array   $exclude
	 *
	 * @return int|bool
	 */
	public function recalculate_index( Sitemap $sitemap, array $exclude = [] ): ?Sitemap;

	public function offset_next_index( int $offset, int $sitemap_id, $where = null );

	public function offset_index( int $offset, int $sitemap_id, $where = null, $field = 'item_index' );

	public function clear_next_index( int $sitemap_id ): bool;

	public function commit_next_index( int $sitemap_id ): bool;

	public function update_sitemap_stats( Sitemap $sitemap ): Sitemap;

	/**
	 * @return PostQuery|TermQuery|UserQuery
	 */
	public function get_object_query( string $object_subtype = null ): ObjectQueryInterface;
}
