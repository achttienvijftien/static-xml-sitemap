<?php
/**
 * Watcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\AbstractWatcher;

/**
 * Class Watcher
 *
 * @property SitemapProvider $provider
 */
class Watcher extends AbstractWatcher {

	public const TERM_SAVED         = 1 << 0;
	public const TERM_DELETED       = 1 << 1;
	public const TERM_LINK_UPDATED  = 1 << 2;
	public const TERM_COUNT_UPDATED = 1 << 3;

	private TermItemStore $term_item_store;

	public function __construct( TermItemStore $term_item_store ) {
		$this->term_item_store = $term_item_store;
	}

	protected function add_watch_hooks(): void {
		add_action( 'saved_term', [ $this, 'saved_term' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'delete_term' ], 10, 2 );
		add_action( 'edited_term_taxonomy', [ $this, 'edited_term_taxonomy' ], 10, 3 );
	}

	/**
	 * @param mixed|int $term_id Term ID.
	 * @param int       $tt_id Term taxonomy ID.
	 * @param string    $taxonomy Taxonomy slug.
	 */
	public function saved_term( $term_id, int $tt_id, string $taxonomy ) {
		if ( ! $this->provider->taxonomy_has_sitemap( $taxonomy ) ) {
			return;
		}

		$this->add_events( $tt_id, self::TERM_SAVED );

		$item = $this->term_item_store->get_one_by_object_id( $tt_id );

		if ( $item && $item->get_url() !== get_term_link( $term_id, $taxonomy ) ) {
			$this->add_events( $tt_id, self::TERM_LINK_UPDATED );
		}
	}

	public function delete_term( $term_id, int $tt_id ): void {
		$item = $this->term_item_store->get_one_by_object_id( $tt_id );

		if ( $item ) {
			$this->add_events( $tt_id, self::TERM_DELETED );
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
		// This hook is only called without $args in _update_post_term_count() and
		// _update_generic_term_count(), so we can be fairly certain this is a term count update.
		if ( null !== $args ) {
			return;
		}

		if ( ! $this->provider->taxonomy_has_sitemap( $taxonomy ) ) {
			return;
		}

		$this->add_events( $term, self::TERM_COUNT_UPDATED );
	}

}
