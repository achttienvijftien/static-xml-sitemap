<?php
/**
 * TestCase
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;

/**
 * Class TestCase
 */
abstract class TestCase extends \WP_UnitTestCase {

	protected function create_container(): Container {
		return ( new Container() )
			->add_parameters( include dirname( __DIR__, 2 ) . '/config/parameters.php' );
	}
}
