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

	public const ORDER_ASCENDING  = 'ASC';
	public const ORDER_DESCENDING = 'DESC';

	private int $page_size;
	private Sitemap $sitemap;
	private string $order;
	/**
	 * @var ItemStoreInterface<T>
	 */
	private ItemStoreInterface $item_store;

	public function __construct(
		Sitemap $sitemap,
		ItemStoreInterface $item_store,
		int $page_size,
		string $order
	) {
		$this->sitemap    = $sitemap;
		$this->page_size  = $page_size;
		$this->item_store = $item_store;
		$this->order      = $order;
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

	public function get_last_modified( int $page ): ?string {
		global $wpdb;

		$item_order = $this->item_store->get_orderby();

		if ( 'modified' === $item_order ) {
			// Easy case: item index reflects modified order.
			$last_modified = $this->item_store->get_by_item_index(
				$this->sitemap,
				$this->is_order_desc()
					? $this->get_first_item_index( $page )
					: $this->get_last_item_index( $page )
			);

			return $last_modified ? $last_modified->get_modified() : null;
		}

		// Otherwise: find max modified date in page window.
		$item_index_range = [
			$this->get_first_item_index( $page ),
			$this->get_last_item_index( $page ),
		];

		if ( ! isset( $item_index_range[0], $item_index_range[1] ) ) {
			return null;
		}

		sort( $item_index_range, SORT_NUMERIC );

		$query = $this->item_store->get_object_query( $this->sitemap->object_subtype )
			->set_fields( [ 'modified' ] )
			->set_sitemap( $this->sitemap->id )
			->set_item_index( 'BETWEEN', ...$item_index_range )
			->set_hide_empty( false )
			->set_orderby( 'modified' )
			->set_order( 'DESC' )
			->set_limit( 1 )
			->get_query();

		return $wpdb->get_var( $query );
	}

	private function get_first_item_index( int $page ): ?int {
		$offset = ( $page - 1 ) * $this->page_size;

		if ( $this->is_order_desc() ) {
			$item_index = $this->sitemap->last_item_index - $offset;

			return $item_index < 0 ? null : $item_index;
		}

		return $offset > $this->sitemap->last_item_index ? null : $offset;
	}

	private function get_last_item_index( int $page ): ?int {
		$first_item_index = $this->get_first_item_index( $page );

		if ( null === $first_item_index ) {
			return null;
		}

		if ( $this->is_order_desc() ) {
			return max( 0, $first_item_index - $this->page_size + 1 );
		}

		return min( $first_item_index + $this->page_size - 1, $this->sitemap->last_item_index );
	}

	private function is_order_desc(): bool {
		return self::ORDER_DESCENDING === $this->order;
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
		$compare = $this->is_order_desc() ? '<=' : '>=';
		$offset  = $this->get_first_item_index( $page );

		if ( null === $offset ) {
			return [];
		}

		return $this->item_store->where_item_index_compare(
			$this->sitemap->get_id(),
			$compare,
			$offset,
			$this->page_size,
			$this->order
		);
	}

}
