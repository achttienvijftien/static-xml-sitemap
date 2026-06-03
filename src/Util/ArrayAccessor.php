<?php
/**
 * ArrayAccessor
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class ArrayAccessor
 */
class ArrayAccessor {

	private array $array;

	public function __construct( array $array ) {
		$this->array = $array;
	}

	public function __get( $name ) {
		return $this->array[ $name ] ?? null;
	}

}
