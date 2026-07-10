<?php
/**
 * ObjectAccessorTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ObjectAccessor;

/**
 * Class ObjectAccessorTest
 */
class ObjectAccessorTest extends TestCase {

	public function test_returns_value_for_existing_property(): void {
		$accessor = new ObjectAccessor( (object) [ 'foo' => 'bar' ] );

		$this->assertSame( 'bar', $accessor->foo );
	}

	public function test_returns_null_for_missing_property(): void {
		$accessor = new ObjectAccessor( (object) [ 'foo' => 'bar' ] );

		$this->assertNull( $accessor->missing );
	}

	public function test_returns_null_for_explicit_null_property(): void {
		$accessor = new ObjectAccessor( (object) [ 'foo' => null ] );

		$this->assertNull( $accessor->foo );
	}

	public function test_preserves_falsy_values(): void {
		$accessor = new ObjectAccessor( (object) [ 'zero' => 0, 'false' => false ] );

		$this->assertSame( 0, $accessor->zero );
		$this->assertFalse( $accessor->false );
	}
}
