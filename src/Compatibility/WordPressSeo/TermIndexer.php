<?php
/**
 * TermIndexer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapStore;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\TermItemStore;

/**
 * Class TermIndexer
 */
class TermIndexer {

	private SitemapStore $sitemap_store;
	private TermItemStore $term_item_store;

	public function __construct( SitemapStore $sitemap_store, TermItemStore $term_item_store ) {
		$this->sitemap_store = $sitemap_store;
		$this->term_item_store = $term_item_store;
	}

	public function add_hooks(): void {
		add_action( 'static_sitemap_indexed_terms', [ $this, 'indexed_terms' ], 10, 2 );
	}

	public function indexed_terms( $sitemap_id, int $total_items_inserted ): void {
		if ( $total_items_inserted < 1 ) {
			return;
		}

		$sitemap = $this->sitemap_store->get( $sitemap_id );

		if ( $sitemap ) {
			$this->calculate_last_modified( $sitemap );
		}
	}

	private function calculate_last_modified( Sitemap $sitemap ): void {
		global $wpdb;

		$last_modified_query = $wpdb->prepare(
			"SELECT tr.term_taxonomy_id AS tt_id, p.ID, MAX(p.post_modified_gmt) AS last_modified " .
			"FROM $wpdb->posts p " .
			"JOIN $wpdb->term_relationships tr ON tr.object_id = p.ID " .
			"JOIN $wpdb->term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id" .
			" AND tt.taxonomy = %s " .
			"WHERE p.post_status IN ('publish') AND p.post_password = '' " .
			"GROUP BY tr.term_taxonomy_id",
			$sitemap->object_subtype
		);

		$this->term_item_store->query(
			"UPDATE %i AS si, ($last_modified_query) AS p " .
			'SET si.last_modified = p.last_modified,' .
			' si.last_modified_object_id = p.ID ' .
			'WHERE si.term_taxonomy_id = p.tt_id',
			[ $this->term_item_store->get_table() ]
		);

		$this->term_item_store->update_sitemap_stats( $sitemap );
	}

}
