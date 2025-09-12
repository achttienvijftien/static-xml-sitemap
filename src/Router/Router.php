<?php
/**
 * Router
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Router
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Router;

use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapIndexRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;

/**
 * Class Router
 */
class Router {

	private const OPTION_FLUSHED_REWRITE_RULES = 'static_sitemap_rewrites_flushed';

	private SitemapStore $sitemap_store;
	private SitemapIndexRenderer $index_renderer;
	private SitemapRenderer $sitemap_renderer;

	public function __construct(
		SitemapStore $sitemap_store,
		SitemapIndexRenderer $index_renderer,
		SitemapRenderer $sitemap_renderer
	) {
		$this->index_renderer   = $index_renderer;
		$this->sitemap_renderer = $sitemap_renderer;
		$this->sitemap_store    = $sitemap_store;
	}

	public function add_hooks(): void {
		add_action( 'init', [ $this, 'register_rewrites' ] );
		add_action( 'template_redirect', [ $this, 'render_sitemaps' ] );
		add_filter( 'redirect_canonical', [ $this, 'redirect_canonical' ] );
	}

	/**
	 * @param string|mixed $redirect The redirect URL currently determined.
	 *
	 * @return false|mixed
	 */
	public function redirect_canonical( $redirect ) {
		if ( get_query_var( 'static-sitemap' ) ) {
			return false;
		}

		return $redirect;
	}

	public function render_sitemaps() {
		global $wp_query;

		$type = sanitize_text_field( get_query_var( 'static-sitemap' ) );

		if ( empty( $type ) ) {
			return;
		}

		if ( ! $this->sitemaps_enabled() ) {
			$wp_query->set_404();
			status_header( 404 );

			return;
		}

		$paged = sanitize_text_field( get_query_var( 'paged', 1 ) );
		$paged = max( 1, (int) $paged );

		if ( 'index' === $type ) {
			$this->index_renderer->render();
			exit();
		}

		$sitemap = $this->get_sitemap_for_type( $type );

		if ( ! $sitemap ) {
			$wp_query->set_404();
			status_header( 404 );

			return;
		}

		$this->sitemap_renderer->render( $sitemap, $paged );
		exit();
	}

	private function sitemaps_enabled() {
		return apply_filters( 'static_sitemap_enabled', false );
	}

	private function get_sitemap_for_type( string $type ): ?Sitemap {
		if ( 'author' === $type ) {
			return $this->sitemap_store->get_by_object_type( 'user' );
		}

		foreach ( [ 'post', 'term' ] as $object_type ) {
			$sitemap = $this->sitemap_store->get_by_object_type( $object_type, $type );

			if ( $sitemap ) {
				return $sitemap;
			}
		}

		return null;
	}

	public function register_rewrites(): void {
		add_rewrite_tag( '%static-sitemap%', '([^?]+)' );
		add_rewrite_rule( '^sitemap_index\.xml$', 'index.php?static-sitemap=index', 'top' );
		add_rewrite_rule(
			'^(.+)-sitemap(\d+)?\.xml$',
			'index.php?static-sitemap=$matches[1]&paged=$matches[2]',
			'top'
		);

		if ( ! $this->flushed_rewrite_rules() ) {
			add_action( 'shutdown', [ $this, 'maybe_flush_rewrite_rules' ] );
		}
	}

	private function flushed_rewrite_rules(): bool {
		return (bool) get_option( self::OPTION_FLUSHED_REWRITE_RULES, false );
	}

	public function maybe_flush_rewrite_rules() {
		if ( ! did_action( 'generate_rewrite_rules' ) && ! $this->flushed_rewrite_rules() ) {
			flush_rewrite_rules();
		}

		update_option( self::OPTION_FLUSHED_REWRITE_RULES, true, true );
	}

}
