<?php
/**
 * TermWatcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

use AchttienVijftien\Plugin\StaticXMLSitemap\Term\SitemapProvider;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Watcher;

/**
 * Class TermWatcher
 */
class TermWatcher {

	public const NOINDEX_META_UPDATED       = 1 << 10;
	public const CANONICAL_META_UPDATED     = 1 << 11;
	public const TERM_LAST_MODIFIED_UPDATED = 1 << 12;
	public const CHILD_TERM_COUNT_UPDATED   = 1 << 13;

	private Watcher $watcher;
	private SitemapProvider $provider;
	private TermItemStore $term_item_store;

	private ?array $post_statuses = null;
	private array $meta_key_events;

	public function __construct(
		Watcher $base_watcher,
		SitemapProvider $provider,
		TermItemStore $term_item_store
	) {
		$this->watcher         = $base_watcher;
		$this->provider        = $provider;
		$this->term_item_store = $term_item_store;
		$this->meta_key_events = [
			'wpseo_noindex'   => self::NOINDEX_META_UPDATED,
			'wpseo_canonical' => self::CANONICAL_META_UPDATED,
		];
	}

	public function add_hooks(): void {
		add_action( 'updated_term_meta', [ $this, 'updated_term_meta' ], 10, 4 );
		add_action( 'added_term_meta', [ $this, 'updated_term_meta' ], 10, 4 );
		add_action( 'deleted_term_meta', [ $this, 'updated_term_meta' ], 10, 4 );
		add_action( 'added_term_relationship', [ $this, 'added_term_relationship' ], 10, 3 );
		add_action( 'delete_term_relationships', [ $this, 'delete_term_relationships' ], 10, 3 );
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'edited_term_taxonomy', [ $this, 'edited_term_taxonomy' ], 10, 3 );
	}

	public function updated_term_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		if ( ! key_exists( $meta_key, $this->meta_key_events ) ) {
			return;
		}

		$this->watcher->add_events( $object_id, $this->meta_key_events[ $meta_key ] );
	}

	/**
	 * @param int|mixed $object_id Object ID.
	 * @param int       $tt_id Term taxonomy ID.
	 * @param string    $taxonomy Taxonomy slug.
	 */
	public function added_term_relationship( $object_id, int $tt_id, string $taxonomy ) {
		if ( ! $this->provider->taxonomy_has_sitemap( $taxonomy ) ) {
			return;
		}

		$item = $this->term_item_store->get_one_by_object_id( $tt_id );

		if ( ! $item ) {
			return;
		}

		$post = get_post( $object_id );

		if ( ! $post ) {
			return;
		}

		if ( ! $this->is_post_publicly_viewable( $post ) ) {
			return;
		}

		if ( $item->last_modified < $post->post_modified_gmt ) {
			$this->watcher->add_events( $tt_id, self::TERM_LAST_MODIFIED_UPDATED );
		}
	}

	/**
	 * @param int|mixed $object_id Object ID.
	 * @param array     $tt_ids An array of term taxonomy IDs.
	 * @param string    $taxonomy Taxonomy slug.
	 */
	public function delete_term_relationships( $object_id, array $tt_ids, string $taxonomy ) {
		if ( ! $this->provider->taxonomy_has_sitemap( $taxonomy ) ) {
			return;
		}

		foreach ( $tt_ids as $tt_id ) {
			$this->delete_term_relationship( $object_id, $tt_id );
		}
	}

	private function delete_term_relationship( $object_id, int $tt_id ) {
		$item = $this->term_item_store->get_one_by_object_id( $tt_id );

		if ( ! $item ) {
			return;
		}

		if ( $item->last_modified_object_id === $object_id ) {
			$this->watcher->add_events( $tt_id, self::TERM_LAST_MODIFIED_UPDATED );
		}
	}

	private function is_post_publicly_viewable( \WP_Post $post ): bool {
		$post_status_viewable = in_array(
			$post->post_status,
			$this->get_viewable_post_statuses( $post->post_type ),
			true
		);

		if ( ! $post_status_viewable ) {
			return false;
		}

		if ( '' !== $post->post_password ) {
			return false;
		}

		return true;
	}

	public function get_viewable_post_statuses( string $post_type ): ?array {
		if ( isset( $this->post_statuses[ $post_type ] ) ) {
			return $this->post_statuses[ $post_type ];
		}

		$post_statuses = apply_filters( 'wpseo_sitemap_post_statuses', [ 'publish' ], '1' );

		if ( ! is_array( $post_statuses ) || empty( $post_statuses ) ) {
			$post_statuses = [ 'publish' ];
		}

		if ( in_array( 'inherit', $post_statuses, true ) ) {
			$post_statuses[] = 'inherit';
		}

		$this->post_statuses[ $post_type ] = $post_statuses;

		return $this->post_statuses[ $post_type ];
	}

	/**
	 * @param int|mixed $post_id Post ID.
	 * @param \WP_Post  $post Post object.
	 * @param bool      $update Whether this is an existing post being updated.
	 */
	public function save_post( $post_id, \WP_Post $post, bool $update ) {
		if ( $update ) {
			return;
		}

		if ( ! $this->is_post_publicly_viewable( $post ) ) {
			return;
		}

		$terms = get_terms( [ 'object_ids' => (int) $post_id ] );

		if ( ! is_array( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$this->post_viewable_updated( $post, $term, true );
		}
	}

	public function post_updated( $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( $post_before->post_modified_gmt === $post_after->post_modified_gmt
			&& $post_before->post_password === $post_after->post_password
		) {
			return;
		}

		$is_viewable  = $this->is_post_publicly_viewable( $post_after );
		$was_viewable = $this->is_post_publicly_viewable( $post_before );
		$updated      = $is_viewable && ! $was_viewable || ! $is_viewable && $was_viewable;

		if ( ! $updated ) {
			return;
		}

		$terms = get_terms( [ 'object_ids' => (int) $post_id ] );

		if ( ! is_array( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$this->post_viewable_updated( $post_after, $term, $is_viewable );
		}
	}

	private function post_viewable_updated( \WP_Post $post, \WP_Term $term, bool $is_viewable ) {
		if ( ! $this->provider->taxonomy_has_sitemap( $term->taxonomy ) ) {
			return;
		}

		$item = $this->term_item_store->get_one_by_object_id( $term->term_taxonomy_id );

		if ( ! $item ) {
			return;
		}

		if ( $is_viewable && (int) $item->last_modified < $post->post_modified_gmt
			|| ! $is_viewable && $item->last_modified_object_id === $post->ID
		) {
			$this->watcher->add_events( $term->term_taxonomy_id, self::TERM_LAST_MODIFIED_UPDATED );
		}
	}

	/**
	 * @param int|mixed   $term Term taxonomy id
	 * @param string      $taxonomy
	 * @param array|mixed $args If called by wp_update_term(), args passed to the function.
	 *
	 * @return void
	 */
	public function edited_term_taxonomy( $term, string $taxonomy, $args = null ) {
		if ( null !== $args ) {
			return;
		}

		if ( ! $this->provider->taxonomy_has_sitemap( $taxonomy ) ) {
			return;
		}

		if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return;
		}

		$hide_empty     = apply_filters( 'wpseo_sitemap_exclude_empty_terms', true, [ $taxonomy ] );
		$hide_empty_tax = apply_filters( 'wpseo_sitemap_exclude_empty_terms_taxonomy', $hide_empty, $taxonomy );

		if ( ! $hide_empty_tax ) {
			return;
		}

		$term = get_term_by( 'term_taxonomy_id', $term );

		if ( ! $term ) {
			return;
		}

		while ( ! empty( $term->parent ) ) {
			$parent = get_term( $term->parent, $taxonomy );
			if ( ! $parent ) {
				break;
			}
			$this->watcher->add_events( $parent->term_taxonomy_id, self::CHILD_TERM_COUNT_UPDATED );
			$term = $parent;
		}
	}
}
