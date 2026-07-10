<?php
/**
 * SitemapRendererTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class SitemapRendererTest
 */
class SitemapRendererTest extends TestCase {

	private const SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

	/**
	 * Captures the rendered output while suppressing headers-already-sent warnings.
	 *
	 * @param callable $callback Renderer invocation.
	 *
	 * @return string
	 */
	private function capture( callable $callback ): string {
		set_error_handler(
			static function ( $errno, $errstr ) {
				return (bool) preg_match( '/headers already sent|Cannot modify header/i', $errstr );
			}
		);

		ob_start();

		try {
			$callback();
		} finally {
			$output = ob_get_clean();
			restore_error_handler();
		}

		return $output;
	}

	/**
	 * Indexes the given posts into the post sitemap and returns the sitemap.
	 *
	 * @param Container $container Container.
	 *
	 * @return Sitemap
	 */
	private function index_post_sitemap( Container $container ): Sitemap {
		/** @var SitemapProvider $provider */
		$provider = $container->get( SitemapProvider::class );
		$provider->index_objects( [ 'post' ], true );

		/** @var SitemapStore $store */
		$store = $container->get( SitemapStore::class );

		return $store->get_by_object_type( 'post', 'post' );
	}

	public function test_renders_valid_urlset_with_namespace(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 3 );
		$sitemap = $this->index_post_sitemap( $container );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 1 );
			}
		);

		$document = new \DOMDocument();
		$this->assertTrue( $document->loadXML( $xml ) );

		$element = simplexml_load_string( $xml );
		$this->assertSame( 'urlset', $element->getName() );
		$this->assertContains( self::SITEMAP_NAMESPACE, $element->getDocNamespaces() );
	}

	public function test_renders_one_url_and_loc_per_item(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 4 );
		$sitemap = $this->index_post_sitemap( $container );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 1 );
			}
		);

		$xml_element = simplexml_load_string( $xml );
		$this->assertCount( 4, $xml_element->url );

		foreach ( $xml_element->url as $url ) {
			$this->assertNotEmpty( (string) $url->loc );
		}
	}

	public function test_lastmod_is_present_and_w3c_formatted(): void {
		$container = $this->create_container();
		$post_id   = self::factory()->post->create();
		$sitemap   = $this->index_post_sitemap( $container );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 1 );
			}
		);

		$xml_element = simplexml_load_string( $xml );
		$lastmod     = (string) $xml_element->url[0]->lastmod;

		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
			$lastmod
		);

		$modified_gmt = get_post( $post_id )->post_modified_gmt;
		$expected     = date_create_immutable_from_format(
			'Y-m-d H:i:s',
			$modified_gmt,
			timezone_open( 'UTC' )
		)->format( \DATE_W3C );

		$this->assertSame( $expected, $lastmod );
	}

	public function test_escapes_special_characters_in_urls(): void {
		add_filter(
			'static_sitemap_post_url',
			static fn() => home_url( '/products?a=1&b=2&x=<y>' )
		);

		$container = $this->create_container();
		self::factory()->post->create();
		$sitemap = $this->index_post_sitemap( $container );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 1 );
			}
		);

		$this->assertStringContainsString( '&amp;', $xml );
		$this->assertStringContainsString( '&lt;y&gt;', $xml );
		$this->assertStringNotContainsString( '<y>', $xml );

		$xml_element = simplexml_load_string( $xml );
		$this->assertSame(
			home_url( '/products?a=1&b=2&x=<y>' ),
			(string) $xml_element->url[0]->loc
		);
	}

	public function test_empty_page_sets_404_and_outputs_nothing(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$sitemap = $this->index_post_sitemap( $container );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 99 );
			}
		);

		$this->assertSame( '', $xml );
		$this->assertTrue( is_404() );
	}

	public function test_unknown_object_type_sets_404(): void {
		$container = $this->create_container();
		$sitemap   = Sitemap::for_object_type( 'bogus' );

		/** @var SitemapRenderer $renderer */
		$renderer = $container->get( SitemapRenderer::class );
		$xml      = $this->capture(
			static function () use ( $renderer, $sitemap ) {
				$renderer->render( $sitemap, 1 );
			}
		);

		$this->assertSame( '', $xml );
		$this->assertTrue( is_404() );
	}
}
