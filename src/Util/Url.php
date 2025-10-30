<?php
/**
 * Url
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class Url
 */
class Url {

	public static function remove_home_url( string $url ): ?string {
		$home_url_regex = preg_quote( untrailingslashit( home_url() ), '|' );

		return preg_replace( "|^$home_url_regex(.+)$|", '$1', $url );
	}

	public static function is_site_url( string $url ): bool {
		$home_url = home_url();

		return strncmp( $url, $home_url, strlen( $home_url ) ) === 0;
	}

}
