<?php
/**
 * Plugin uninstaller
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

use AchttienVijftien\Plugin\StaticXMLSitemap\Bootstrap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;

require __DIR__ . '/vendor/autoload.php';

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	return;
}

Bootstrap::boot( fn( Plugin $plugin ) => $plugin->uninstall() );
