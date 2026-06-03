<?php
/**
 * Bootstrap
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class Bootstrap
 */
class Bootstrap {

	public static function boot( $callback = null ) {
		$plugin = self::get_plugin();

		if ( is_wp_error( $plugin ) ) {
			self::add_admin_notice( $plugin );

			return;
		}

		if ( $callback ) {
			$callback( $plugin );
		}
	}

	public static function get_plugin() {
		static $plugin = null;

		if ( null !== $plugin ) {
			return $plugin;
		}

		try {
			return ( new Container() )
				->add_parameters( include dirname( __DIR__ ) . '/config/parameters.php' )
				->get( Plugin::class );
		} catch ( NotFoundExceptionInterface $e ) {
			$error_message = sprintf(
				esc_html__( 'Static XML sitemap plugin could not be loaded: %s', 'static-xml-sitemap' ),
				$e->getMessage()
			);

			return new \WP_Error( 'plugin_load_error', $error_message );
		}
	}

	private static function add_admin_notice( \WP_Error $error ): void {
		add_action( 'admin_notices', function () use ( $error ) {
			echo '<div class="notice notice-error is-dismissible"><p>'
				. $error->get_error_message()
				. '</p></div>';
		} );
	}


}
