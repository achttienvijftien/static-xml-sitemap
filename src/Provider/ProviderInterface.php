<?php
/**
 * ProviderInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Provider
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Provider;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\UserItem;

interface ProviderInterface {

	public function process_watches( int $object_id, int $events ): void;

	/**
	 * @param \WP_User|\WP_Post|\WP_Term $object
	 *
	 * @return bool
	 */
	public function handles_object( $object ): bool;

	public function get_object_type(): string;

	public static function compare_objects( $a, $b ): int;

	public function add_to_sitemap( $object ): void;

	/**
	 * @param \WP_Post|\WP_User|int $object
	 *
	 * @return PostItem|UserItem|null
	 */
	public function get_item_for_object( $object );

	public function run_jobs( ?array $subtypes = null ): void;

	public function index_objects( ?array $subtypes = [], bool $force_recreate = false ): array;

	public function is_enabled(): bool;
}
