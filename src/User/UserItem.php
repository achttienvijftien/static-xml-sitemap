<?php
/**
 * UserItem
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;

/**
 * Class UserItem
 */
class UserItem implements SitemapItemInterface {

	use SitemapItemTrait, EntityTrait;

	public ?int $user_id;

	/**
	 * UserItem constructor.
	 *
	 * @param array|object{id: int, user_id: int, sitemap_id: int, url: string, item_index: int|null, next_item_index: int|null} $data
	 */
	public function __construct( $data ) {
		$data = PropertyAccessor::create( $data );

		$this->id              = null !== $data->id ? (int) $data->id : null;
		$this->user_id         = (int) $data->user_id;
		$this->sitemap_id      = (int) $data->sitemap_id;
		$this->url             = $data->url;
		$this->item_index      = $data->item_index !== null ? (int) $data->item_index : null;
		$this->next_item_index = $data->next_item_index !== null ? (int) $data->next_item_index : null;
	}

	public function get_object() {
		return get_user_by( 'id', $this->user_id );
	}

	public function get_object_id(): ?int {
		return $this->user_id;
	}

	public function get_modified(): ?string {
		$user = $this->get_object();
		if ( null === $user ) {
			return null;
		}

		return apply_filters( 'static_sitemap_user_modified', $user->user_registered, $user );
	}

	/**
	 * @param \WP_User|int $object
	 * @param Sitemap      $sitemap
	 *
	 * @return UserItem|null
	 */
	public static function for_object( $object, Sitemap $sitemap ): ?UserItem {
		if ( is_int( $object ) ) {
			$object = get_user_by( 'id', $object );
		}

		if ( ! $object instanceof \WP_User ) {
			return null;
		}

		$data = [
			'user_id'    => $object->ID,
			'url'        => get_author_posts_url( $object->ID ),
			'sitemap_id' => $sitemap->id,
		];

		$data['url'] = apply_filters( 'static_sitemap_author_page_url', $data['url'], $object );

		if ( empty( $data['url'] ) || ! Url::is_site_url( $data['url'] ) ) {
			return null;
		}

		$data['url'] = Url::remove_home_url( $data['url'] );

		$data = apply_filters( 'static_sitemap_user_item_data', $data, $object );

		if ( ! is_array( $data ) || empty( $data['url'] ) ) {
			return null;
		}

		return new UserItem( $data );
	}

	public function __toString() {
		return 'UserItem { '
			. "id: $this->id, "
			. "user_id: $this->user_id, "
			. "sitemap_id: $this->sitemap_id, "
			. "url: $this->url, "
			. "item_index: $this->item_index, "
			. "next_item_index: $this->next_item_index"
			. ' }';
	}

	public static function compare_objects( UserItem $a, UserItem $b ): int {
		$a_user = $a->get_object();
		$b_user = $b->get_object();

		if ( null === $a_user || null === $b_user ) {
			return 0;
		}

		return SitemapProvider::compare_objects( $a_user, $b_user );
	}

	public function get_field( string $field ) {
		switch ( $field ) {
			case 'id':
				return $this->get_object_id();
			case 'modified':
				return $this->get_modified();
			default:
				$user = $this->get_object();

				return $user && property_exists( $user, $field ) ? $user->$field : null;
		}
	}
}
