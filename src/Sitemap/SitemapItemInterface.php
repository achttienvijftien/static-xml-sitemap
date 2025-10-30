<?php
/**
 * SitemapItemInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityInterface;

/**
 * @template T
 */
interface SitemapItemInterface extends EntityInterface {

	/**
	 * @phpstan-return T|null
	 */
	public function get_object();

	public function get_item_index(): ?int;

	public function set_item_index( ?int $item_index ): void;

	public function get_next_item_index(): ?int;

	public function set_next_item_index( ?int $next_item_index ): void;

	public function get_url(): string;

	public function get_sitemap_id(): ?int;

	public function get_object_id(): ?int;

	public function get_modified(): ?string;

	/**
	 * @phpstan-param T         $object
	 *
	 * @param \WP_Post|\WP_User $object
	 * @param Sitemap           $sitemap
	 *
	 * @return self|null
	 */
	public static function for_object( $object, Sitemap $sitemap ): ?self;

	public function get_field( string $field );

}
