<?php
/**
 * ObjectAccessor
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class ObjectAccessor
 */
class ObjectAccessor {

	private object $object;

	public function __construct( object $object ) {
		$this->object = $object;
	}

	public function __get( $name ) {
		return $this->object->$name ?? null;
	}

}
