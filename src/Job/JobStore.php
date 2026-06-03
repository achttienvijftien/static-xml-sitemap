<?php
/**
 * JobStore
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Job;

use AchttienVijftien\Plugin\StaticXMLSitemap\Store\StoreTrait;

/**
 * Class JobStore
 */
class JobStore {

	use StoreTrait;

	private const MAX_JOBS_CLAIMED = 25;

	public function __construct() {
		global $wpdb;
		$this->table       = "{$wpdb->prefix}sitemap_jobs";
		$this->field_types = [
			'id'              => '%d',
			'sitemap_id'      => '%d',
			'sitemap_item_id' => '%d',
			'object_id'       => '%d',
			'action'          => '%s',
			'scheduled_at'    => '%s',
			'claim_id'        => '%s',
			'claimed_at'      => '%s',
		];
	}

	public function insert_job( Job $job ): ?Job {
		global $wpdb;

		$data = $job->to_array();

		$field_names = array_keys( $data );
		$field_types = $this->get_field_types( $field_names );

		$fields  = [];
		$formats = [];
		$values  = [];

		foreach ( $data as $field => $value ) {
			$fields[] = '%i';
			if ( null === $value ) {
				$formats[] = 'NULL';
				continue;
			}
			$formats[] = $field_types[ $field ];
			$values[]  = $value;
		}

		$fields  = implode( ', ', $fields );
		$formats = implode( ', ', $formats );

		$num_rows = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $this->table ($fields) VALUES ($formats)"
				. " ON DUPLICATE KEY UPDATE id = id",
				array_merge( $field_names, $values )
			)
		);

		if ( false === $num_rows ) {
			return null;
		}

		if ( $num_rows < 1 || $wpdb->insert_id < 1 ) {
			return null;
		}

		$job->set_id( $wpdb->insert_id );

		return $job;
	}

	public function get( int $id ): ?Job {
		global $wpdb;

		$job = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table WHERE id = %d", $id )
		);

		return $job ? new Job( $job ) : null;
	}

	/**
	 * @param int    $sitemap_id
	 * @param string $claim_id
	 * @param int    $limit
	 *
	 * @return int|\WP_Error
	 */
	public function claim_jobs(
		int $sitemap_id,
		string $claim_id,
		int $limit = self::MAX_JOBS_CLAIMED
	) {
		global $wpdb;

		$current_time = current_time( 'mysql', true );

		$skip_locked     = $this->supports_skip_locked();
		$skip_locked_sql = $skip_locked ? 'SKIP LOCKED' : '';

		$select_sql = $wpdb->prepare(
			"SELECT id FROM $this->table
			WHERE sitemap_id = %d 
			AND claim_id IS NULL 
			AND claimed_at IS NULL 
			AND scheduled_at <= %s
			ORDER BY scheduled_at
			LIMIT %d 
			FOR UPDATE $skip_locked_sql",
			$sitemap_id,
			$current_time,
			$limit
		);

		$update_sql = $wpdb->prepare(
			"UPDATE $this->table j1
			JOIN ( $select_sql ) j2 ON j1.id = j2.id
			SET claim_id = x%s, claimed_at = %s",
			$claim_id,
			$current_time
		);

		$updated = $wpdb->query( $update_sql );

		if ( false === $updated ) {
			return new \WP_Error( 'db_error', $wpdb->last_error );
		}

		return is_int( $updated ) ? $updated : 0;
	}

	private function supports_skip_locked(): bool {
		global $wpdb;

		// Check MySQL version
		$version = $wpdb->get_var( 'SELECT VERSION()' );

		// MySQL 8.0.1+ supports SKIP LOCKED
		if ( strpos( $version, 'MySQL' ) !== false || strpos( $version, 'mysql' ) !== false ) {
			return version_compare( $version, '8.0.1', '>=' );
		}

		// MariaDB 10.6+ supports SKIP LOCKED
		if ( strpos( $version, 'MariaDB' ) !== false ) {
			preg_match( '/(\d+\.\d+\.\d+)/', $version, $matches );
			if ( isset( $matches[1] ) ) {
				return version_compare( $matches[1], '10.6.0', '>=' );
			}
		}

		return false;
	}

	public function get_by_claim_id( string $claim_id ): array {
		global $wpdb;

		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $this->table WHERE claim_id = x%s ORDER BY id",
				$claim_id
			)
		);

		return $jobs ? array_map( fn( $job ) => new Job( $job ), $jobs ) : [];
	}

	public function release_claim( string $claim_id ): int {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE $this->table SET claim_id = NULL, claimed_at = NULL WHERE claim_id = x%s",
				$claim_id
			)
		);
	}

	public function delete_jobs( array $jobs ) {
		if ( empty( $jobs ) ) {
			return 0;
		}

		return $this->delete_where_id_in( array_map( fn( $job ) => $job->id, $jobs ) );
	}

}
