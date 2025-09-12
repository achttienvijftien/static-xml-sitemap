<?php
/**
 * UserItem
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

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
		// TODO: Implement get_modified() method.
	}
}
