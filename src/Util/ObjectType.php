<?php
/**
 * ObjectType
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class ObjectType
 */
class ObjectType {

	public static function get_type( $object ): ?string {
		if ( $object instanceof \WP_Post ) {
			return 'post';
		}

		if ( $object instanceof \WP_User ) {
			return 'user';
		}

		return null;
	}

	public static function get_subtype( $object ): ?string {
		if ( $object instanceof \WP_Post ) {
			return $object->post_type;
		}

		return null;
	}

}
