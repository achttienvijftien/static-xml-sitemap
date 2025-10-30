<?php
/**
 * SitemapStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Store\EntityCache;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class SitemapStore
 */
class SitemapStore {

	use StoreTrait;

	public function __construct() {
		global $wpdb;

		$this->table       = "{$wpdb->prefix}sitemaps";
		$this->field_types = [
			'id'              => '%d',
			'object_type'     => '%s',
			'object_subtype'  => '%s',
			'last_modified'   => '%s',
			'last_object_id'  => '%d',
			'last_item_index' => '%d',
			'status'          => '%s',
			'item_count'      => '%d',
		];

		$this->cache = new EntityCache();
		$this->cache->set_tagger(
			fn( Sitemap $sitemap ) => [
				'object_type' => implode( ':', array_filter( [ $sitemap->object_type, $sitemap->object_subtype ] ) ),
			]
		);
	}

	public function get_by_object_type( string $object_type, $object_subtype = null ): ?Sitemap {
		global $wpdb;

		$tag = [
			'object_type' => implode( ':', array_filter( [ $object_type, $object_subtype ] ) ),
		];

		$sitemap = $this->cache->get_by_tag( $tag );

		if ( $sitemap ) {
			return $sitemap;
		}

		if ( $object_subtype ) {
			$sitemap = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}sitemaps WHERE object_type = %s AND object_subtype = %s",
					$object_type,
					$object_subtype
				)
			);

			return $sitemap ? new Sitemap( $sitemap ) : null;
		}

		$sitemap = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sitemaps WHERE object_type = %s AND object_subtype IS NULL",
				$object_type
			)
		);

		if ( ! $sitemap ) {
			return null;
		}

		$sitemap = new Sitemap( $sitemap );

		$this->cache->add( $sitemap );

		return $sitemap;
	}

	public function insert_sitemap( Sitemap $sitemap ) {
		return $this->insert( $sitemap );
	}

	public function update_sitemap( Sitemap $sitemap ) {
		return $this->update( $sitemap );
	}

	/**
	 * @return Sitemap[]
	 */
	public function get_all(): array {
		global $wpdb;

		$sitemaps = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sitemaps" );

		return $sitemaps ? array_map( fn( $sitemap ) => new Sitemap( $sitemap ), $sitemaps ) : [];
	}

	/**
	 *
	 * @param string $object_type
	 *
	 * @return Sitemap[]
	 */
	public function find_by_object_type( string $object_type ): array {
		global $wpdb;

		$sitemaps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sitemaps WHERE object_type = %s",
				$object_type
			)
		);

		return $sitemaps ? array_map( fn( $sitemap ) => new Sitemap( $sitemap ), $sitemaps ) : [];
	}

	/**
	 * @return Sitemap[]
	 */
	public function get_viewable_sitemaps(): array {
		global $wpdb;

		$sitemaps = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sitemaps WHERE status IN (%s, %s) AND item_count > 0",
				Sitemap::STATUS_INDEXED,
				Sitemap::STATUS_UPDATING
			)
		);

		return $sitemaps ? array_map( fn( $sitemap ) => new Sitemap( $sitemap ), $sitemaps ) : [];
	}

	public function get( int $id ): ?Sitemap {
		global $wpdb;

		$sitemap = $this->cache->get( $id );

		if ( $sitemap ) {
			return $sitemap;
		}

		$sitemap = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE id = %d", $id )
		);

		if ( ! $sitemap ) {
			return null;
		}

		$sitemap = new Sitemap( $sitemap );
		$this->cache->add( $sitemap );

		return $sitemap;
	}

	public function invalidate_cache( int $sitemap_id ): void {
		$this->cache->delete( $sitemap_id );
	}

}
