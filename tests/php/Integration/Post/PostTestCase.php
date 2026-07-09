<?php
/**
 * PostTestCase
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class PostTestCase
 */
abstract class PostTestCase extends TestCase {

	protected Container $container;
	protected SitemapProvider $provider;
	protected PostItemStore $item_store;
	protected SitemapStore $sitemap_store;
	protected JobStore $job_store;
	protected Watcher $watcher;

	/**
	 * Builds a fresh set of services and removes any pre-existing posts.
	 */
	protected function boot(): void {
		$this->container     = $this->create_container();
		$this->provider      = $this->container->get( SitemapProvider::class );
		$this->item_store    = $this->container->get( PostItemStore::class );
		$this->sitemap_store = $this->container->get( SitemapStore::class );
		$this->job_store     = $this->container->get( JobStore::class );
		$this->watcher       = $this->container->get( Watcher::class );

		$this->delete_all_posts();
	}

	/**
	 * Deletes every post so each test starts with an empty content set.
	 */
	protected function delete_all_posts(): void {
		$post_ids = get_posts(
			[
				'numberposts' => -1,
				'post_type'   => 'any',
				'post_status' => 'any',
				'fields'      => 'ids',
			]
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Registers the watcher on the real WordPress events.
	 */
	protected function register_watch_hooks(): void {
		add_action( 'save_post', [ $this->watcher, 'save_post' ], 10, 3 );
		add_action( 'post_updated', [ $this->watcher, 'post_updated' ], 10, 3 );
		add_action( 'delete_post', [ $this->watcher, 'delete_post' ], 10, 2 );
	}

	/**
	 * Overwrites the stored modification date of a post.
	 */
	protected function set_post_modified( int $post_id, string $modified_gmt ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => get_date_from_gmt( $modified_gmt ),
				'post_modified_gmt' => $modified_gmt,
			],
			[ 'ID' => $post_id ]
		);

		clean_post_cache( $post_id );
	}

	/**
	 * Returns the raw sitemap row for the post/post sitemap.
	 *
	 * @return object|null
	 */
	protected function get_post_sitemap() {
		global $wpdb;

		return $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}sitemaps WHERE object_type = 'post' AND object_subtype = 'post'"
		);
	}

	/**
	 * Returns all post sitemap item rows ordered by their index.
	 *
	 * @return object[]
	 */
	protected function get_item_rows(): array {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}sitemap_posts ORDER BY item_index"
		);
	}

	/**
	 * Returns a map of post id to item index for every stored item.
	 *
	 * @return array<int, int>
	 */
	protected function get_item_index_map(): array {
		$map = [];

		foreach ( $this->get_item_rows() as $row ) {
			$map[ (int) $row->post_id ] = (int) $row->item_index;
		}

		return $map;
	}

	/**
	 * Returns the stored job rows, optionally filtered by action.
	 *
	 * @return object[]
	 */
	protected function get_jobs( string $action = null ): array {
		global $wpdb;

		if ( null === $action ) {
			return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sitemap_jobs ORDER BY id" );
		}

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sitemap_jobs WHERE action = %s ORDER BY id", $action )
		);
	}

	protected function count_jobs(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}sitemap_jobs" );
	}
}
