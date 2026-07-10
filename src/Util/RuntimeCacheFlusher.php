<?php
/**
 * RuntimeCacheFlusher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class RuntimeCacheFlusher
 */
class RuntimeCacheFlusher {

	public function flush_all(): void {
		global $wp_object_cache, $wpdb;

		$wpdb->queries = [];

		$this->wp_cache_flush_runtime();

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		if ( property_exists( $wp_object_cache, 'group_ops' ) ) {
			$wp_object_cache->group_ops = [];
		}
		if ( property_exists( $wp_object_cache, 'stats' ) ) {
			$wp_object_cache->stats = [];
		}
		if ( property_exists( $wp_object_cache, 'memcache_debug' ) ) {
			$wp_object_cache->memcache_debug = [];
		}
		if ( property_exists( $wp_object_cache, 'cache' ) ) {
			$wp_object_cache->cache = [];
		}

		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			call_user_func( [ $wp_object_cache, '__remoteset' ] );
		}
	}

	private function wp_cache_flush_runtime(): void {
		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_runtime' ) ) {
			wp_cache_flush_runtime();

			return;
		}

		if ( ! wp_using_ext_object_cache() ) {
			wp_cache_flush();
		}
	}
}
