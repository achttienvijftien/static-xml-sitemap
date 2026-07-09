<?php
/**
 * PolyfillsTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class PolyfillsTest
 */
class PolyfillsTest extends TestCase {

	public function test_array_all_returns_true_when_all_match(): void {
		$this->assertTrue( array_all( [ 2, 4, 6 ], static fn( $value ) => 0 === $value % 2 ) );
	}

	public function test_array_all_returns_false_when_one_fails(): void {
		$this->assertFalse( array_all( [ 2, 3, 6 ], static fn( $value ) => 0 === $value % 2 ) );
	}

	public function test_array_all_returns_true_for_empty_array(): void {
		$this->assertTrue( array_all( [], static fn( $value ) => false ) );
	}
}
