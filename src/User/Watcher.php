<?php
/**
 * Watcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\AbstractWatcher;

/**
 * Class Watcher
 */
class Watcher extends AbstractWatcher {

	public const AUTHOR_PAGE_LINK_UPDATED = 1 << 0;
	public const USER_DELETED             = 1 << 1;
	public const USER_REGISTERED          = 1 << 2;
	public const POST_AUTHOR_UPDATED      = 1 << 3;
	public const POST_STATUS_UPDATED      = 1 << 4;

	private UserItemStore $user_item_store;

	public function __construct( UserItemStore $user_item_store ) {
		$this->user_item_store = $user_item_store;
	}

	protected function add_watch_hooks(): void {
		add_action( 'delete_user', [ $this, 'delete_user' ] );
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
		add_action( 'profile_update', [ $this, 'profile_update' ] );
		add_action( 'user_register', [ $this, 'user_register' ] );
	}

	public function delete_user( $user_id ): void {
		$items = $this->user_item_store->find_by_object_id( $user_id );

		if ( $items ) {
			$this->add_events( $user_id, self::USER_DELETED );
		}
	}

	/**
	 * @param int|mixed $post_id
	 * @param \WP_Post  $post_after
	 * @param \WP_Post  $post_before
	 *
	 * @return void
	 */
	public function post_updated( $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		$post_author_changed = $post_before->post_author !== $post_after->post_author;
		$post_status_changed = $post_before->post_status !== $post_after->post_status;

		foreach ( [ $post_before, $post_after ] as $post ) {
			if ( 0 === $post->post_author ) {
				continue;
			}

			if ( ! $this->has_public_post_type( $post ) ) {
				continue;
			}

			if ( $post_author_changed ) {
				$this->add_events( $post->post_author, self::POST_AUTHOR_UPDATED );
			}
			if ( $post_status_changed ) {
				$this->add_events( $post->post_author, self::POST_STATUS_UPDATED );
			}
		}
	}

	/**
	 * @param mixed|int $post_id
	 * @param \WP_Post  $post
	 * @param bool      $update
	 */
	public function save_post( $post_id, \WP_Post $post, bool $update ): void {
		if ( $update || 0 === $post->post_author || ! $this->has_public_post_type( $post ) ) {
			return;
		}

		$this->add_events( $post->post_author, self::POST_AUTHOR_UPDATED );
		$this->add_events( $post->post_author, self::POST_STATUS_UPDATED );
	}

	private function has_public_post_type( \WP_Post $post ): bool {
		$public_post_types = get_post_types( [ 'public' => true ] );

		return in_array( $post->post_type, $public_post_types, true );
	}

	public function profile_update( $user_id ): void {
		$item = $this->user_item_store->get_one_by_object_id( $user_id );

		if ( ! $item ) {
			return;
		}

		$author_posts_url = get_author_posts_url( $user_id );

		if ( $author_posts_url !== $item->get_url() ) {
			$this->add_events( $user_id, self::AUTHOR_PAGE_LINK_UPDATED );
		}
	}

	public function user_register( $user_id ): void {
		$this->add_events( $user_id, self::USER_REGISTERED );
	}

}
