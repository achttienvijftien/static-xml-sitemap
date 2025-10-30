<?php
/**
 * RunJobs
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Command
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Command;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider as PostProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider as TermProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserProvider;

/**
 * Class RunJobs
 */
class RunJobs {

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
	 *   - term
	 * ---
	 *
	 * [--post_type=<post_type>]
	 * : Post type of the sitemap to run jobs for.
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Taxonomy of the sitemap to run jobs for.
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ): void {
		$object_type = $assoc_args['object_type'] ?? 'post';
		$post_type   = $assoc_args['post_type'] ?? null;
		$taxonomy    = $assoc_args['taxonomy'] ?? null;

		set_time_limit( 0 );

		if ( 'post' === $object_type ) {
			$post_types = $post_type ? [ $post_type ] : $this->post_provider->get_post_types();
			$this->post_provider->run_jobs( $post_types );
		}
		if ( 'user' === $object_type ) {
			$this->user_provider->run_jobs();
		}
		if ( 'term' === $object_type ) {
			$taxonomies = $taxonomy ? [ $taxonomy ] : $this->term_provider->get_taxonomies();
			$this->term_provider->run_jobs( $taxonomies );
		}
	}
}
