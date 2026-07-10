<?php
/**
 * ObjectTypeTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\ObjectType;

/**
 * Class ObjectTypeTest
 */
class ObjectTypeTest extends TestCase {

	public function test_get_type_for_post(): void {
		$this->assertSame( 'post', ObjectType::get_type( new \WP_Post( (object) [ 'post_type' => 'page' ] ) ) );
	}

	public function test_get_type_for_user(): void {
		$this->assertSame( 'user', ObjectType::get_type( new \WP_User() ) );
	}

	public function test_get_type_for_term(): void {
		$this->assertSame( 'term', ObjectType::get_type( new \WP_Term( (object) [ 'taxonomy' => 'category' ] ) ) );
	}

	public function test_get_type_for_unknown_object(): void {
		$this->assertNull( ObjectType::get_type( new \stdClass() ) );
	}

	public function test_get_subtype_for_post_returns_post_type(): void {
		$this->assertSame( 'page', ObjectType::get_subtype( new \WP_Post( (object) [ 'post_type' => 'page' ] ) ) );
	}

	public function test_get_subtype_for_term_returns_taxonomy(): void {
		$this->assertSame( 'category', ObjectType::get_subtype( new \WP_Term( (object) [ 'taxonomy' => 'category' ] ) ) );
	}

	public function test_get_subtype_for_user_is_null(): void {
		$this->assertNull( ObjectType::get_subtype( new \WP_User() ) );
	}

	public function test_get_subtype_for_unknown_object_is_null(): void {
		$this->assertNull( ObjectType::get_subtype( new \stdClass() ) );
	}
}
