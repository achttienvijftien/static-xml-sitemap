<?php
/**
 * Plugin
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\YoastNewsSeo\YoastNewsSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider as PostSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Router\Router;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider as TermSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserSitemapProvider;

/**
 * Class Plugin
 */
class Plugin {

	private Installer $installer;
	private WordPressSeo $wordpress_seo;
	private YoastNewsSeo $yoast_news_seo;
	private PostSitemapProvider $post_sitemap_provider;
	private TermSitemapProvider $term_sitemap_provider;
	private UserSitemapProvider $user_sitemap_provider;
	private Router $router;
	private ?Cli $cli;

	public function __construct(
		Installer $installer,
		PostSitemapProvider $post_sitemap_provider,
		UserSitemapProvider $user_sitemap_provider,
		TermSitemapProvider $term_sitemap_provider,
		WordPressSeo $wordpress_seo,
		YoastNewsSeo $yoast_news_seo,
		Router $router,
		?Cli $cli = null
	) {
		$this->installer             = $installer;
		$this->wordpress_seo         = $wordpress_seo;
		$this->yoast_news_seo        = $yoast_news_seo;
		$this->post_sitemap_provider = $post_sitemap_provider;
		$this->term_sitemap_provider = $term_sitemap_provider;
		$this->user_sitemap_provider = $user_sitemap_provider;
		$this->router                = $router;
		$this->cli                   = $cli;

		if ( $this->cli ) {
			$this->cli->register_commands();
		}
	}

	public function add_hooks(): void {
		$this->installer->add_hooks();
		$this->post_sitemap_provider->add_hooks();
		$this->user_sitemap_provider->add_hooks();
		$this->term_sitemap_provider->add_hooks();
		$this->router->add_hooks();

		// Delay compatibility checks until we're sure all plugins are initialized.
		add_action( 'plugins_loaded', [ $this, 'add_compatibility_hooks' ], PHP_INT_MAX );
	}

	public function add_compatibility_hooks(): void {
		if ( $this->wordpress_seo->is_activated() && $this->wordpress_seo->sitemaps_enabled() ) {
			$this->wordpress_seo->add_hooks();
		}

		if ( $this->yoast_news_seo->is_activated() ) {
			$this->yoast_news_seo->add_hooks();
		}
	}

	public function activate(): void {
		$this->installer->activate();
	}

	public function deactivate(): void {
		$this->installer->deactivate();
	}

	public function uninstall(): void {
		$this->installer->uninstall();
	}

}
