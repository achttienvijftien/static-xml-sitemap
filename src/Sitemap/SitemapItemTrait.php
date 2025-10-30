<?php
/**
 * SitemapItemTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap;

/**
 * Trait SitemapItemTrait
 *
 * Common properties for sitemap items (posts and users)
 */
trait SitemapItemTrait {

	public ?int $sitemap_id;
	public ?string $url;
	public ?int $item_index;
	public ?int $next_item_index;

	public function get_item_index(): ?int {
		return $this->item_index;
	}

	public function set_item_index( ?int $item_index ): void {
		$this->item_index = $item_index;
	}

	public function get_next_item_index(): ?int {
		return $this->next_item_index;
	}

	public function set_next_item_index( ?int $next_item_index ): void {
		$this->next_item_index = $next_item_index;
	}

	public function get_url(): string {
		return home_url( $this->url );
	}

	public function get_sitemap_id(): ?int {
		return $this->sitemap_id;
	}

}
