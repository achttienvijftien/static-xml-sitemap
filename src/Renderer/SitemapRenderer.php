<?php
/**
 * SitemapRenderer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Renderer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;

/**
 * Class SitemapRenderer
 */
class SitemapRenderer {

	use DateFormatterTrait;

	private PostItemStore $post_item_store;
	private int $page_size;
	private \XMLWriter $writer;

	public function __construct( PostItemStore $post_item_store, int $page_size ) {
		$this->post_item_store = $post_item_store;
		$this->page_size       = $page_size;
		$this->writer          = new \XMLWriter();
	}

	public function render( Sitemap $sitemap, int $page ) {
		global $wp_query;

		$store = $this->get_item_store( $sitemap );
		if ( $store ) {
			$items = $store->paginate( $sitemap )->get_items( $page );
		}

		if ( empty( $items ) ) {
			$wp_query->set_404();
			status_header( 404 );

			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );

		$this->writer->openMemory();
		$this->writer->startDocument( '1.0', 'UTF-8' );
		$this->writer->startElement( 'urlset' );
		$this->writer->writeAttribute( 'xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9' );

		foreach ( $items as $item ) {
			$this->writer->startElement( 'url' );
			$this->writer->writeElement( 'loc', $item->get_url() );
			$this->writer->writeElement( 'lastmod', $this->format_date( $item->get_modified() ) );
			$this->writer->endElement();
		}

		$this->writer->endElement();
		$this->writer->endDocument();
		echo $this->writer->outputMemory();
	}

	private function get_item_store( Sitemap $sitemap ): ?ItemStoreInterface {
		switch ( $sitemap->object_type ) {
			case 'post':
				return $this->post_item_store;
			case 'user':
				return $this->user_item_store;
			case 'term':
				return $this->term_item_store;
		}

		return null;
	}

}
