<?php
/**
 * SitemapProvider
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Post
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\Job;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\AbstractProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class SitemapProvider
 *
 * @property ItemStoreInterface<PostItem> $item_store
 */
class SitemapProvider extends AbstractProvider implements ProviderInterface {

	private ?array $post_types = null;

	public function process_watches( int $object_id, int $events ): void {
		$post = get_post( $object_id );
		$item = $this->item_store->get_one_by_object_id( $object_id );
		if ( $events & Watcher::POST_TYPE_UPDATED ) {
			$item = $this->remove_invalid_items(
				$this->item_store->find_by_object_id( $object_id ),
				$post
			);
		}

		$invalidations = $this->get_invalidations( $events );

		$this->process_invalidations( $invalidations, $post, $item );
	}

	/**
	 * @param PostItem[]    $items
	 * @param \WP_Post|null $post
	 *
	 * @return PostItem|null
	 */
	protected function remove_invalid_items( array $items, ?\WP_Post $post ): ?PostItem {
		$valid_item = null;

		$sitemap = null;
		if ( $post && $this->post_type_has_sitemap( $post ) ) {
			$sitemap = $this->sitemap_store->get_by_object_type( 'post', $post->post_type );
		}

		foreach ( $items as $item ) {
			if ( null === $sitemap || $item->sitemap_id !== $sitemap->id ) {
				$this->job_store->insert_job( Job::remove_item( $item ) );
				continue;
			}

			$valid_item = $item;
		}

		return $valid_item;
	}

	protected function get_invalidations( int $events ): int {
		$invalidations = 0;

		if ( $events & Watcher::POST_TYPE_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::POST_STATUS_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::POST_PERMALINK_UPDATED ) {
			$invalidations |= Invalidations::ITEM_URL;
		}
		if ( $events & Watcher::POST_SAVED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::POST_DELETED ) {
			// Ignore other invalidations in case post was deleted.
			$invalidations = Invalidations::OBJECT_EXISTS;
		}

		return apply_filters( 'static_sitemap_post_invalidations', $invalidations, $events );
	}

	public function is_indexable( $object ) {
		if ( ! $object instanceof \WP_Post ) {
			return false;
		}

		if ( ! $this->post_type_has_sitemap( $object ) ) {
			return false;
		}

		$indexable = in_array( $object->post_status, [ 'publish', 'inherit' ], true );

		return apply_filters( 'static_sitemap_post_indexable', $indexable, $object );
	}

	public function post_type_has_sitemap( \WP_Post $post ): bool {
		return in_array( $post->post_type, $this->get_post_types(), true );
	}

	public function get_post_types(): array {
		if ( isset( $this->post_types ) ) {
			return $this->post_types;
		}

		$post_types = get_post_types( [ 'public' => true ] );
		$post_types = array_filter( $post_types, 'is_post_type_viewable' );

		$this->post_types = apply_filters( 'static_sitemap_post_types', $post_types );

		return $this->post_types;
	}

	/**
	 * @param PostItem|mixed $item
	 *
	 * @return void
	 */
	protected function update_item_url( $item ) {
		if ( ! $item instanceof PostItem ) {
			return;
		}

		$url = get_permalink( $item->post_id );
		$url = apply_filters( 'static_sitemap_post_url', $url, $item->get_object() );

		if ( ! $url ) {
			return;
		}

		$item->url = Url::remove_home_url( $url );
		$this->item_store->update_item( $item );
	}

	private function get_indexer(): Indexer {
		return new Indexer(
			$this,
			$this->item_store,
			$this->sitemap_store,
			$this->logger,
			$this->page_size
		);
	}

	public function handles_object( $object ): bool {
		return $object instanceof \WP_Post;
	}

	public function get_object_type(): string {
		return 'post';
	}

	public static function compare_objects( $a, $b ): int {
		if ( is_int( $a ) && 0 !== $a ) {
			$a = get_post( $a );
		}

		if ( is_int( $b ) && 0 !== $b ) {
			$b = get_post( $b );
		}

		if ( ! $a instanceof \WP_Post || ! $b instanceof \WP_Post ) {
			return 0;
		}

		return $a->post_modified_gmt <=> $b->post_modified_gmt
			?: $a->ID <=> $b->ID;
	}

	protected function get_object_by_id( int $object_id ) {
		return get_post( $object_id );
	}

	public function index_objects( ?array $subtypes = [], bool $force_recreate = false ): array {
		$post_types = empty( $subtypes ) ? $this->get_post_types() : $subtypes;

		$indexer = $this->get_indexer()
			->set_force_recreate( $force_recreate );

		$results = [];

		foreach ( $post_types as $post_type ) {
			$posts_indexed = $indexer->run( $post_type );
			$results[]     = [
				'object_type'     => 'post',
				'object_subtype'  => $post_type,
				'objects_indexed' => is_wp_error( $posts_indexed ) ? 0 : $posts_indexed,
				'error'           => is_wp_error( $posts_indexed ) ? $posts_indexed : null,
			];
		}

		return $results;
	}

	public function is_enabled(): bool {
		return true;
	}

}
