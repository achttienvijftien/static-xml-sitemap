<?php
/**
 * ContainerInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Container
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Container;

interface ContainerInterface extends \Psr\Container\ContainerInterface {

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function get_parameter( string $name );

	public function has_parameter( string $name ): bool;

}
