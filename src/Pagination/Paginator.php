<?php
/**
 * Paginator
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Pagination
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Pagination;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;

/**
 * Class Paginator
 *
 * @template T of SitemapItemInterface
 */
class Paginator {

	private int $page_size;
	private Sitemap $sitemap;
	/**
	 * @var ItemStoreInterface<T>
	 */
	private ItemStoreInterface $item_store;

	public function __construct( Sitemap $sitemap, ItemStoreInterface $item_store, int $page_size ) {
		$this->sitemap    = $sitemap;
		$this->page_size  = $page_size;
		$this->item_store = $item_store;
	}

	public function get_pages(): array {
		return range( 1, $this->get_last_page() );
	}

	public function get_last_page(): int {
		return $this->page_size > 0 ? ceil( $this->get_total() / $this->page_size ) : 1;
	}

	public function get_total(): int {
		return $this->sitemap->item_count;
	}

	public function get_url( int $page ): ?string {
		$slug = $this->get_slug();

		if ( ! $slug ) {
			return null;
		}

		$uri = $this->get_slug() . '-sitemap';

		if ( $page > 1 ) {
			$uri .= $page;
		}

		$uri .= '.xml';

		return home_url( $uri );
	}

	private function get_slug(): string {
		return $this->sitemap->object_subtype ?? $this->sitemap->object_type;
	}

	public function get_last_modified( int $page ) {
		$first_item = $this->get_first_item( $page );

		return $first_item ? $first_item->get_modified() : null;
	}

	/**
	 * @param int $page
	 *
	 * @phpstan-return T|null
	 * @return SitemapItemInterface|null
	 */
	public function get_first_item( int $page ): ?SitemapItemInterface {
		return $this->item_store->get_by_item_index(
			$this->sitemap,
			$this->sitemap->last_item_index - ( $page - 1 ) * $this->page_size
		);
	}

	/**
	 * Returns the items.
	 *
	 * @param int $page
	 *
	 * @phpstan-return T[]
	 * @return SitemapItemInterface[]
	 */
	public function get_items( int $page ): array {
		return $this->item_store->where_item_index_lte(
			$this->sitemap->last_item_index - ( $page - 1 ) * $this->page_size,
			$this->page_size
		);
	}

}
