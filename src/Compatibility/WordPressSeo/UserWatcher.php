<?php
/**
 * UserWatcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

use AchttienVijftien\Plugin\StaticXMLSitemap\User\SitemapProvider as UserSitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Watcher as BaseUserWatcher;

/**
 * Class UserWatcher
 */
class UserWatcher {

	public const PROFILE_UPDATED_UPDATED = 1 << 10;
	public const USER_LEVEL_UPDATED      = 1 << 11;
	public const NOINDEX_AUTHOR_UPDATED  = 1 << 12;
	public const USER_ROLES_UPDATED      = 1 << 13;

	private BaseUserWatcher $watcher;
	private array $meta_key_events;

	public function __construct( BaseUserWatcher $base_watcher, UserSitemapProvider $provider, UserItemStore $user_item_store ) {
		global $wpdb;

		$this->watcher         = $base_watcher;
		$this->user_item_store = $user_item_store;
		$this->provider        = $provider;
		$this->meta_key_events = [
			'_yoast_wpseo_profile_updated'          => self::PROFILE_UPDATED_UPDATED,
			$wpdb->get_blog_prefix() . 'user_level' => self::USER_LEVEL_UPDATED,
			'wpseo_noindex_author'                  => self::NOINDEX_AUTHOR_UPDATED,
		];
	}

	public function add_hooks(): void {
		add_action( 'updated_user_meta', [ $this, 'updated_user_meta' ], 10, 4 );
		add_action( 'added_user_meta', [ $this, 'updated_user_meta' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'updated_user_meta' ], 10, 4 );
		add_action( 'add_user_role', [ $this, 'update_user_role' ] );
		add_action( 'remove_user_role', [ $this, 'update_user_role' ] );
	}

	public function updated_user_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( ! key_exists( $meta_key, $this->meta_key_events ) ) {
			return;
		}

		$this->watcher->add_events( $object_id, $this->meta_key_events[ $meta_key ] );
	}

	/**
	 * @param int $user_id The user ID.
	 */
	public function update_user_role( int $user_id ): void {
		$this->watcher->add_events( $user_id, self::USER_ROLES_UPDATED );
	}

}
