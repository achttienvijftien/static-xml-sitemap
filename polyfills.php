<?php
/**
 * polyfills.php
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap
 */

if ( ! function_exists( 'array_all' ) ) {
	function array_all( array $array, callable $callback ): bool {
		foreach ( $array as $value ) {
			if ( ! $callback( $value ) ) {
				return false;
			}
		}

		return true;
	}
}
