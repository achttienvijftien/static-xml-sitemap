<?php
/**
 * WordPressSeo
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Query as PostQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Query as TermQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermCache;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Query as UserQuery;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\DateTime;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class WordPressSeo
 */
class WordPressSeo {

	private const WPSEO_OPTIONS = [
		'enable_xml_sitemap'           => 'wpseo',
		'disable-author'               => 'wpseo_titles',
		'noindex-author-wpseo'         => 'wpseo_titles',
		'noindex-author-noposts-wpseo' => 'wpseo_titles',
		'/noindex-tax-.+/'             => 'wpseo_titles',
		'disable-post_format'          => 'wpseo_titles',
	];

	private PostWatcher $post_watcher;
	private UserIndexer $user_indexer;
	private UserWatcher $user_watcher;
	private TermIndexer $term_indexer;
	private TermWatcher $term_watcher;
	private TermItemStore $term_item_store;

	private ?array $accessible_post_types = null;
	private ?array $indexable_post_statuses = null;
	private ?array $excluded_post_ids = null;
	private ?array $excluded_term_ids = null;
	private TermCache $term_cache;

	public function __construct(
		PostWatcher $post_watcher,
		UserIndexer $user_indexer,
		UserWatcher $user_watcher,
		TermIndexer $term_indexer,
		TermWatcher $term_watcher,
		TermItemStore $term_item_store
	) {
		$this->post_watcher    = $post_watcher;
		$this->user_indexer    = $user_indexer;
		$this->user_watcher    = $user_watcher;
		$this->term_indexer    = $term_indexer;
		$this->term_watcher    = $term_watcher;
		$this->term_item_store = $term_item_store;
		$this->term_cache      = new TermCache();
	}

	public function add_hooks(): void {
		add_filter( 'static_sitemap_enabled', [ $this, 'sitemaps_enabled' ] );

		add_filter( 'static_sitemap_post_types', [ $this, 'post_types' ] );
		add_filter( 'static_sitemap_post_indexable', [ $this, 'post_indexable' ], 10, 2 );
		add_filter( 'static_sitemap_post_statuses', [ $this, 'post_statuses' ], 10, 2 );
		add_filter( 'static_sitemap_excluded_posts', [ $this, 'excluded_posts' ], 10, 2 );
		add_filter( 'static_sitemap_posts_clauses', [ $this, 'posts_clauses' ], 10, 2 );
		add_filter( 'static_sitemap_posts_orderby', [ $this, 'posts_orderby' ] );
		add_filter( 'static_sitemap_posts_pagination_order', [ $this, 'posts_pagination_order' ] );
		add_filter( 'static_sitemap_post_url', [ $this, 'post_url' ], 10, 2 );
		add_filter( 'static_sitemap_post_item_data', [ $this, 'post_item_data' ], 10, 2 );
		add_filter( 'static_sitemap_post_invalidations', [ $this, 'post_invalidations' ], 10, 2 );

		add_filter( 'static_sitemap_authors_enabled', [ $this, 'authors_enabled' ] );
		add_filter( 'static_sitemap_authors_clauses', [ $this, 'authors_clauses' ], 10, 2 );
		add_filter( 'static_sitemap_authors_orderby', [ $this, 'authors_orderby' ] );
		add_filter( 'static_sitemap_authors_pagination_order', [ $this, 'authors_pagination_order' ] );
		add_filter( 'static_sitemap_authors_query_field', [ $this, 'authors_query_field' ], 10, 2 );
		add_filter( 'static_sitemap_user_compare_callback', [ $this, 'user_compare_callback' ] );
		add_filter( 'static_sitemap_user_modified', [ $this, 'user_modified' ], 10, 2 );
		add_filter( 'static_sitemap_user_indexable', [ $this, 'user_indexable' ], 10, 2 );
		add_filter( 'static_sitemap_user_item_data', [ $this, 'user_item_data' ], 10, 2 );
		add_filter( 'static_sitemap_user_invalidations', [ $this, 'user_invalidations' ], 10, 2 );

		add_filter( 'static_sitemap_excluded_terms', [ $this, 'excluded_terms' ] );
		add_filter( 'static_sitemap_taxonomies', [ $this, 'taxonomies' ] );
		add_filter( 'static_sitemap_term_indexable', [ $this, 'term_indexable' ], 10, 2 );
		add_filter( 'static_sitemap_term_url', [ $this, 'term_url' ], 10, 2 );
		add_filter( 'static_sitemap_term_item_data', [ $this, 'term_item_data' ], 10, 2 );
		add_filter( 'static_sitemap_term_invalidations', [ $this, 'term_invalidations' ], 10, 2 );
		add_filter( 'static_sitemap_terms_query', [ $this, 'terms_query' ] );
		add_filter( 'static_sitemap_terms_clauses', [ $this, 'terms_clauses' ], 10, 2 );
		add_filter( 'static_sitemap_terms_orderby', [ $this, 'terms_orderby' ] );
		add_filter( 'static_sitemap_terms_query_field', [ $this, 'terms_query_field' ], 10, 2 );
		add_action( 'static_sitemap_terms_update_last_modified', [ $this, 'terms_update_last_modified' ] );

		add_filter( 'static_sitemap_force_queue_add', [ $this, 'force_queue_add' ], 10, 2 );

		$this->post_watcher->add_hooks();
		$this->user_indexer->add_hooks();
		$this->user_watcher->add_hooks();
		$this->term_indexer->add_hooks();
		$this->term_watcher->add_hooks();

		$this->disable_wpseo_sitemaps();
	}

	public function is_activated(): bool {
		return defined( 'WPSEO_VERSION' ) && function_exists( 'YoastSEO' );
	}

	public function sitemaps_enabled(): bool {
		return (bool) ( $this->get_wpseo_option( 'enable_xml_sitemap' ) ?? false );
	}

	private function get_wpseo_option( string $name, $default = null ) {
		$wpseo_option = $this->get_wpseo_option_name( $name );

		if ( null === $wpseo_option ) {
			return null;
		}

		$option_value = (array) get_option( $wpseo_option, null );

		if ( ! key_exists( $name, $option_value ) ) {
			return $default;
		}

		return $option_value[ $name ];
	}

	private function get_wpseo_option_name( string $name ): ?string {
		if ( isset( self::WPSEO_OPTIONS[ $name ] ) ) {
			return self::WPSEO_OPTIONS[ $name ];
		}

		$name_regexes = array_filter(
			array_keys( self::WPSEO_OPTIONS ),
			fn( $key ) => '/' === $key[0]
		);

		foreach ( $name_regexes as $name_regex ) {
			if ( preg_match( $name_regex, $name ) ) {
				return self::WPSEO_OPTIONS[ $name_regex ];
			}
		}

		return null;
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
	 * @param bool|mixed $indexable
	 * @param \WP_Post   $post
	 *
	 * @return bool|mixed
	 */
	public function post_indexable( $indexable, \WP_Post $post ) {
		if ( ! $indexable ) {
			return $indexable;
		}

		if ( ! $this->is_post_status_indexable( $post->post_status, $post->post_type ) ) {
			return false;
		}

		if ( in_array( $post->ID, $this->get_excluded_post_ids( $post->post_type ) ) ) {
			return false;
		}

		if ( '1' === get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return false;
		}

		if ( null === $this->post_url( get_permalink( $post->ID ), $post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param int[]|mixed $excluded_post_ids
	 * @param string      $post_type
	 *
	 * @return array
	 */
	public function excluded_posts( $excluded_post_ids, string $post_type ): array {
		if ( ! is_array( $excluded_post_ids ) ) {
			$excluded_post_ids = [];
		}

		return array_unique(
			array_merge( $excluded_post_ids, $this->get_excluded_post_ids( $post_type ) )
		);
	}

	private function get_excluded_post_ids( string $post_type ): array {
		if ( isset( $this->excluded_post_ids[ $post_type ] ) ) {
			return $this->excluded_post_ids[ $post_type ];
		}

		$this->excluded_post_ids[ $post_type ] = [];

		$page_on_front_id = ( 'page' === $post_type ) ? (int) get_option( 'page_on_front' ) : 0;
		if ( $page_on_front_id > 0 ) {
			$this->excluded_post_ids[ $post_type ][] = $page_on_front_id;
		}

		$filtered_excluded_post_ids = apply_filters(
			'wpseo_exclude_from_sitemap_by_post_ids',
			$this->excluded_post_ids[ $post_type ],
		);

		if ( is_array( $filtered_excluded_post_ids ) ) {
			$this->excluded_post_ids[ $post_type ] = $filtered_excluded_post_ids;
		}

		$this->excluded_post_ids[ $post_type ] = array_map( 'intval', $this->excluded_post_ids[ $post_type ] );

		$page_for_posts_id = ( 'page' === $post_type ) ? (int) get_option( 'page_for_posts' ) : 0;
		if ( $page_for_posts_id > 0 ) {
			$this->excluded_post_ids[ $post_type ][] = $page_for_posts_id;
		}

		return $this->excluded_post_ids[ $post_type ];
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

	public function posts_clauses( $clauses, PostQuery $query ): array {
		global $wpdb;

		if ( $query->post_type !== 'attachment' ) {
			return $clauses;
		}

		$post_status     = $query->post_status ?? [];
		$parent_statuses = array_map( 'esc_sql', array_diff( $post_status, [ 'inherit' ] ) );

		if ( empty( $parent_statuses ) ) {
			return $clauses;
		}

		$clauses['join'][]  = "LEFT JOIN $wpdb->posts AS p2 ON ($wpdb->posts.post_parent = p2.ID)";
		$clauses['where'][] = "(p2.post_status IN ('"
			. implode( "','", $parent_statuses )
			. "') AND p2.post_password = '')";

		return $clauses;
	}

	public function posts_orderby(): string {
		return 'modified';
	}

	public function posts_pagination_order(): string {
		return 'DESC';
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

		return $url;
	}

	/**
	 * @param mixed|array $item_data
	 * @param \WP_Post    $post
	 *
	 * @return mixed|array
	 */
	public function post_item_data( $item_data, \WP_Post $post ) {
		$url['loc'] = $item_data['url'];
		$url['chf'] = 'daily';
		$url['pri'] = 1;
		$modified   = max( $post->post_modified_gmt, $post->post_date_gmt );

		if ( $modified !== '0000-00-00 00:00:00' ) {
			$url['mod'] = $modified;
		}

		$url = apply_filters( 'wpseo_sitemap_entry', $url, 'post', $post );

		if ( ! is_array( $url ) || empty( $url['loc'] ) ) {
			return null;
		}

		$item_data['url'] = $url['loc'];

		return $item_data;
	}

	public function post_invalidations( int $invalidations, int $events ): int {
		if ( $invalidations & Invalidations::OBJECT_EXISTS ) {
			return $invalidations;
		}

		// Make sure reindex is only triggered by updates to post_modified_gmt.
		$invalidations &= ~Invalidations::ITEM_INDEX;

		if ( $events & PostWatcher::NOINDEX_META_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & PostWatcher::CANONICAL_META_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
			$invalidations |= Invalidations::ITEM_URL;
		}
		if ( $events & PostWatcher::POST_MODIFIED_UPDATED ) {
			$invalidations |= Invalidations::ITEM_INDEX;
		}

		return $invalidations;
	}

	public function authors_enabled( $enabled ): bool {
		if ( ! $enabled ) {
			return $enabled;
		}

		return ! $this->get_wpseo_option( 'disable-author' )
			&& ! $this->get_wpseo_option( 'noindex-author-wpseo' );
	}

	public function authors_clauses( $clauses, UserQuery $query ): array {
		global $wpdb;

		$after_value = null;

		if ( 'modified' === $query->orderby ) {
			$after_value = isset( $query->after['value'] )
				? DateTime::strtotime( $query->after['value'] )
				: null;
		}

		if ( 'modified' === $query->orderby && $after_value ) {
			$clauses['where']['after'] = $wpdb->prepare(
				'(wpseo_um_profile_updated.meta_value * 1, u.ID) > (%d, %d)',
				$after_value,
				$query->after['id']
			);
		}

		if ( 'modified' === $query->orderby ) {
			$clauses['orderby'] = [ 'wpseo_um_profile_updated.meta_value * 1', 'u.ID' ];
		}

		$profile_updated_join = "LEFT JOIN $wpdb->usermeta AS wpseo_um_profile_updated" .
			' ON wpseo_um_profile_updated.user_id = u.ID' .
			" AND wpseo_um_profile_updated.meta_key = '_yoast_wpseo_profile_updated'";

		if ( 'modified' === $query->orderby || in_array( 'modified', $clauses['fields'], true ) ) {
			$clauses['join']['meta_profile_updated'] = $profile_updated_join;
		}

		if ( null === $query->indexable ) {
			return $clauses;
		}

		$noindex_author_noposts = $this->get_wpseo_option( 'noindex-author-noposts-wpseo', true );

		$clauses['where'][] = '(wpseo_um_profile_updated.meta_value IS NOT NULL)';
		$clauses['where'][] = "(wpseo_um_lvl.meta_value != '0')";
		$clauses['where'][] = "(wpseo_um_noindex.meta_value IS NULL " .
			"OR wpseo_um_noindex.meta_value != 'on')";

		$user_level_meta_key = $wpdb->get_blog_prefix() . 'user_level';

		$clauses['join']['meta_noindex'] =
			"LEFT JOIN $wpdb->usermeta AS wpseo_um_noindex" .
			' ON wpseo_um_noindex.user_id = u.ID' .
			" AND wpseo_um_noindex.meta_key = 'wpseo_noindex_author'";

		$clauses['join']['meta_profile_updated'] = $profile_updated_join;

		$clauses['join']['meta_user_level'] = $wpdb->prepare(
			"LEFT JOIN $wpdb->usermeta AS wpseo_um_lvl" .
			' ON wpseo_um_lvl.user_id = u.ID' .
			' AND wpseo_um_lvl.meta_key = %s',
			$user_level_meta_key
		);

		if ( $noindex_author_noposts ) {
			$author_archive_post_types = YoastSEO()->helpers->author_archive->get_author_archive_post_types();
			$author_archive_post_types = esc_sql( $author_archive_post_types );

			$clauses['where'][] = '(u.ID IN (' .
				'SELECT DISTINCT wp_posts.post_author ' .
				'FROM wp_posts ' .
				"WHERE wp_posts.post_status = 'publish' " .
				"AND wp_posts.post_type IN ('"
				. implode( "','", $author_archive_post_types )
				. "')" .
				"))";
		} else {
			$cap_meta_key = $wpdb->get_blog_prefix() . 'capabilities';

			$clauses['join'][] = $wpdb->prepare(
				"LEFT JOIN $wpdb->usermeta AS wpseo_um_cap" .
				" ON wpseo_um_cap.user_id = u.ID AND wpseo_um_cap.meta_key = %s",
				$cap_meta_key
			);

			$capability_like = array_map(
				fn( $role ) => $wpdb->prepare(
					'wpseo_um_cap.meta_value LIKE %s',
					'%' . $wpdb->esc_like( '"' . $role . '"' ) . '%'
				),
				$this->get_roles_with_edit_posts_cap()
			);

			if ( $capability_like ) {
				$clauses['where'][] = [
					'relation' => 'OR',
					...$capability_like,
				];
			}
		}

		return $clauses;
	}

	public function authors_orderby(): string {
		return 'modified';
	}

	public function authors_pagination_order(): string {
		return 'DESC';
	}

	/**
	 * @param string|mixed $column
	 * @param string       $field
	 *
	 * @return string|mixed
	 */
	public function authors_query_field( $column, string $field ) {
		global $wpdb;

		if ( 'modified' === $field ) {
			return $wpdb->prepare(
				'FROM_UNIXTIME(wpseo_um_profile_updated.meta_value, %s)',
				'%Y-%m-%d %H:%i:%s'
			);
		}

		return $column;
	}

	private function get_roles_with_edit_posts_cap(): array {
		$roles = [];

		foreach ( wp_roles()->roles as $role => $role_data ) {
			$role_caps = array_keys( array_filter( $role_data['capabilities'] ) );

			if ( in_array( 'edit_posts', $role_caps, true ) ) {
				$roles[] = $role;
				break;
			}
		}

		return $roles;
	}

	public function user_compare_callback(): callable {
		return static function ( \WP_User $a, \WP_User $b ) {
			$a_profile_updated = (int) get_user_meta( $a->ID, '_yoast_wpseo_profile_updated', true );
			$b_profile_updated = (int) get_user_meta( $b->ID, '_yoast_wpseo_profile_updated', true );

			return $a_profile_updated <=> $b_profile_updated ?: $a->ID <=> $b->ID;
		};
	}

	public function user_modified( $modified, \WP_User $user ): ?string {
		$profile_updated = get_user_meta( $user->ID, '_yoast_wpseo_profile_updated', true );

		return $profile_updated ? gmdate( 'Y-m-d H:i:s', $profile_updated ) : null;
	}

	/**
	 * @param bool|mixed $indexable
	 * @param \WP_User   $user
	 *
	 * @return bool|mixed
	 */
	public function user_indexable( $indexable, \WP_User $user ) {
		if ( ! $indexable ) {
			return $indexable;
		}

		if ( empty( apply_filters( 'wpseo_sitemap_exclude_author', [ $user ] ) ) ) {
			return false;
		}

		// User is indexable, make sure it has _yoast_wpseo_profile_updated meta, otherwise the
		// item index cannot be determined.
		if ( '' === get_user_meta( $user->ID, '_yoast_wpseo_profile_updated', true ) ) {
			update_user_meta( $user->ID, '_yoast_wpseo_profile_updated', time() );
		}

		return true;
	}

	/**
	 * @param mixed|array $item_data
	 * @param \WP_User    $user
	 *
	 * @return mixed|array
	 */
	public function user_item_data( $item_data, \WP_User $user ) {
		$url['loc'] = $item_data['url'];
		$url['chf'] = 'daily';
		$url['pri'] = 1;
		$mod        = time();
		if ( isset( $user->_yoast_wpseo_profile_updated ) ) {
			$mod = $user->_yoast_wpseo_profile_updated;
		}
		$url['mod'] = gmdate( DATE_W3C, $mod );

		$url = apply_filters( 'wpseo_sitemap_entry', $url, 'user', $user );

		if ( ! is_array( $url ) || empty( $url['loc'] ) ) {
			return null;
		}

		$item_data['url'] = $url['loc'];

		return $item_data;
	}

	public function user_invalidations( int $invalidations, int $events ): int {
		if ( $invalidations & Invalidations::OBJECT_EXISTS ) {
			return $invalidations;
		}

		// Make sure reindex is only triggered by updates to _yoast_wpseo_profile_updated meta.
		$invalidations &= ~Invalidations::ITEM_INDEX;

		if ( $events & UserWatcher::PROFILE_UPDATED_UPDATED ) {
			$invalidations |= Invalidations::ITEM_INDEX;
		}
		if ( $events & UserWatcher::USER_LEVEL_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & UserWatcher::NOINDEX_AUTHOR_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & UserWatcher::USER_ROLES_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}

		return $invalidations;
	}

	/**
	 * @param int[]|mixed $excluded_term_ids
	 *
	 * @return array
	 */
	public function excluded_terms( $excluded_term_ids ): array {
		if ( ! is_array( $excluded_term_ids ) ) {
			$excluded_term_ids = [];
		}

		return array_unique( array_merge( $excluded_term_ids, $this->get_excluded_term_ids() ) );
	}

	private function get_excluded_term_ids(): array {
		if ( isset( $this->excluded_term_ids ) ) {
			return $this->excluded_term_ids;
		}

		$excluded_term_ids = apply_filters( 'wpseo_exclude_from_sitemap_by_term_ids', [] );

		if ( ! is_array( $excluded_term_ids ) ) {
			$excluded_term_ids = [];
		}

		$this->excluded_term_ids = array_map( 'intval', $excluded_term_ids );

		return $this->excluded_term_ids;
	}

	public function taxonomies( $taxonomies ): array {
		$taxonomies = (array) $taxonomies;

		$indexable_taxonomies = [];

		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy = get_taxonomy( $taxonomy );

			if ( false === $taxonomy || ! $taxonomy->public ) {
				continue;
			}

			if ( ! $this->is_valid_taxonomy( $taxonomy->name ) ) {
				continue;
			}

			$indexable_taxonomies[] = $taxonomy->name;
		}

		return $indexable_taxonomies;
	}

	private function is_valid_taxonomy( string $taxonomy ): bool {
		if ( $this->get_wpseo_option( "noindex-tax-$taxonomy" ) === true ) {
			return false;
		}

		if ( in_array( $taxonomy, [ 'link_category', 'nav_menu', 'wp_pattern_category' ], true ) ) {
			return false;
		}

		if ( $taxonomy === 'post_format' && $this->get_wpseo_option( 'disable-post_format', false ) ) {
			return false;
		}

		if ( apply_filters( 'wpseo_sitemap_exclude_taxonomy', false, $taxonomy ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param bool|mixed $indexable
	 * @param \WP_Term   $term
	 *
	 * @return bool|mixed
	 */
	public function term_indexable( $indexable, \WP_Term $term ) {
		if ( ! $indexable ) {
			return $indexable;
		}

		if ( 'noindex' === get_term_meta( $term->term_id, 'wpseo_noindex', true ) ) {
			return false;
		}

		$hide_empty     = apply_filters( 'wpseo_sitemap_exclude_empty_terms', true, [ $term->taxonomy ] );
		$hide_empty_tax = apply_filters( 'wpseo_sitemap_exclude_empty_terms_taxonomy', $hide_empty, $term->taxonomy );

		if ( ! $hide_empty_tax ) {
			return $indexable;
		}

		$hierarchical = is_taxonomy_hierarchical( $term->taxonomy );

		if ( ! $hierarchical ) {
			return $term->count > 0;
		}

		$counts = $this->term_cache->get_hierarchical_term_count( $term->taxonomy );

		foreach ( $counts as ['term_id' => $term_id, 'count' => $count] ) {
			if ( $term_id === $term->term_id ) {
				return $count > 0;
			}
		}

		return true;
	}

	/**
	 * @param string|mixed $url
	 * @param \WP_Term     $term
	 *
	 * @return mixed
	 */
	public function term_url( $url, \WP_Term $term ) {
		$canonical = get_term_meta( $term->term_id, 'wpseo_canonical', true );

		if ( '' !== $canonical && $url !== $canonical ) {
			return null;
		}

		return $url;
	}

	/**
	 * @param mixed|array $item_data
	 * @param \WP_Term    $term
	 *
	 * @return mixed|array
	 */
	public function term_item_data( $item_data, \WP_Term $term ) {
		$url['loc'] = $item_data['url'];
		$url['chf'] = 'daily';
		$url['pri'] = 1;

		$url = apply_filters( 'wpseo_sitemap_entry', $url, 'term', $term );

		if ( ! is_array( $url ) || empty( $url['loc'] ) ) {
			return null;
		}

		$item_data['url'] = $url['loc'];

		return $item_data;
	}

	public function term_invalidations( int $invalidations, int $events ): int {
		if ( $invalidations & Invalidations::OBJECT_EXISTS ) {
			return $invalidations;
		}

		if ( $events & TermWatcher::NOINDEX_META_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & TermWatcher::CANONICAL_META_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
			$invalidations |= Invalidations::ITEM_URL;
		}
		if ( $events & TermWatcher::TERM_LAST_MODIFIED_UPDATED ) {
			$invalidations |= Invalidations::ITEM_LAST_MODIFIED;
		}
		if ( $events & TermWatcher::CHILD_TERM_COUNT_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}

		return $invalidations;
	}

	/**
	 * @param TermQuery|mixed $query
	 *
	 * @return TermQuery|mixed
	 */
	public function terms_query( $query ) {
		if ( ! $query instanceof TermQuery || ! $query->indexable ) {
			return $query;
		}

		$hide_empty     = apply_filters( 'wpseo_sitemap_exclude_empty_terms', true, [ $query->taxonomy ] );
		$hide_empty_tax = apply_filters( 'wpseo_sitemap_exclude_empty_terms_taxonomy', $hide_empty, $query->taxonomy );

		$query->set_hide_empty( (bool) $hide_empty_tax );
		$query->set_hierarchical( true );

		return $query;
	}

	public function terms_clauses( $clauses, TermQuery $query ): array {
		global $wpdb;

		if ( in_array( 'modified', $clauses['fields'], true ) ) {
			if ( ! isset( $clauses['join']['sitemap_items'] ) ) {
				$clauses['join']['sitemap_items'] = "JOIN {$wpdb->prefix}sitemap_terms si" .
					' ON tt.term_taxonomy_id = si.term_taxonomy_id';
			}
		}

		if ( in_array( 'name', $clauses['fields'], true ) || 'name' === $query->orderby ) {
			if ( ! isset( $clauses['join']['terms'] ) ) {
				$clauses['join']['terms'] = "JOIN $wpdb->terms t" .
					' ON t.term_id = tt.term_id';
			}
		}

		if ( 'modified' === $query->orderby && $query->after ) {
			$clauses['where']['after'] = $wpdb->prepare(
				'(si.last_modified, tt.term_taxonomy_id) > (%s, %d)',
				$query->after['value'],
				$query->after['id']
			);

			$clauses['orderby'] = [ 'si.last_modified', 'tt.term_taxonomy_id' ];
		}

		return $clauses;
	}

	public function terms_orderby(): string {
		return 'name';
	}

	/**
	 * @param string|mixed $column
	 * @param string       $field
	 *
	 * @return string|mixed
	 */
	public function terms_query_field( $column, string $field ) {
		if ( 'modified' === $field ) {
			return 'si.last_modified';
		}
		if ( 'name' === $field ) {
			return 't.name';
		}

		return $column;
	}

	/**
	 * @param TermItem[]|mixed $items
	 */
	public function terms_update_last_modified( $items ): void {
		$is_term_item = static fn( $item ) => $item instanceof TermItem;

		if ( ! is_array( $items ) || array_filter( $items, $is_term_item ) !== $items ) {
			return;
		}

		foreach ( $items as $item ) {
			$last_modified = $this->get_term_item_last_modified( $item );

			if ( $last_modified && $last_modified->post_modified_gmt !== $item->last_modified ) {
				$item->last_modified           = $last_modified->post_modified_gmt;
				$item->last_modified_object_id = $last_modified->ID;

				$this->term_item_store->update_item( $item );
			}
		}
	}

	private function get_term_item_last_modified( TermItem $item ): ?\stdClass {
		global $wpdb;

		$post_statuses = apply_filters( 'wpseo_sitemap_post_statuses', [ 'publish' ], '1' );

		if ( ! is_array( $post_statuses ) || empty( $post_statuses ) ) {
			$post_statuses = [ 'publish' ];
		}

		if ( in_array( 'inherit', $post_statuses, true ) ) {
			$post_statuses[] = 'inherit';
		}

		$post_statuses = esc_sql( $post_statuses );

		$last_modified = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT p.ID, p.post_modified_gmt ' .
				'FROM wp_posts AS p ' .
				'    INNER JOIN wp_term_relationships AS term_rel ' .
				'ON term_rel.object_id = p.ID ' .
				'    INNER JOIN wp_term_taxonomy AS term_tax ' .
				'    ON term_tax.term_taxonomy_id = term_rel.term_taxonomy_id ' .
				'    AND term_tax.term_taxonomy_id = %d ' .
				'WHERE p.post_status IN (\'' . implode( "', '", $post_statuses ) . '\') ' .
				'  AND p.post_password = \'\' ' .
				'ORDER BY p.post_modified_gmt DESC, p.ID DESC ' .
				'LIMIT 1',
				$item->term_taxonomy_id,
			)
		);

		return $last_modified instanceof \stdClass ? $last_modified : null;
	}

	/**
	 * @param mixed|bool $force_queue_add
	 * @param string     $object_type
	 *
	 * @return mixed|bool
	 */
	public function force_queue_add( $force_queue_add, string $object_type ) {
		if ( 'term' === $object_type ) {
			return true;
		}

		return $force_queue_add;
	}

	public function disable_wpseo_sitemaps(): void {
		$sitemaps = $GLOBALS['wpseo_sitemaps'] ?? null;
		$router   = $sitemaps->router ?? null;

		if ( $sitemaps instanceof \WPSEO_Sitemaps ) {
			remove_action( 'after_setup_theme', [ $sitemaps, 'init_sitemaps_providers' ] );
			remove_action( 'after_setup_theme', [ $sitemaps, 'reduce_query_load' ], 99 );
			remove_action( 'pre_get_posts', [ $sitemaps, 'redirect' ], 1 );
			remove_action( 'wpseo_hit_sitemap_index', [ $sitemaps, 'hit_sitemap_index' ] );

			if ( property_exists( $sitemaps, 'providers' ) ) {
				$sitemaps->providers = [];
			}
		}

		if ( $router instanceof \WPSEO_Sitemaps_Router ) {
			remove_action( 'yoast_add_dynamic_rewrite_rules', [ $router, 'add_rewrite_rules' ] );
			remove_filter( 'query_vars', [ $router, 'add_query_vars' ] );
			remove_filter( 'redirect_canonical', [ $router, 'redirect_canonical' ] );
			remove_action( 'template_redirect', [ $router, 'template_redirect' ], 0 );
		}

		// Note: the sitemap cache used to be unhooked here as well, but we keep that in place
		// for now to let the News sitemap (and its cache) to function as-is.
	}

	public function is_post_status_indexable( string $post_status, string $post_type ): bool {
		$post_statuses = $this->get_indexable_post_statuses( $post_type );

		return in_array( $post_status, $post_statuses, true );
	}

	protected function get_indexable_post_statuses( string $post_type ): array {
		if ( isset( $this->indexable_post_statuses[ $post_type ] ) ) {
			return $this->indexable_post_statuses[ $post_type ];
		}

		$post_statuses = apply_filters( 'wpseo_sitemap_post_statuses', [ 'publish' ], $post_type );

		if ( ! is_array( $post_statuses ) || empty( $post_statuses ) ) {
			$post_statuses = [ 'publish' ];
		}

		if ( $post_type === 'attachment' && ! in_array( 'inherit', $post_statuses, true ) ) {
			$post_statuses[] = 'inherit';
		}

		$this->indexable_post_statuses[ $post_type ] = $post_statuses;

		return $this->indexable_post_statuses[ $post_type ];
	}

}
