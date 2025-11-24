<?php
/**
 * Installer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;

/**
 * Class Installer
 */
class Installer {

	private const DB_VERSION = 2;

	private const TABLES = [
		'sitemaps',
		'sitemap_posts',
		'sitemap_users',
		'sitemap_terms',
		'sitemap_jobs',
	];

	public function add_hooks(): void {
		add_action( 'wpmu_drop_tables', [ $this, 'wpmu_drop_tables' ] );
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
	}

	public function wpmu_drop_tables( $tables ) {
		if ( is_array( $tables ) ) {
			return array_merge( $tables, $this->get_tables() );
		}

		return $tables;
	}

	protected function get_tables(): array {
		global $wpdb;

		$tables = [];
		foreach ( self::TABLES as $table ) {
			$tables[] = $wpdb->prefix . $table;
		}

		return $tables;
	}

	public function activate(): void {
		$result = $this->install_tables();

		if ( is_wp_error( $result ) ) {
			update_option( 'static_sitemap_install_errors', $result->get_error_messages(), false );

			return;
		}

		delete_option( 'static_sitemap_install_errors' );
	}

	public function maybe_upgrade(): void {
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( $this->is_installed() && $this->get_db_version() < self::DB_VERSION ) {
			$this->upgrade();
		}
	}

	private function is_installed(): bool {
		return $this->get_db_version() > 0;
	}

	private function get_db_version(): int {
		return (int) get_option( 'static_sitemap_db_version', 0 );
	}

	public function upgrade(): void {
		if ( ! $this->is_installed() || $this->get_db_version() >= self::DB_VERSION ) {
			return;
		}

		$lock = ( new Lock( 'upgrade_db' ) )->set_wait( 0 );

		if ( ! $lock->acquire() ) {
			return;
		}

		$release_lock = function () use ( $lock ) {
			$lock->release();
		};

		add_action( 'shutdown', $release_lock );
		dbDelta( $this->get_schema() );
		update_option( 'static_sitemap_db_version', self::DB_VERSION, false );
		$release_lock();
		remove_action( 'shutdown', $release_lock );
	}

	protected function install_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		update_option( 'static_sitemap_db_version', self::DB_VERSION, false );

		dbDelta( $this->get_schema() );

		$missing_tables = $this->get_missing_tables();

		if ( $missing_tables ) {
			update_option( 'static_sitemap_missing_tables', $missing_tables, false );

			$error = new \WP_Error();

			foreach ( $missing_tables as $table ) {
				$error->add(
					'missing_table',
					sprintf( __( 'Missing table %s', 'static-xml-sitemap' ), $table )
				);
			}

			return $error;
		}

		delete_option( 'static_sitemap_missing_tables' );

		return true;
	}

	protected function get_schema(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return <<<SQL
CREATE TABLE {$wpdb->prefix}sitemaps (
	id int unsigned NOT NULL auto_increment,
	object_type varchar(4) NOT NULL,
	object_subtype varchar(20),
	last_modified datetime,
	last_object_id bigint(20),
	last_item_index int unsigned,
	last_indexed_value varchar(200),
	last_indexed_id bigint(20),
	status enum('unindexed','indexed','indexing','updating') NOT NULL,
	item_count int default '0',
	PRIMARY KEY  (id),
	UNIQUE KEY object_type_subtype (object_type,object_subtype)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}sitemap_posts (
	id int unsigned NOT NULL auto_increment,
	post_id bigint(20) unsigned NOT NULL,
	sitemap_id int unsigned NOT NULL,
	url varchar(2000) NOT NULL,
	item_index int unsigned,
	next_item_index int unsigned,
	PRIMARY KEY  (id),
	KEY post_id (post_id),
	KEY sitemap_id_item_index (sitemap_id,item_index)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}sitemap_users (
	id int unsigned NOT NULL auto_increment,
	user_id bigint(20) unsigned NOT NULL,
	sitemap_id int unsigned NOT NULL,
	url varchar(2000) NOT NULL,
	item_index int unsigned,
	next_item_index int unsigned,
	PRIMARY KEY  (id),
	KEY user_id (user_id),
	KEY sitemap_id_item_index (sitemap_id,item_index)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}sitemap_terms (
	id int unsigned NOT NULL auto_increment,
	term_taxonomy_id bigint(20) unsigned NOT NULL,
	sitemap_id int unsigned NOT NULL,
	url varchar(2000) NOT NULL,
	last_modified datetime,
	last_modified_object_id bigint(20),
	item_index int unsigned,
	next_item_index int unsigned,
	PRIMARY KEY  (id),
	KEY term_taxonomy_id (term_taxonomy_id),
	KEY last_modified_last_modified_object_id (last_modified, last_modified_object_id),
	KEY sitemap_id_item_index (sitemap_id,item_index)
) $charset_collate;
CREATE TABLE {$wpdb->prefix}sitemap_jobs (
	id int unsigned NOT NULL auto_increment,
	sitemap_id int unsigned NOT NULL,
	sitemap_item_id int unsigned,
	object_id bigint(20) unsigned,
	action enum('add_item','remove_item','reindex_item','reindex_sitemap','update_last_modified') NOT NULL,
	scheduled_at datetime NOT NULL,
	claim_id binary(16),
	claimed_at datetime,
	PRIMARY KEY  (id),
	UNIQUE KEY sitemap_id_object_id_action (sitemap_id,object_id,action),
	KEY sitemap_id_scheduled_at (sitemap_id,scheduled_at),
	KEY object_id (object_id),
	KEY claim_id (claim_id)
) $charset_collate;
SQL;
	}

	protected function get_missing_tables(): array {
		global $wpdb;

		$missing_tables = [];

		$suppress_errors = $wpdb->suppress_errors();

		foreach ( $this->get_tables() as $table ) {
			$described_table = $wpdb->get_results( "DESCRIBE $table" );

			if ( ! is_array( $described_table ) || [] === $described_table ) {
				$missing_tables[] = $table;
			}
		}

		$wpdb->suppress_errors( $suppress_errors );

		return $missing_tables;
	}

	public function deactivate(): void {
		// remove scheduled cron events..
	}

	public function uninstall(): void {
		$this->drop_tables();
		$this->delete_options();
	}

	private function drop_tables() {
		global $wpdb;
		foreach ( $this->get_tables() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" );
		}
	}

	private function delete_options() {
		delete_option( 'static_sitemap_install_errors' );
		delete_option( 'static_sitemap_missing_tables' );
	}

}
