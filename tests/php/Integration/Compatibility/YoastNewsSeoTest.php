<?php
/**
 * YoastNewsSeoTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\YoastNewsSeo\YoastNewsSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class YoastNewsSeoTest
 */
class YoastNewsSeoTest extends TestCase {

	private function make_news_seo(): YoastNewsSeo {
		return $this->create_container()->get( YoastNewsSeo::class );
	}

	public function test_is_activated_returns_false_without_yoast_news(): void {
		$this->assertFalse( $this->make_news_seo()->is_activated() );
	}

	public function test_index_content_passes_through_without_filter(): void {
		$this->assertSame( 'sitemap-index', $this->make_news_seo()->index_content( 'sitemap-index' ) );
	}

	public function test_index_content_applies_wpseo_sitemap_index_filter(): void {
		add_filter( 'wpseo_sitemap_index', static fn( $content ) => $content . '-news' );

		$this->assertSame( 'index-news', $this->make_news_seo()->index_content( 'index' ) );
	}

	public function test_add_query_vars_passes_through_non_array(): void {
		$this->assertSame( 'not-an-array', $this->make_news_seo()->add_query_vars( 'not-an-array' ) );
	}

	public function test_add_query_vars_adds_xsl_query_var(): void {
		$this->assertContains( 'yoast-sitemap-xsl', $this->make_news_seo()->add_query_vars( [ 'paged' ] ) );
	}

	public function test_add_query_vars_does_not_duplicate_xsl_query_var(): void {
		$query_vars = $this->make_news_seo()->add_query_vars( [ 'yoast-sitemap-xsl' ] );

		$this->assertSame( [ 'yoast-sitemap-xsl' ], $query_vars );
	}

	public function test_request_is_noop_without_wpseo_sitemaps(): void {
		unset( $GLOBALS['wpseo_sitemaps'] );

		$this->assertNull( $this->make_news_seo()->request( null, 'news', 1 ) );
	}

	public function test_xsl_request_is_noop_without_wpseo_sitemaps(): void {
		unset( $GLOBALS['wpseo_sitemaps'] );

		$this->assertNull( $this->make_news_seo()->xsl_request( null ) );
	}
}
