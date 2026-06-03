<?php
/**
 * SitemapProvider
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Job\JobStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Logger\Logger;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\AbstractProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Store\ItemStoreInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\WatcherInterface;

/**
 * Class SitemapProvider
 */
class SitemapProvider extends AbstractProvider implements ProviderInterface {

	private ?array $taxonomies = null;
	private TermCache $cache;

	public function __construct(
		WatcherInterface $watcher,
		SitemapStore $sitemap_store,
		ItemStoreInterface $item_store,
		JobStore $job_store,
		Logger $logger,
		int $page_size
	) {
		parent::__construct( $watcher, $sitemap_store, $item_store, $job_store, $logger, $page_size );

		$this->cache = new TermCache();
	}

	public function add_hooks(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_action( 'clean_term_cache', [ $this, 'clean_term_cache' ], 10, 3 );
		add_action( 'clean_taxonomy_cache', [ $this, 'clean_taxonomy_cache' ] );

		$this->watcher->add_hooks();
	}

	/**
	 * @param array|mixed $ids An array of term IDs.
	 * @param string      $taxonomy Taxonomy slug.
	 * @param bool        $clean_taxonomy Whether or not to clean taxonomy-wide caches
	 */
	public function clean_term_cache( $ids, string $taxonomy, bool $clean_taxonomy ): void {
		if ( $clean_taxonomy ) {
			// No need to invalidate the cache, clean_taxonomy_cache action has already fired.
			return;
		}

		$this->cache->clean( $taxonomy );
	}

	/**
	 * @param string|mixed $taxonomy Taxonomy slug.
	 */
	public function clean_taxonomy_cache( $taxonomy ): void {
		$this->cache->clean( $taxonomy );
	}

	protected function get_invalidations( int $events ): int {
		$invalidations = 0;

		if ( $events & Watcher::TERM_SAVED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::TERM_LINK_UPDATED ) {
			$invalidations |= Invalidations::ITEM_URL;
		}
		if ( $events & Watcher::TERM_COUNT_UPDATED ) {
			$invalidations |= Invalidations::IS_INDEXABLE;
		}
		if ( $events & Watcher::TERM_DELETED ) {
			// Ignore other invalidations in case term was deleted.
			$invalidations = Invalidations::IS_INDEXABLE;
		}

		return apply_filters( 'static_sitemap_term_invalidations', $invalidations, $events );
	}

	protected function update_last_modified( array $items ): void {
		do_action( 'static_sitemap_terms_update_last_modified', $items );
	}

	public function is_indexable( $object ) {
		if ( ! $object instanceof \WP_Term ) {
			return false;
		}

		if ( ! $this->taxonomy_has_sitemap( $object->taxonomy ) ) {
			return false;
		}

		return apply_filters( 'static_sitemap_term_indexable', true, $object );
	}

	public function taxonomy_has_sitemap( string $taxonomy ): bool {
		return in_array( $taxonomy, $this->get_taxonomies(), true );
	}

	public function get_taxonomies(): array {
		if ( isset( $this->taxonomies ) ) {
			return $this->taxonomies;
		}

		$taxonomies = get_taxonomies( [ 'public' => true ] );
		$taxonomies = array_filter( $taxonomies, 'is_taxonomy_viewable' );

		$this->taxonomies = apply_filters( 'static_sitemap_taxonomies', $taxonomies );

		return $this->taxonomies;
	}

	/**
	 * @param TermItem|mixed $item
	 *
	 * @return void
	 */
	protected function update_item_url( $item ) {
		if ( ! $item instanceof TermItem ) {
			return;
		}

		$term = $item->get_object();
		if ( ! $term ) {
			return;
		}

		$url = get_term_link( $term );
		$url = apply_filters( 'static_sitemap_term_url', $url, $term );

		if ( ! $url ) {
			return;
		}

		$item->url = Url::remove_home_url( $url );
		$this->item_store->update_item( $item );
	}

	private function get_indexer(): Indexer {
		return new Indexer(
			$this,
			$this->item_store,
			$this->sitemap_store,
			$this->logger,
			$this->page_size
		);
	}

	public function handles_object( $object ): bool {
		return $object instanceof \WP_Term;
	}

	public function get_object_type(): string {
		return 'term';
	}

	public static function compare_objects( $a, $b ): int {
		if ( is_int( $a ) && 0 !== $a ) {
			$a = get_term( $a );
		}

		if ( is_int( $b ) && 0 !== $b ) {
			$b = get_term( $b );
		}

		if ( ! $a instanceof \WP_Term || ! $b instanceof \WP_Term ) {
			return 0;
		}

		$compare_callback = static function ( \WP_Term $a, \WP_Term $b ) {
			return $a->term_id <=> $b->term_id
				?: $a->term_taxonomy_id <=> $b->term_taxonomy_id;
		};

		if ( has_filter( 'static_sitemap_term_compare_callback' ) ) {
			$original_compare_callback = $compare_callback;

			$compare_callback = apply_filters(
				'static_sitemap_term_compare_callback',
				$compare_callback
			);

			if ( ! is_callable( $compare_callback ) ) {
				$compare_callback = $original_compare_callback;
			}
		}

		try {
			return $compare_callback( $a, $b );
		} catch ( \Throwable $e ) {
			return 0;
		}
	}

	protected function get_object_by_id( int $object_id ) {
		return get_term_by( 'term_taxonomy_id', $object_id );
	}

	public function index_objects( ?array $subtypes = [], bool $force_recreate = false ): array {
		$taxonomies = empty( $subtypes ) ? $this->get_taxonomies() : $subtypes;

		$indexer = $this->get_indexer()
			->set_force_recreate( $force_recreate );

		$results = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms_indexed = $indexer->run( $taxonomy );
			$results[]     = [
				'object_type'     => 'term',
				'object_subtype'  => $taxonomy,
				'objects_indexed' => is_wp_error( $terms_indexed ) ? 0 : $terms_indexed,
				'error'           => is_wp_error( $terms_indexed ) ? $terms_indexed : null,
			];
		}

		return $results;
	}

	public function is_enabled(): bool {
		return true;
	}

}
