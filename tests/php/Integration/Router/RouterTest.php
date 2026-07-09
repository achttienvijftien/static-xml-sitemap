<?php
/**
 * RouterTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Router
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Router;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Router\Router;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class RouterTest
 */
class RouterTest extends TestCase {

	private Container $container;
	private Router $router;

	public function set_up(): void {
		parent::set_up();

		$this->container = $this->create_container();
		$this->router    = $this->container->get( Router::class );
		$this->router->register_rewrites();
		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Requests the given url while suppressing headers-already-sent warnings.
	 *
	 * @param string $url Url to request.
	 *
	 * @return void
	 */
	private function request( string $url ): void {
		set_error_handler(
			static function ( $errno, $errstr ) {
				return (bool) preg_match( '/headers already sent|Cannot modify header/i', $errstr );
			}
		);

		try {
			$this->go_to( $url );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Invokes the private get_sitemap_for_type method.
	 *
	 * @param string $type Sitemap type.
	 *
	 * @return Sitemap|null
	 */
	private function resolve_type( string $type ): ?Sitemap {
		$method = new \ReflectionMethod( Router::class, 'get_sitemap_for_type' );
		$method->setAccessible( true );

		return $method->invoke( $this->router, $type );
	}

	public function test_register_rewrites_registers_tag_and_rules(): void {
		global $wp_rewrite;

		$this->assertContains( '%static-sitemap%', $wp_rewrite->rewritecode );
		$this->assertArrayHasKey( '^sitemap_index\.xml$', $wp_rewrite->extra_rules_top );
		$this->assertArrayHasKey( '^(.+)-sitemap(\d+)?\.xml$', $wp_rewrite->extra_rules_top );
	}

	public function test_index_request_parses_type(): void {
		$this->request( home_url( '/sitemap_index.xml' ) );

		$this->assertSame( 'index', get_query_var( 'static-sitemap' ) );
	}

	public function test_type_and_page_request_parses_type_and_page(): void {
		$this->request( home_url( '/post-sitemap2.xml' ) );

		$this->assertSame( 'post', get_query_var( 'static-sitemap' ) );
		$this->assertSame( 2, get_query_var( 'paged' ) );
	}

	public function test_type_without_page_parses_type(): void {
		$this->request( home_url( '/post-sitemap.xml' ) );

		$this->assertSame( 'post', get_query_var( 'static-sitemap' ) );
	}

	public function test_author_request_parses_type(): void {
		$this->request( home_url( '/author-sitemap.xml' ) );

		$this->assertSame( 'author', get_query_var( 'static-sitemap' ) );
	}

	public function test_disabled_sitemaps_result_in_404(): void {
		$this->request( home_url( '/post-sitemap.xml' ) );

		$this->assertTrue( is_404() );
	}

	public function test_unknown_type_results_in_404_when_enabled(): void {
		add_filter( 'static_sitemap_enabled', '__return_true' );

		$this->request( home_url( '/bogustype-sitemap.xml' ) );

		$this->assertSame( 'bogustype', get_query_var( 'static-sitemap' ) );
		$this->assertTrue( is_404() );
	}

	public function test_author_type_maps_to_user_sitemap(): void {
		/** @var SitemapStore $store */
		$store = $this->container->get( SitemapStore::class );
		$store->insert_sitemap( Sitemap::for_object_type( 'user' ) );

		$sitemap = $this->resolve_type( 'author' );

		$this->assertInstanceOf( Sitemap::class, $sitemap );
		$this->assertSame( 'user', $sitemap->object_type );
	}

	public function test_post_type_maps_to_post_sitemap(): void {
		self::factory()->post->create();

		/** @var SitemapProvider $provider */
		$provider = $this->container->get( SitemapProvider::class );
		$provider->index_objects( [ 'post' ], true );

		$sitemap = $this->resolve_type( 'post' );

		$this->assertInstanceOf( Sitemap::class, $sitemap );
		$this->assertSame( 'post', $sitemap->object_type );
		$this->assertSame( 'post', $sitemap->object_subtype );
	}

	public function test_unknown_type_resolves_to_null(): void {
		$this->assertNull( $this->resolve_type( 'does-not-exist' ) );
	}

	public function test_redirect_canonical_is_disabled_for_sitemap_requests(): void {
		set_query_var( 'static-sitemap', 'post' );

		$this->assertFalse( $this->router->redirect_canonical( 'https://example.org/redirect' ) );
	}

	public function test_redirect_canonical_passes_through_for_other_requests(): void {
		set_query_var( 'static-sitemap', '' );

		$this->assertSame(
			'https://example.org/redirect',
			$this->router->redirect_canonical( 'https://example.org/redirect' )
		);
	}
}
