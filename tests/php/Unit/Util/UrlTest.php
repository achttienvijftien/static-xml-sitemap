<?php
/**
 * UrlTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;

/**
 * Class UrlTest
 */
class UrlTest extends TestCase {

	public function test_remove_home_url_strips_prefix(): void {
		$this->assertSame( '/foo/bar', Url::remove_home_url( home_url() . '/foo/bar' ) );
	}

	public function test_remove_home_url_returns_slash_for_home_root(): void {
		$this->assertSame( '/', Url::remove_home_url( home_url() . '/' ) );
	}

	public function test_remove_home_url_leaves_home_url_without_path_unchanged(): void {
		$this->assertSame( home_url(), Url::remove_home_url( home_url() ) );
	}

	public function test_remove_home_url_leaves_foreign_url_unchanged(): void {
		$foreign = 'http://not-this-site.test/some/path';

		$this->assertSame( $foreign, Url::remove_home_url( $foreign ) );
	}

	public function test_is_site_url_true_for_path_on_site(): void {
		$this->assertTrue( Url::is_site_url( home_url( '/some/path' ) ) );
	}

	public function test_is_site_url_true_for_home_url_itself(): void {
		$this->assertTrue( Url::is_site_url( home_url() ) );
	}

	public function test_is_site_url_false_for_foreign_url(): void {
		$this->assertFalse( Url::is_site_url( 'http://not-this-site.test/' ) );
	}

	public function test_is_site_url_false_for_empty_string(): void {
		$this->assertFalse( Url::is_site_url( '' ) );
	}
}
