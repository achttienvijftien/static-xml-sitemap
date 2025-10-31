<?php
/**
 * PostWatcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher;

/**
 * Class PostWatcher
 */
class PostWatcher {

	public const NOINDEX_META_UPDATED   = 1 << 10;
	public const CANONICAL_META_UPDATED = 1 << 11;
	public const POST_MODIFIED_UPDATED  = 1 << 12;

	private Watcher $watcher;
	private SitemapProvider $provider;
	private PostItemStore $post_item_store;

	private array $meta_key_events;

	public function __construct( Watcher $watcher, SitemapProvider $provider, PostItemStore $post_item_store ) {
		$this->watcher         = $watcher;
		$this->provider        = $provider;
		$this->post_item_store = $post_item_store;
		$this->meta_key_events = [
			'_yoast_wpseo_meta-robots-noindex' => self::NOINDEX_META_UPDATED,
			'_yoast_wpseo_canonical'           => self::CANONICAL_META_UPDATED,
		];
	}

	public function add_hooks(): void {
		add_action( 'updated_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'updated_post_meta' ], 10, 4 );
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
	}

	public function updated_post_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( ! key_exists( $meta_key, $this->meta_key_events ) ) {
			return;
		}

		$this->watcher->add_events( $object_id, $this->meta_key_events[ $meta_key ] );
	}

	/**
	 * @param int|mixed $post_id
	 * @param \WP_Post  $post_after
	 * @param \WP_Post  $post_before
	 *
	 * @return void
	 */
	public function post_updated( $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( $post_after->post_modified_gmt === '0000-00-00 00:00:00'
			|| $post_before->post_modified_gmt === '0000-00-00 00:00:00'
			|| $post_after->post_modified_gmt === $post_before->post_modified_gmt
		) {
			return;
		}

		$item = $this->post_item_store->get_one_by_object_id( $post_id );

		$is_indexable_before = $this->provider->is_indexable( $post_before );
		$is_indexable_after  = $this->provider->is_indexable( $post_after );

		if ( ! ( $item && $is_indexable_after || ! $item && $is_indexable_before && $is_indexable_after ) ) {
			return;
		}

		$this->watcher->add_events( $post_id, self::POST_MODIFIED_UPDATED );
	}

}
