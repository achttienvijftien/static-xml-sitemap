<?php
/**
 * Plugin
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Router\Router;

/**
 * Class Plugin
 */
class Plugin {

	private Installer $installer;
	private Watcher $post_watcher;
	private ?Cli $cli;
	private WordPressSeo $wordpress_seo;
	private Router $router;

	public function __construct(
		Installer $installer,
		Watcher $post_watcher,
		WordPressSeo $wordpress_seo,
		Router $router,
		?Cli $cli = null
	) {
		$this->installer     = $installer;
		$this->post_watcher  = $post_watcher;
		$this->wordpress_seo = $wordpress_seo;
		$this->router        = $router;
		$this->cli           = $cli;

		if ( $this->cli ) {
			$this->cli->register_commands();
		}
	}

	public function add_hooks(): void {
		$this->installer->add_hooks();
		$this->post_watcher->add_hooks();
		$this->router->add_hooks();

		$this->add_compatibility_hooks();
	}

	private function add_compatibility_hooks(): void {
		if ( $this->wordpress_seo->is_activated() && $this->wordpress_seo->sitemaps_enabled() ) {
			$this->wordpress_seo->add_hooks();
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
