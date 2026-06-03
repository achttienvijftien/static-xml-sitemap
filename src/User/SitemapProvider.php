<?php
/**
 * SitemapProvider
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\AbstractProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class SitemapProvider
 */
class SitemapProvider extends AbstractProvider implements ProviderInterface {

	protected function get_invalidations( int $events ): int {
		$invalidations = 0;

		if ( $events & Watcher::AUTHOR_PAGE_LINK_UPDATED ) {
			$invalidations |= Invalidations::ITEM_URL;
		}
		if ( $events & Watcher::USER_REGISTERED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::POST_AUTHOR_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::POST_STATUS_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::USER_DELETED ) {
			// Ignore other invalidations in case user was deleted.
			$invalidations = Invalidations::OBJECT_EXISTS;
		}

		return apply_filters( 'static_sitemap_user_invalidations', $invalidations, $events );
	}

	public function is_indexable( $object ) {
		if ( ! $object instanceof \WP_User ) {
			return false;
		}

		$indexable = $this->has_published_posts( $object->ID );

		return apply_filters( 'static_sitemap_user_indexable', $indexable, $object );
	}

	/**
	 * @param UserItem|mixed $item
	 *
	 * @return void
	 */
	protected function update_item_url( $item ) {
		if ( ! $item instanceof UserItem ) {
			return;
		}

		$url = get_author_posts_url( $item->user_id );
		$url = apply_filters( 'static_sitemap_author_page_url', $url, $item->get_object() );

		if ( ! $url ) {
			return;
		}

		$item->url = Url::remove_home_url( $url );
		$this->item_store->update_item( $item );
	}


	private function has_published_posts( int $user_id ): bool {
		$users = get_users(
			[
				'fields'              => 'ID',
				'include'             => [ $user_id ],
				'has_published_posts' => true,
			]
		);

		return ! empty( $users );
	}

	public function handles_object( $object ): bool {
		return $object instanceof \WP_User;
	}

	protected function get_object_by_id( int $object_id ) {
		return get_user_by( 'id', $object_id ) ?: null;
	}

	public function get_object_type(): string {
		return 'user';
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

	public function index_objects( ?array $subtypes = [], bool $force_recreate = false ): array {
		$error = null;

		if ( ! $this->is_enabled() ) {
			$error = new \WP_Error( 'author_sitemap_disabled', 'Author sitemap is disabled' );
		}

		$users_indexed = 0;

		if ( $this->is_enabled() ) {
			$users_indexed = $this->get_indexer()
				->set_force_recreate( $force_recreate )
				->run();

			if ( is_wp_error( $users_indexed ) ) {
				$error = $users_indexed;
			}
		}

		return [
			[
				'object_type'     => 'user',
				'object_subtype'  => null,
				'objects_indexed' => is_wp_error( $error ) ? 0 : $users_indexed,
				'error'           => $error,
			],
		];
	}

	public function is_enabled(): bool {
		return (bool) apply_filters( 'static_sitemap_authors_enabled', true );
	}

	public static function compare_objects( $a, $b ): int {
		if ( is_int( $a ) && 0 !== $a ) {
			$a = get_user_by( 'id', $a );
		}
		if ( is_int( $b ) && 0 !== $b ) {
			$b = get_user_by( 'id', $b );
		}

		if ( ! $a instanceof \WP_User || ! $b instanceof \WP_User ) {
			return 0;
		}

		$compare_callback = static function ( \WP_User $a, \WP_User $b ) {
			return $a->user_registered <=> $b->user_registered ?: $a->ID <=> $b->ID;
		};

		if ( has_filter( 'static_sitemap_user_compare_callback' ) ) {
			$original_compare_callback = $compare_callback;

			$compare_callback = apply_filters(
				'static_sitemap_user_compare_callback',
				$compare_callback
			);

			if ( ! is_callable( $compare_callback ) ) {
				$compare_callback = $original_compare_callback;
			}
		}

		try {
			return $compare_callback( $a, $b );
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

}
