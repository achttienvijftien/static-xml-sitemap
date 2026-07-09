<?php
/**
 * InstallerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration;

use AchttienVijftien\Plugin\StaticXMLSitemap\Bootstrap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class InstallerTest
 */
class InstallerTest extends TestCase {

	public function test_plugin_boots(): void {
		$this->assertInstanceOf( Plugin::class, Bootstrap::get_plugin() );
	}

	public function test_plugin_tables_are_installed(): void {
		global $wpdb;

		$tables = [ 'sitemaps', 'sitemap_posts', 'sitemap_users', 'sitemap_terms', 'sitemap_jobs' ];

		foreach ( $tables as $table ) {
			$this->assertSame(
				$wpdb->prefix . $table,
				$wpdb->get_var(
					$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table )
				),
				"Table $table should exist."
			);
		}
	}

	public function test_db_version_option_is_set(): void {
		$this->assertSame( '2', (string) get_option( 'static_sitemap_db_version' ) );
	}
}
