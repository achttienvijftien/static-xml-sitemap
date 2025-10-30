<?php
/**
 * Plugin Name: Static XML sitemap
 * Plugin URI: https://www.1815.nl
 * Description: Generate static XML sitemaps for large WordPress sites
 * Version: 1.0.0
 * Author: 1815
 * Author URI: https://www.1815.nl
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 **/

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

require __DIR__ . '/polyfills.php';

const PLUGIN_DIR  = __DIR__;
const PLUGIN_FILE = __FILE__;

function activate(): void {
	Bootstrap::boot( fn( Plugin $plugin ) => $plugin->activate() );
}

function deactivate(): void {
	Bootstrap::boot( fn( Plugin $plugin ) => $plugin->deactivate() );
}

function boot(): void {
	Bootstrap::boot( fn( Plugin $plugin ) => $plugin->add_hooks() );
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );
