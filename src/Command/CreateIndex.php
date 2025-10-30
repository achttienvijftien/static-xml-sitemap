<?php
/**
 * CreateIndex
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Command;
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Command;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider as PostProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider as TermProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserProvider;
use WP_CLI\ExitException;

/**
 * Class CreateIndex
 */
class CreateIndex {

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
	 *   - term
	 * ---
	 *
	 * [--post_type=<post_type>]
	 * : Post type of the sitemap to index.
	 *
	 * [--taxonomy=<taxonomy>]
	 * : Taxonomy of the sitemap to index.
	 *
	 * [--force-recreate]
	 * : Clear existing sitemap if it exists.
	 *
	 * @when after_wp_load
	 * @throws ExitException
	 */
	public function __invoke( $args, $assoc_args ): void {
		$object_type    = $assoc_args['object_type'] ?? 'post';
		$post_types     = array_filter( [ $assoc_args['post_type'] ?? null ] );
		$taxonomies     = array_filter( [ $assoc_args['taxonomy'] ?? null ] );
		$force_recreate = $assoc_args['force-recreate'] ?? false;

		set_time_limit( 0 );

		$results = [];

		if ( 'post' === $object_type ) {
			foreach ( $post_types as $post_type ) {
				if ( ! post_type_exists( $post_type ) ) {
					\WP_CLI::error( "$post_type is not a valid post type." );
				}
			}

			$results = $this->post_provider->index_objects( $post_types, $force_recreate );
		}

		if ( 'user' === $object_type ) {
			$results = $this->user_provider->index_objects( [], $force_recreate );
		}

		if ( 'term' === $object_type ) {
			foreach ( $taxonomies as $taxonomy ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					\WP_CLI::error( "$taxonomy is not a valid taxonomy." );
				}
			}

			$results = $this->term_provider->index_objects( $taxonomies, $force_recreate );
		}

		foreach ( $results as $result ) {
			$object_type_description = $object_type;
			if ( 'post' === $object_type ) {
				$object_type_description = "post type ${result['object_subtype']}";
			}
			if ( 'user' === $object_type ) {
				$object_type_description = 'users';
			}
			if ( 'term' === $object_type ) {
				$object_type_description = "taxonomy ${result['object_subtype']}";
			}
			\WP_CLI::line( "Indexed $object_type_description, total ${result['objects_indexed']} items." );

			if ( isset( $result['error'] ) && $result['error'] instanceof \WP_Error ) {
				\WP_CLI::warning( $result['error']->get_error_message() );
			}
		}

	}

}
