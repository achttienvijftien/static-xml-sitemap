<?php
/**
 * Watcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

/**
 * Class Watcher
 */
class Watcher {

	public const POST_TYPE_UPDATED      = 1 << 0;
	public const POST_MODIFIED_UPDATED  = 1 << 1;
	public const POST_STATUS_UPDATED    = 1 << 2;
	public const POST_META_UPDATED      = 1 << 3;
	public const POST_DELETED           = 1 << 4;
	public const POST_PERMALINK_UPDATED = 1 << 5;

	private Provider $sitemaps;
	private PostItemStore $post_item_store;
	private array $invalidations = [];

	public function __construct( Provider $sitemaps, PostItemStore $post_item_store ) {
		$this->sitemaps        = $sitemaps;
		$this->post_item_store = $post_item_store;
	}

	public function add_hooks(): void {
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'delete_post', [ $this, 'delete_post' ], 10, 2 );
		add_action( 'shutdown', [ $this, 'process_invalidations' ] );
	}

	/**
	 * @param int|mixed $post_id
	 * @param \WP_Post  $post_after
	 * @param \WP_Post  $post_before
	 *
	 * @return void
	 */
	public function post_updated( $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		$items = $this->post_item_store->find_by_object_id( $post_id );

		if ( ! $items ) {
			if ( $this->sitemaps->post_type_has_sitemap( $post_after ) ) {
				$this->sitemaps->add_to_sitemap( $post_after );
			}

			return;
		}

		$invalidations = 0;

		if ( $post_after->post_type !== $post_before->post_type ) {
			$invalidations |= self::POST_TYPE_UPDATED;
		}

		if ( $post_after->post_modified_gmt !== $post_before->post_modified_gmt ) {
			$invalidations |= self::POST_MODIFIED_UPDATED;
		}

		if ( $post_after->post_status !== $post_before->post_status ) {
			$invalidations |= self::POST_STATUS_UPDATED;
		}

		$permalink = get_permalink( $post_id );

		foreach ( $items as $item ) {
			if ( $permalink !== $item->get_url() ) {
				$invalidations |= self::POST_PERMALINK_UPDATED;
			}
		}

		if ( $invalidations ) {
			$this->add_invalidations( $post_id, $invalidations );
		}
	}

	private function add_invalidations( int $post_id, int $invalidations ): void {
		$this->invalidations[ $post_id ] ??= 0;
		$this->invalidations[ $post_id ] |= $invalidations;
	}

	/**
	 * @param mixed|int $post_id
	 * @param \WP_Post  $post
	 * @param bool      $update
	 */
	public function save_post( $post_id, \WP_Post $post, bool $update ) {
		if ( ! $update && $this->sitemaps->post_type_has_sitemap( $post ) ) {
			$this->sitemaps->add_to_sitemap( $post );
		}
	}

	public function updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		$invalidating_meta_keys = apply_filters( 'static_sitemap_invalidating_meta_keys', [] );

		if ( ! in_array( $meta_key, $invalidating_meta_keys, true ) ) {
			return;
		}

		$items = $this->post_item_store->find_by_object_id( $object_id );

		if ( $items ) {
			$this->add_invalidations( $object_id, self::POST_META_UPDATED );

			return;
		}

		$post = get_post( $object_id );

		if ( $post && $this->sitemaps->post_type_has_sitemap( $post ) ) {
			$this->sitemaps->add_to_sitemap( $post );
		}
	}

	public function delete_post( $post_id, \WP_Post $post ): void {
		$items = $this->post_item_store->find_by_object_id( $post_id );

		if ( $items ) {
			$this->add_invalidations( $post_id, self::POST_DELETED );
		}
	}

	public function process_invalidations(): void {
		foreach ( $this->invalidations as $post_id => $invalidations ) {
			$this->sitemaps->process_invalidations( $post_id, $invalidations );
		}
	}

}
