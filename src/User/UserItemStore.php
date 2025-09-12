<?php
/**
 * UserItemStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\User
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class UserItemStore
 */
class UserItemStore {

	use StoreTrait, ItemStoreTrait;

	public function __construct() {
		global $wpdb;
		$this->table            = "{$wpdb->prefix}sitemap_users";
		$this->object_id_column = 'user_id';
	}
}
