<?php
/**
 * Cli
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Command\CreateIndex;
use AchttienVijftien\Plugin\StaticXMLSitemap\Command\RunJobs;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Provider as PostSitemaps;

/**
 * Class Cli
 */
class Cli {

	private PostSitemaps $post_sitemaps;

	public function __construct( PostSitemaps $post_sitemaps ) {
		$this->post_sitemaps = $post_sitemaps;
	}

	public static function is_wp_cli(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	public function register_commands(): void {
		if ( ! method_exists( \WP_CLI::class, 'add_command' ) ) {
			return;
		}

		try {
			\WP_CLI::add_command( 'sitemap create-index', new CreateIndex( $this->post_sitemaps ) );
			\WP_CLI::add_command( 'sitemap jobs run', new RunJobs( $this->post_sitemaps ) );
		} catch ( \Exception $e ) {
			// no-op.
		}
	}

}
