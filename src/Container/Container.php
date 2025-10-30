<?php
/**
 * Container
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Container;

use AchttienVijftien\Plugin\StaticXMLSitemap\Cli;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\PostWatcher as WpSeoPostWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\TermIndexer as WpSeoTermIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\TermWatcher as WpSeoTermWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\UserIndexer as WpSeoUserIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\UserWatcher as WpSeoUserWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\YoastNewsSeo\YoastNewsSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Installer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider as PostSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher as PostWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapIndexRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\SitemapRenderer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Router\Router;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider as TermSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Watcher as TermWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Watcher as UserWatcher;

/**
 * Class Container
 */
class Container implements ContainerInterface {

	use ContainerTrait;

	public function __construct() {
		$this->register( Plugin::class, fn() => $this->plugin() );
		$this->register( Installer::class, fn() => $this->installer() );
		$this->register( PostWatcher::class, fn() => $this->post_watcher() );
		$this->register( PostSitemapProvider::class, fn() => $this->post_sitemap_provider() );
		$this->register( PostItemStore::class, fn() => $this->post_item_store() );
		$this->register( UserWatcher::class, fn() => $this->user_watcher() );
		$this->register( UserSitemapProvider::class, fn() => $this->user_sitemap_provider() );
		$this->register( UserItemStore::class, fn() => $this->user_item_store() );
		$this->register( TermWatcher::class, fn() => $this->term_watcher() );
		$this->register( TermSitemapProvider::class, fn() => $this->term_sitemap_provider() );
		$this->register( TermItemStore::class, fn() => $this->term_item_store() );
		$this->register( SitemapStore::class, fn() => $this->sitemaps_store() );
		$this->register( JobStore::class, fn() => $this->job_store() );
		$this->register( Logger::class, fn() => $this->logger() );
		$this->register( Cli::class, fn() => $this->cli() );
		$this->register( WordPressSeo::class, fn() => $this->wordpress_seo() );
		$this->register( WpSeoPostWatcher::class, fn() => $this->wpseo_post_watcher() );
		$this->register( WpSeoTermWatcher::class, fn() => $this->wpseo_term_watcher() );
		$this->register( WpSeoTermIndexer::class, fn() => $this->wpseo_term_indexer() );
		$this->register( WpSeoUserIndexer::class, fn() => $this->wpseo_user_indexer() );
		$this->register( WpSeoUserWatcher::class, fn() => $this->wpseo_user_watcher() );
		$this->register( YoastNewsSeo::class, fn() => $this->yoast_news_seo() );
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
			$this->get( PostSitemapProvider::class ),
			$this->get( UserSitemapProvider::class ),
			$this->get( TermSitemapProvider::class ),
			$this->get( WordPressSeo::class ),
			$this->get( YoastNewsSeo::class ),
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
	protected function post_watcher(): PostWatcher {
		return new PostWatcher(
			$this->get( PostItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function post_sitemap_provider(): PostSitemapProvider {
		$watcher  = $this->get( PostWatcher::class );
		$provider = new PostSitemapProvider(
			$watcher,
			$this->get( SitemapStore::class ),
			$this->get( PostItemStore::class ),
			$this->get( JobStore::class ),
			$this->get( Logger::class ),
			$this->get_parameter( 'page_size' )
		);
		$watcher->set_provider( $provider );

		return $provider;
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function user_watcher(): UserWatcher {
		return new UserWatcher(
			$this->get( UserItemStore::class ),
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function user_sitemap_provider(): UserSitemapProvider {
		$watcher  = $this->get( UserWatcher::class );
		$provider = new UserSitemapProvider(
			$watcher,
			$this->get( SitemapStore::class ),
			$this->get( UserItemStore::class ),
			$this->get( JobStore::class ),
			$this->get( Logger::class ),
			$this->get_parameter( 'page_size' )
		);
		$watcher->set_provider( $provider );

		return $provider;
	}

	protected function user_item_store(): UserItemStore {
		return new UserItemStore( $this->get_parameter( 'page_size' ) );
	}

	protected function post_item_store(): PostItemStore {
		return new PostItemStore( $this->get_parameter( 'page_size' ) );
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function term_watcher(): TermWatcher {
		return new TermWatcher(
			$this->get( TermItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function term_sitemap_provider(): TermSitemapProvider {
		$watcher  = $this->get( TermWatcher::class );
		$provider = new TermSitemapProvider(
			$watcher,
			$this->get( SitemapStore::class ),
			$this->get( TermItemStore::class ),
			$this->get( JobStore::class ),
			$this->get( Logger::class ),
			$this->get_parameter( 'page_size' )
		);
		$watcher->set_provider( $provider );

		return $provider;
	}

	protected function term_item_store(): TermItemStore {
		return new TermItemStore( $this->get_parameter( 'page_size' ) );
	}

	protected function sitemaps_store(): SitemapStore {
		return new SitemapStore();
	}

	protected function job_store(): JobStore {
		return new JobStore();
	}

	protected function logger(): Logger {
		return new Logger();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function cli(): Cli {
		return new Cli(
			$this->get( PostSitemapProvider::class ),
			$this->get( UserSitemapProvider::class ),
			$this->get( TermSitemapProvider::class ),
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function wordpress_seo(): WordPressSeo {
		return new WordPressSeo(
			$this->get( WpSeoPostWatcher::class ),
			$this->get( WpSeoUserIndexer::class ),
			$this->get( WpSeoUserWatcher::class ),
			$this->get( WpSeoTermIndexer::class ),
			$this->get( WpSeoTermWatcher::class ),
			$this->get( TermItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function wpseo_term_indexer(): WpSeoTermIndexer {
		return new WpSeoTermIndexer(
			$this->get( SitemapStore::class ),
			$this->get( TermItemStore::class )
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function wpseo_term_watcher(): WpSeoTermWatcher {
		return new WpSeoTermWatcher(
			$this->get( TermWatcher::class ),
			$this->get( TermSitemapProvider::class ),
			$this->get( TermItemStore::class )
		);
	}

	protected function wpseo_user_indexer(): WpSeoUserIndexer {
		return new WpSeoUserIndexer();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function wpseo_user_watcher(): WpSeoUserWatcher {
		return new WpSeoUserWatcher(
			$this->get( UserWatcher::class ),
			$this->get( UserSitemapProvider::class ),
			$this->get( UserItemStore::class ),
		);
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
	protected function sitemap_index_renderer(): SitemapIndexRenderer {
		return new SitemapIndexRenderer(
			$this->get( SitemapStore::class ),
			$this->get( PostItemStore::class ),
			$this->get( UserItemStore::class ),
			$this->get( TermItemStore::class ),
		);
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	protected function sitemap_renderer(): SitemapRenderer {
		return new SitemapRenderer(
			$this->get( PostItemStore::class ),
			$this->get( UserItemStore::class ),
			$this->get( TermItemStore::class ),
		);
	}

	public function add_parameters( array $parameters ): self {
		foreach ( $parameters as $name => $value ) {
			$this->parameters[ $name ] = $value;
		}

		return $this;
	}

	private function yoast_news_seo(): YoastNewsSeo {
		return new YoastNewsSeo();
	}

	/**
	 * @throws ServiceNotFoundException
	 */
	private function wpseo_post_watcher(): WpSeoPostWatcher {
		return new WpSeoPostWatcher( $this->get( PostWatcher::class ) );
	}

}
