<?php
/**
 * Watcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\AbstractWatcher;

/**
 * Class Watcher
 *
 * @property SitemapProvider $provider
 */
class Watcher extends AbstractWatcher {

	public const POST_TYPE_UPDATED      = 1 << 0;
	public const POST_STATUS_UPDATED    = 1 << 1;
	public const POST_DELETED           = 1 << 2;
	public const POST_PERMALINK_UPDATED = 1 << 3;
	public const POST_SAVED             = 1 << 4;

	private PostItemStore $post_item_store;

	public function __construct( PostItemStore $post_item_store ) {
		$this->post_item_store = $post_item_store;
	}

	protected function add_watch_hooks(): void {
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
		add_action( 'delete_post', [ $this, 'delete_post' ], 10, 2 );
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

		$events = 0;

		if ( $post_after->post_type !== $post_before->post_type ) {
			$events |= self::POST_TYPE_UPDATED;
		}

		if ( $post_after->post_status !== $post_before->post_status ) {
			$events |= self::POST_STATUS_UPDATED;
		}

		if ( $items ) {
			$permalink = get_permalink( $post_id );

			foreach ( $items as $item ) {
				if ( $permalink !== $item->get_url() ) {
					$events |= self::POST_PERMALINK_UPDATED;
				}
			}
		}

		if ( $events ) {
			$this->add_events( $post_id, $events );
		}
	}

	/**
	 * @param mixed|int $post_id
	 * @param \WP_Post  $post
	 * @param bool      $update
	 */
	public function save_post( $post_id, \WP_Post $post, bool $update ) {
		if ( ! $update ) {
			$this->add_events( $post_id, self::POST_SAVED );
		}
	}

	public function delete_post( $post_id, \WP_Post $post ): void {
		$this->add_events( $post_id, self::POST_DELETED );
	}

}
