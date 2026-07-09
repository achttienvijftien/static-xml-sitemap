<?php
/**
 * PropertyAccessorTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ArrayAccessor;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ObjectAccessor;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

/**
 * Class PropertyAccessorTest
 */
class PropertyAccessorTest extends TestCase {

	public function test_create_returns_object_accessor_for_object(): void {
		$accessor = PropertyAccessor::create( (object) [ 'foo' => 'bar' ] );

		$this->assertInstanceOf( ObjectAccessor::class, $accessor );
		$this->assertSame( 'bar', $accessor->foo );
	}

	public function test_create_returns_array_accessor_for_array(): void {
		$accessor = PropertyAccessor::create( [ 'foo' => 'bar' ] );

		$this->assertInstanceOf( ArrayAccessor::class, $accessor );
		$this->assertSame( 'bar', $accessor->foo );
	}

	public function test_create_throws_for_scalar(): void {
		$this->expectException( \BadMethodCallException::class );

		PropertyAccessor::create( 'a string' );
	}

	public function test_create_throws_for_null(): void {
		$this->expectException( \BadMethodCallException::class );

		PropertyAccessor::create( null );
	}

	public function test_get_public_object_vars_returns_only_public_properties(): void {
		$object = new class() {

			public $visible = 1;
			protected $hidden_protected = 2;
			private $hidden_private = 3;
		};

		$this->assertSame( [ 'visible' => 1 ], PropertyAccessor::get_public_object_vars( $object ) );
	}
}
