<?php
/**
 * SitemapIndexRenderer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Renderer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;

/**
 * Class SitemapIndexRenderer
 */
class SitemapIndexRenderer {

	use DateFormatterTrait;

	private SitemapStore $sitemap_store;
	/**
	 * @var Sitemap[]
	 */
	private array $sitemaps;
	private \XMLWriter $writer;
	private PostItemStore $post_item_store;

	/**
	 * SitemapIndexRenderer constructor.
	 *
	 * @param SitemapStore  $sitemap_store
	 * @param PostItemStore $post_item_store
	 */
	public function __construct( SitemapStore $sitemap_store, PostItemStore $post_item_store ) {
		$this->sitemap_store   = $sitemap_store;
		$this->sitemaps        = $this->sitemap_store->get_viewable_sitemaps();
		$this->writer          = new \XMLWriter();
		$this->post_item_store = $post_item_store;
	}

	public function render(): void {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		$this->writer->openMemory();
		$this->writer->startDocument( '1.0', 'UTF-8' );
		$this->writer->startElement( 'sitemapindex' );
		$this->writer->writeAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );
		foreach ( $this->sitemaps as $sitemap ) {
			$this->render_paginated_sitemap( $sitemap );
		}
		$this->writer->endElement();
		$this->writer->endDocument();
		echo $this->writer->outputMemory();
	}

	private function render_paginated_sitemap( Sitemap $sitemap ) {
		$item_store = $this->get_sitemap_item_store( $sitemap );

		if ( ! $item_store ) {
			return;
		}

		$paginator = $item_store->paginate( $sitemap );
		foreach ( $paginator->get_pages() as $page ) {
			$url = $paginator->get_url( $page );
			if ( ! $url ) {
				continue;
			}
			$this->writer->startElement( 'sitemap' );
			$this->writer->writeElement( 'loc', $url );
			$this->writer->writeElement( 'lastmod', $this->format_date( $paginator->get_last_modified( $page ) ) );
			$this->writer->endElement();
		}
	}

	/**
	 * Returns the sitemap item store.
	 *
	 * @param Sitemap $sitemap
	 *
	 * @return ItemStoreInterface|null
	 */
	private function get_sitemap_item_store( Sitemap $sitemap ): ?ItemStoreInterface {
		switch ( $sitemap->object_type ) {
			case 'post':
				return $this->post_item_store;
			default:
				return null;
		}
	}

}
