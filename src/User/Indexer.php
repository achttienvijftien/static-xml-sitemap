<?php
/**
 *
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\User;

use AchttienVijftien\Plugin\StaticXMLSitemap\Indexer\AbstractIndexer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;

/**
 * Class Indexer
 */
class Indexer extends AbstractIndexer {

	use WithLockTrait;

	/**
	 * Excluded users.
	 *
	 * @var \WP_User[]|null
	 */
	private ?array $excluded_users = null;

	public function get_excluded_user_ids(): array {
		if ( null === $this->excluded_users ) {
			$this->excluded_users = apply_filters( 'static_sitemap_excluded_authors', [] );
		}

		return array_map( fn( \WP_User $user ) => $user->ID, $this->excluded_users );
	}

	protected function get_sitemap_items(
		int $count,
		string $last_indexed_value = null,
		int $last_indexed_id = null,
		string $object_subtype = null
	) {
		global $wpdb;

		$orderby = $this->get_orderby();
		$fields  = array_unique( [ 'id', $orderby ] );

		$query = ( new Query() )
			->set_fields( $fields )
			->set_indexable( true )
			->set_orderby( $orderby )
			->set_limit( $count );

		$excluded_user_ids = $this->get_excluded_user_ids();

		if ( $excluded_user_ids ) {
			$query->set_exclude( $excluded_user_ids );
		}

		if ( $last_indexed_value && $last_indexed_id ) {
			$query->set_after( $orderby, $last_indexed_value, $last_indexed_id );
		}

		return $wpdb->get_results( $query->get_query() );
	}

	public function handle_unexpected_shutdown(): void {
		if ( $this->lock ) {
			$this->lock->release();
		}
	}

	protected function get_orderby(): string {
		return apply_filters( 'static_sitemap_authors_orderby', 'user_login' );
	}

	protected function before_index( int $sitemap_id ): void {
		do_action( 'static_sitemap_index_authors', $sitemap_id );
	}

	protected function after_index( int $sitemap_id, int $total_items_inserted ): void {
		do_action( 'static_sitemap_indexed_authors', $sitemap_id, $total_items_inserted );
	}

}
