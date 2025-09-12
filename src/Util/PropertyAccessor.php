<?php
/**
 * PropertyAccessor
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class PropertyAccessor
 */
class PropertyAccessor {

	/**
	 * @param mixed $var
	 *
	 * @return ArrayAccessor|ObjectAccessor
	 */
	public static function create( $var ) {
		if ( is_object( $var ) ) {
			return new ObjectAccessor( $var );
		}
		if ( is_array( $var ) ) {
			return new ArrayAccessor( $var );
		}

		throw new \BadMethodCallException( 'Unsupported type' );
	}

	public static function get_public_object_vars( object $object ): array {
		return get_object_vars( $object );
	}

}
