<?php
/**
 * PaginatorTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Pagination
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Pagination;

use AchttienVijftien\Plugin\StaticXMLSitemap\Pagination\Paginator;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class PaginatorTest
 */
class PaginatorTest extends TestCase {

	public function test_get_total_returns_item_count(): void {
		$paginator = $this->make_paginator( [ 'item_count' => 2500 ], 1000 );

		$this->assertSame( 2500, $paginator->get_total() );
	}

	/**
	 * @dataProvider last_page_provider
	 */
	public function test_get_last_page( int $item_count, int $page_size, int $expected ): void {
		$paginator = $this->make_paginator( [ 'item_count' => $item_count ], $page_size );

		$this->assertSame( $expected, $paginator->get_last_page() );
	}

	/**
	 * @return array<string, array{0: int, 1: int, 2: int}>
	 */
	public static function last_page_provider(): array {
		return [
			'exact multiple'   => [ 1000, 1000, 1 ],
			'one over'         => [ 1001, 1000, 2 ],
			'one under'        => [ 999, 1000, 1 ],
			'two full pages'   => [ 2000, 1000, 2 ],
			'partial last'     => [ 2500, 1000, 3 ],
			'empty'            => [ 0, 1000, 0 ],
			'zero page size'   => [ 500, 0, 1 ],
		];
	}

	public function test_get_pages_lists_every_page(): void {
		$paginator = $this->make_paginator( [ 'item_count' => 2500 ], 1000 );

		$this->assertSame( [ 1, 2, 3 ], $paginator->get_pages() );
	}

	public function test_get_pages_single_page(): void {
		$paginator = $this->make_paginator( [ 'item_count' => 10 ], 1000 );

		$this->assertSame( [ 1 ], $paginator->get_pages() );
	}

	public function test_get_url_for_post_first_page(): void {
		$paginator = $this->make_paginator( [ 'object_type' => 'post', 'object_subtype' => 'page' ], 1000 );

		$this->assertSame( home_url( 'page-sitemap.xml' ), $paginator->get_url( 1 ) );
	}

	public function test_get_url_for_post_later_page_appends_number(): void {
		$paginator = $this->make_paginator( [ 'object_type' => 'post', 'object_subtype' => 'page' ], 1000 );

		$this->assertSame( home_url( 'page-sitemap3.xml' ), $paginator->get_url( 3 ) );
	}

	public function test_get_url_for_user_uses_author_slug(): void {
		$paginator = $this->make_paginator( [ 'object_type' => 'user', 'object_subtype' => null ], 1000 );

		$this->assertSame( home_url( 'author-sitemap.xml' ), $paginator->get_url( 1 ) );
	}

	public function test_get_url_for_term_uses_subtype_slug(): void {
		$paginator = $this->make_paginator( [ 'object_type' => 'term', 'object_subtype' => 'category' ], 1000 );

		$this->assertSame( home_url( 'category-sitemap.xml' ), $paginator->get_url( 1 ) );
	}

	public function test_get_url_for_term_without_subtype_falls_back_to_object_type(): void {
		$paginator = $this->make_paginator( [ 'object_type' => 'term', 'object_subtype' => null ], 1000 );

		$this->assertSame( home_url( 'term-sitemap.xml' ), $paginator->get_url( 1 ) );
	}

	public function test_get_url_returns_null_for_empty_slug(): void {
		$paginator = $this->make_paginator( [ 'object_type' => '', 'object_subtype' => null ], 1000 );

		$this->assertNull( $paginator->get_url( 1 ) );
	}

	private function make_paginator( array $overrides, int $page_size ): Paginator {
		$sitemap = new Sitemap(
			array_merge(
				[
					'id'              => 1,
					'object_type'     => 'post',
					'object_subtype'  => 'page',
					'item_count'      => 0,
					'last_item_index' => 0,
				],
				$overrides
			)
		);

		return new Paginator(
			$sitemap,
			$this->createMock( ItemStoreInterface::class ),
			$page_size,
			Paginator::ORDER_ASCENDING
		);
	}
}
