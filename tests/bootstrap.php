<?php
/**
 * bootstrap
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests
 */

use AchttienVijftien\Plugin\StaticXMLSitemap\Bootstrap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;

$plugin_dir = dirname( __DIR__ );

require_once $plugin_dir . '/vendor/autoload.php';

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' );

require_once $wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $plugin_dir ) {
		require $plugin_dir . '/static-xml-sitemap.php';
	}
);

require $wp_phpunit_dir . '/includes/bootstrap.php';

Bootstrap::boot( static fn( Plugin $plugin ) => $plugin->activate() );
