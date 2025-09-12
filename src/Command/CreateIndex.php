<?php
/**
 * CreateIndex
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Command;
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Command;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Provider as PostSitemaps;
use WP_CLI\ExitException;

/**
 * Class CreateIndex
 */
class CreateIndex {

	private PostSitemaps $post_sitemaps;

	public function __construct( PostSitemaps $post_sitemaps ) {
		$this->post_sitemaps = $post_sitemaps;
	}

	/**
	 * Creates the initial sitemap indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--object_type=<object_type>]
	 * : Object type of the sitemap to index.
	 * ---
	 * options:
	 *   - post
	 *   - user
	 * ---
	 *
	 * [--post_type=<post_type>]
	 * : Post type of the sitemap to index.
	 *
	 * [--force-recreate]
	 * : Clear existing sitemap if it exists.
	 *
	 * [--create-sitemap]
	 * : Create new sitemap if needed.
	 *
	 * @when after_wp_load
	 * @throws ExitException
	 */
	public function __invoke( $args, $assoc_args ): void {
		$object_type    = $assoc_args['object_type'] ?? 'post';
		$post_types     = [ $assoc_args['post_type'] ?? 'post' ];
		$force_recreate = $assoc_args['force-recreate'] ?? false;
		$create_sitemap = $assoc_args['create-sitemap'] ?? false;

		set_time_limit( 0 );

		$result = null;

		if ( 'post' === $object_type ) {
			$result = $this->post_sitemaps->create_index( $post_types, $create_sitemap, $force_recreate );
		}

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
	}

}
