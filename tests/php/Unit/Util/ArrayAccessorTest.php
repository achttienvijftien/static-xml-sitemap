<?php
/**
 * ArrayAccessorTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ArrayAccessor;

/**
 * Class ArrayAccessorTest
 */
class ArrayAccessorTest extends TestCase {

	public function test_returns_value_for_existing_key(): void {
		$accessor = new ArrayAccessor( [ 'foo' => 'bar' ] );

		$this->assertSame( 'bar', $accessor->foo );
	}

	public function test_returns_null_for_missing_key(): void {
		$accessor = new ArrayAccessor( [ 'foo' => 'bar' ] );

		$this->assertNull( $accessor->missing );
	}

	public function test_returns_null_for_explicit_null_value(): void {
		$accessor = new ArrayAccessor( [ 'foo' => null ] );

		$this->assertNull( $accessor->foo );
	}

	public function test_returns_null_from_empty_array(): void {
		$accessor = new ArrayAccessor( [] );

		$this->assertNull( $accessor->anything );
	}

	public function test_preserves_falsy_values(): void {
		$accessor = new ArrayAccessor( [ 'zero' => 0, 'empty' => '', 'false' => false ] );

		$this->assertSame( 0, $accessor->zero );
		$this->assertSame( '', $accessor->empty );
		$this->assertFalse( $accessor->false );
	}
}
