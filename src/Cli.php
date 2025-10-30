<?php
/**
 * Cli
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Command\CommandNamespace;
use AchttienVijftien\Plugin\StaticXMLSitemap\Command\CreateIndex;
use AchttienVijftien\Plugin\StaticXMLSitemap\Command\RunJobs;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider as PostProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider as TermProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserProvider;

/**
 * Class Cli
 */
class Cli {

	private PostProvider $post_provider;
	private UserProvider $user_provider;
	private TermProvider $term_provider;

	public function __construct(
		PostProvider $post_provider,
		UserProvider $user_provider,
		TermProvider $term_provider
	) {
		$this->post_provider = $post_provider;
		$this->user_provider = $user_provider;
		$this->term_provider = $term_provider;
	}

	public static function is_wp_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	public function register_commands(): void {
		if ( ! method_exists( \WP_CLI::class, 'add_command' ) ) {
			return;
		}

		try {
			if ( class_exists( '\WP_CLI\Dispatcher\CommandNamespace' ) ) {
				\WP_CLI::add_command( 'sitemap', CommandNamespace::class );
			}

			\WP_CLI::add_command(
				'sitemap create-index',
				new CreateIndex( $this->post_provider, $this->user_provider, $this->term_provider )
			);
			\WP_CLI::add_command(
				'sitemap jobs run',
				new RunJobs( $this->post_provider, $this->user_provider, $this->term_provider )
			);
		} catch ( \Exception $e ) {
			\WP_CLI::warning( $e->getMessage() );
		}
	}

}
