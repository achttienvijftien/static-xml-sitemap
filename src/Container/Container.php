<?php
/**
 * Container
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Container;

use AchttienVijftien\Plugin\StaticXMLSitemap\Cli;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Installer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Provider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapIndexRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Router\Router;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;

/**
 * Class Container
 */
class Container implements ContainerInterface {

	use ContainerTrait;

	public function __construct() {
		$this->register( Plugin::class, fn() => $this->plugin() );
		$this->register( Installer::class, fn() => $this->installer() );
		$this->register( Watcher::class, fn() => $this->post_watcher() );
		$this->register( Provider::class, fn() => $this->post_sitemaps() );
		$this->register( PostItemStore::class, fn() => $this->post_item_store() );
		$this->register( SitemapStore::class, fn() => $this->sitemaps_store() );
		$this->register( JobStore::class, fn() => $this->job_store() );
		$this->register( Logger::class, fn() => $this->logger() );
		$this->register( Cli::class, fn() => $this->cli() );
		$this->register( WordPressSeo::class, fn() => $this->wordpress_seo() );
		$this->register( Router::class, fn() => $this->router() );
		$this->register( SitemapIndexRenderer::class, fn() => $this->sitemap_index_renderer() );
		$this->register( SitemapRenderer::class, fn() => $this->sitemap_renderer() );
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function plugin(): Plugin {
		return new Plugin(
			$this->get( Installer::class ),
			$this->get( Watcher::class ),
			$this->get( WordPressSeo::class ),
			$this->get( Router::class ),
			Cli::is_wp_cli() ? $this->get( Cli::class ) : null
		);
	}

	protected function installer(): Installer {
		return new Installer();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function post_watcher(): Watcher {
		return new Watcher(
			$this->get( Provider::class ),
			$this->get( PostItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function post_sitemaps(): Provider {
		return new Provider(
			$this->get( SitemapStore::class ),
			$this->get( PostItemStore::class ),
			$this->get( JobStore::class ),
			$this->get( Logger::class ),
			$this->get_parameter( 'page_size' )
		);
	}

	protected function post_item_store(): PostItemStore {
		return new PostItemStore( $this->get_parameter( 'page_size' ) );
	}

	protected function sitemaps_store(): SitemapStore {
		return new SitemapStore();
	}

	protected function job_store(): JobStore {
		return new JobStore();
	}

	private function logger(): Logger {
		return new Logger();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function cli(): Cli {
		return new Cli( $this->get( Provider::class ) );
	}

	protected function wordpress_seo(): WordPressSeo {
		return new WordPressSeo();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function router(): Router {
		return new Router(
			$this->get( SitemapStore::class ),
			$this->get( SitemapIndexRenderer::class ),
			$this->get( SitemapRenderer::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	private function sitemap_index_renderer(): SitemapIndexRenderer {
		return new SitemapIndexRenderer(
			$this->get( SitemapStore::class ),
			$this->get( PostItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	private function sitemap_renderer(): SitemapRenderer {
		return new SitemapRenderer(
			$this->get( PostItemStore::class ),
			$this->get_parameter( 'page_size' )
		);
	}

	public function add_parameters( array $parameters ): self {
		foreach ( $parameters as $name => $value ) {
			$this->parameters[ $name ] = $value;
		}

		return $this;
	}
}
