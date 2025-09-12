<?php
/**
 * RunJobs
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Command
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Command;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Provider as PostSitemaps;

/**
 * Class RunJobs
 */
class RunJobs {

	private PostSitemaps $post_sitemaps;

	public function __construct( PostSitemaps $post_sitemaps ) {
		$this->post_sitemaps = $post_sitemaps;
	}

	/**
	 * Runs jobs for sitemap item updates.
	 *
	 * ## OPTIONS
	 *
	 * [--object_type=<object_type>]
	 * : Object type of the sitemap to run jobs for.
	 * ---
	 * options:
	 *   - post
	 *   - user
	 * ---
	 *
	 * [--post_type=<post_type>]
	 * : Post type of the sitemap to run jobs for.
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ): void {
		$object_type = $assoc_args['object_type'] ?? 'post';
		$post_types  = [ $assoc_args['post_type'] ?? 'post' ];

		set_time_limit( 0 );

		if ( 'post' === $object_type ) {
			$this->post_sitemaps->run_jobs( $post_types );
		}
	}
}
