<?php
/**
 * SitemapIndexRendererTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapIndexRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class SitemapIndexRendererTest
 */
class SitemapIndexRendererTest extends TestCase {

	private const SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

	public function set_up(): void {
		parent::set_up();

		add_filter( 'static_sitemap_posts_orderby', static fn() => 'modified' );
	}

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

	private function render_index( Container $container ): string {
		/** @var SitemapIndexRenderer $renderer */
		$renderer = $container->get( SitemapIndexRenderer::class );

		return $this->capture(
			static function () use ( $renderer ) {
				$renderer->render();
			}
		);
	}

	private function index_posts( Container $container ): void {
		/** @var SitemapProvider $provider */
		$provider = $container->get( SitemapProvider::class );
		$provider->index_objects( [ 'post' ], true );
	}

	public function test_renders_sitemapindex_with_namespace(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$this->index_posts( $container );

		$xml = $this->render_index( $container );

		$document = new \DOMDocument();
		$this->assertTrue( $document->loadXML( $xml ) );

		$element = simplexml_load_string( $xml );
		$this->assertSame( 'sitemapindex', $element->getName() );
		$this->assertContains( self::SITEMAP_NAMESPACE, $element->getDocNamespaces() );
	}

	public function test_lists_indexed_sitemap_with_loc_and_lastmod(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$this->index_posts( $container );

		$xml_element = simplexml_load_string( $this->render_index( $container ) );

		$this->assertCount( 1, $xml_element->sitemap );
		$this->assertSame(
			home_url( '/post-sitemap.xml' ),
			(string) $xml_element->sitemap[0]->loc
		);
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
			(string) $xml_element->sitemap[0]->lastmod
		);
	}

	public function test_paginates_into_multiple_entries(): void {
		$container = $this->create_container()->add_parameters( [ 'page_size' => 2 ] );
		self::factory()->post->create_many( 5 );
		$this->index_posts( $container );

		$xml_element = simplexml_load_string( $this->render_index( $container ) );

		$this->assertCount( 3, $xml_element->sitemap );

		$locations = [];
		foreach ( $xml_element->sitemap as $sitemap ) {
			$locations[] = (string) $sitemap->loc;
		}

		$this->assertSame(
			[
				home_url( '/post-sitemap.xml' ),
				home_url( '/post-sitemap2.xml' ),
				home_url( '/post-sitemap3.xml' ),
			],
			$locations
		);
	}

	public function test_renders_lastmod_under_default_orderby(): void {
		remove_all_filters( 'static_sitemap_posts_orderby' );

		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$this->index_posts( $container );

		$xml_element = simplexml_load_string( $this->render_index( $container ) );

		$this->assertCount( 1, $xml_element->sitemap );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
			(string) $xml_element->sitemap[0]->lastmod
		);
	}

	public function test_excludes_non_viewable_sitemaps(): void {
		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$this->index_posts( $container );

		/** @var SitemapStore $store */
		$store = $container->get( SitemapStore::class );
		$store->insert_sitemap( Sitemap::for_object_type( 'post', 'page' ) );

		$xml_element = simplexml_load_string( $this->render_index( $container ) );

		$this->assertCount( 1, $xml_element->sitemap );
		$this->assertSame(
			home_url( '/post-sitemap.xml' ),
			(string) $xml_element->sitemap[0]->loc
		);
	}

	public function test_appends_index_content_filter(): void {
		add_filter(
			'static_sitemap_index_content',
			static fn() => '<!--sxs-index-marker-->'
		);

		$container = $this->create_container();
		self::factory()->post->create_many( 2 );
		$this->index_posts( $container );

		$xml = $this->render_index( $container );

		$this->assertStringContainsString( '<!--sxs-index-marker-->', $xml );

		$document = new \DOMDocument();
		$this->assertTrue( $document->loadXML( $xml ) );
	}
}
