<?php
/**
 * WordPressSeo
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility;

/**
 * Class WordPressSeo
 */
class WordPressSeo {

	private const WPSEO_OPTIONS = [
		'enable_xml_sitemap'   => 'wpseo',
		'disable-author'       => 'wpseo_titles',
		'noindex-author-wpseo' => 'wpseo_titles',
	];

	private array $accessible_post_types;

	public function is_activated(): bool {
		return defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' );
	}

	public function sitemaps_enabled(): bool {
		return (bool) ( $this->get_wpseo_option( 'enable_xml_sitemap' ) ?? false );
	}

	private function get_wpseo_option( string $name ): ?string {
		$wpseo_option = self::WPSEO_OPTIONS[ $name ] ?? null;

		if ( null === $wpseo_option ) {
			return null;
		}

		$option_value = (array) get_option( $wpseo_option, null );

		if ( ! key_exists( $name, $option_value ) ) {
			return null;
		}

		return (string) $option_value[ $name ];
	}

	public function add_hooks(): void {
		add_filter( 'static_sitemap_post_types', [ $this, 'post_types' ] );
		add_filter( 'static_sitemap_post_statuses', [ $this, 'post_statuses' ], 10, 2 );
		add_filter( 'static_sitemap_excluded_posts', [ $this, 'excluded_posts' ], 10, 2 );
		add_filter( 'static_sitemap_posts_where', [ $this, 'posts_where' ], 10, 3 );
		add_filter( 'static_sitemap_posts_joins', [ $this, 'posts_joins' ], 10, 2 );
		add_filter( 'static_sitemap_post_url', [ $this, 'post_url' ], 10, 2 );
		add_filter( 'static_sitemap_enabled', [ $this, 'sitemaps_enabled' ] );
		add_filter( 'static_sitemap_author_enabled', [ $this, 'author_enabled' ] );
		add_action( 'plugins_loaded', [ $this, 'disable_wpseo_sitemaps' ], PHP_INT_MAX );
	}

	/**
	 * @param array|mixed $post_types
	 *
	 * @return array
	 */
	public function post_types( $post_types ): array {
		$post_types = (array) $post_types;

		return array_filter(
			$post_types,
			fn( $post_type ) => $this->is_post_type_accessible( $post_type )
				&& $this->is_post_type_indexable( $post_type )
				&& ! $this->is_post_type_excluded( $post_type )
		);
	}

	private function is_post_type_accessible( string $post_type ): bool {
		return in_array( $post_type, $this->get_accessible_post_types(), true );
	}

	private function get_accessible_post_types(): array {
		if ( ! isset( $this->accessible_post_types ) ) {
			$this->accessible_post_types = YoastSEO()->helpers->post_type->get_accessible_post_types();
		}

		return $this->accessible_post_types;
	}

	private function is_post_type_indexable( string $post_type ): bool {
		return YoastSEO()->helpers->post_type->is_indexable( $post_type );
	}

	private function is_post_type_excluded( string $post_type ): bool {
		return apply_filters( 'wpseo_sitemap_exclude_post_type', false, $post_type );
	}

	/**
	 * @param int[]|mixed $excluded_posts_ids
	 * @param string      $post_type
	 *
	 * @return array
	 */
	public function excluded_posts( $excluded_posts_ids, string $post_type ): array {
		if ( ! is_array( $excluded_posts_ids ) ) {
			$excluded_posts_ids = [];
		}

		$page_on_front_id = ( 'page' === $post_type ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( $page_on_front_id > 0 ) {
			$excluded_posts_ids[] = $page_on_front_id;
		}

		$excluded_posts_ids = apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', $excluded_posts_ids );

		if ( ! is_array( $excluded_posts_ids ) ) {
			$excluded_posts_ids = [];
		}

		$excluded_posts_ids = array_map( 'intval', $excluded_posts_ids );

		$page_for_posts_id = ( 'page' === $post_type ) ? (int) get_option( 'page_for_posts' ) : 0;
		if ( $page_for_posts_id > 0 ) {
			$excluded_posts_ids[] = $page_for_posts_id;
		}

		return array_unique( $excluded_posts_ids );
	}

	/**
	 * @param array|mixed $post_statuses
	 * @param string      $post_type
	 *
	 * @return array
	 */
	public function post_statuses( $post_statuses, string $post_type ): array {
		$post_statuses = (array) $post_statuses;

		$post_statuses = apply_filters( 'wpseo_sitemap_post_statuses', $post_statuses, $post_type );

		if ( ! is_array( $post_statuses ) || empty( $post_statuses ) ) {
			$post_statuses = [ 'publish' ];
		}

		if ( $post_type === 'attachment' && ! in_array( 'inherit', $post_statuses, true ) ) {
			$post_statuses[] = 'inherit';
		}

		return $post_statuses;
	}

	/**
	 * @param string|mixed $where
	 * @param string       $post_type
	 * @param array        $post_statuses
	 *
	 * @return string
	 */
	public function posts_where( $where, string $post_type, array $post_statuses ): string {
		if ( $post_type === 'attachment' ) {
			$parent_statuses = array_map( 'esc_sql', array_diff( $post_statuses, [ 'inherit' ] ) );

			$where .= " AND (p2.post_status IN ('"
				. implode( "','", $parent_statuses )
				. "') AND p2.post_password = '')";
		}

		return $where;
	}

	/**
	 * @param string|mixed $joins
	 * @param string       $post_type
	 *
	 * @return string
	 */
	public function posts_joins( $joins, string $post_type ): string {
		global $wpdb;

		if ( $post_type === 'attachment' ) {
			$joins .= " LEFT JOIN $wpdb->posts AS p2 ON ($wpdb->posts.post_parent = p2.ID) ";
		}

		return $joins;
	}

	/**
	 * @param string|mixed $url
	 * @param \WP_Post     $post
	 *
	 * @return mixed
	 */
	public function post_url( $url, \WP_Post $post ) {
		$url       = apply_filters( 'wpseo_xml_sitemap_post_url', $url, $post );
		$canonical = YoastSEO()->helpers->meta->get_value( 'canonical', $post->ID );

		if ( '' !== $canonical && $url !== $canonical ) {
			return null;
		}

		return apply_filters( 'wpseo_sitemap_entry', $url, 'post', $post );
	}

	public function author_enabled( $enabled ): bool {
		if ( ! $enabled ) {
			return $enabled;
		}

		return ! $this->get_wpseo_option( 'disable-author' )
			&& ! $this->get_wpseo_option( 'noindex-author-wpseo' );
	}

	public function disable_wpseo_sitemaps(): void {
		$sitemaps = $GLOBALS['wpseo_sitemaps'] ?? null;
		$router   = $sitemaps->router ?? null;
		$cache    = $sitemaps->cache ?? null;

		if ( $sitemaps instanceof \WPSEO_Sitemaps ) {
			remove_action( 'after_setup_theme', [ $sitemaps, 'init_sitemaps_providers' ] );
			remove_action( 'after_setup_theme', [ $sitemaps, 'reduce_query_load' ], 99 );
			remove_action( 'pre_get_posts', [ $sitemaps, 'redirect' ], 1 );
			remove_action( 'wpseo_hit_sitemap_index', [ $sitemaps, 'hit_sitemap_index' ] );
		}

		if ( $router instanceof \WPSEO_Sitemaps_Router ) {
			remove_action( 'yoast_add_dynamic_rewrite_rules', [ $router, 'add_rewrite_rules' ] );
			remove_filter( 'query_vars', [ $router, 'add_query_vars' ] );
			remove_filter( 'redirect_canonical', [ $router, 'redirect_canonical' ] );
			remove_action( 'template_redirect', [ $router, 'template_redirect' ], 0 );
		}

		if ( $cache instanceof \WPSEO_Sitemaps_Cache ) {
			remove_action( 'init', [ $cache, 'init' ] );
			remove_action( 'deleted_term_relationships', [ \WPSEO_Sitemaps_Cache::class, 'invalidate' ] );
			remove_action( 'update_option', [ \WPSEO_Sitemaps_Cache::class, 'clear_on_option_update' ] );
			remove_action( 'edited_terms', [ \WPSEO_Sitemaps_Cache::class, 'invalidate_helper' ] );
			remove_action( 'clean_term_cache', [ \WPSEO_Sitemaps_Cache::class, 'invalidate_helper' ] );
			remove_action( 'clean_object_term_cache', [ \WPSEO_Sitemaps_Cache::class, 'invalidate_helper' ] );
			remove_action( 'user_register', [ \WPSEO_Sitemaps_Cache::class, 'invalidate_author' ] );
			remove_action( 'delete_user', [ \WPSEO_Sitemaps_Cache::class, 'invalidate_author' ] );
			remove_action( 'shutdown', [ \WPSEO_Sitemaps_Cache::class, 'clear_queued' ] );
		}
	}

}
