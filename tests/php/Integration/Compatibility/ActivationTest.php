<?php
/**
 * ActivationTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\WordPressSeo;
use AchttienVijftien\Plugin\StaticXMLSitemap\Plugin;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class ActivationTest
 */
class ActivationTest extends TestCase {

	public function test_is_activated_returns_false_without_yoast(): void {
		$wpseo = $this->create_container()->get( WordPressSeo::class );

		$this->assertFalse( $wpseo->is_activated() );
	}

	public function test_compatibility_hooks_not_added_without_yoast(): void {
		$container = $this->create_container();
		$plugin    = $container->get( Plugin::class );
		$wpseo     = $container->get( WordPressSeo::class );

		$plugin->add_compatibility_hooks();

		$this->assertFalse( has_filter( 'static_sitemap_enabled', [ $wpseo, 'sitemaps_enabled' ] ) );
		$this->assertFalse( has_filter( 'static_sitemap_post_indexable', [ $wpseo, 'post_indexable' ] ) );
		$this->assertFalse( has_filter( 'static_sitemap_term_indexable', [ $wpseo, 'term_indexable' ] ) );
	}

	public function test_add_hooks_registers_compatibility_filters(): void {
		$wpseo = $this->create_container()->get( WordPressSeo::class );

		$wpseo->add_hooks();

		$this->assertNotFalse( has_filter( 'static_sitemap_enabled', [ $wpseo, 'sitemaps_enabled' ] ) );
		$this->assertNotFalse( has_filter( 'static_sitemap_post_indexable', [ $wpseo, 'post_indexable' ] ) );
		$this->assertNotFalse( has_filter( 'static_sitemap_term_indexable', [ $wpseo, 'term_indexable' ] ) );
		$this->assertNotFalse( has_filter( 'static_sitemap_force_queue_add', [ $wpseo, 'force_queue_add' ] ) );
	}

	public function test_sitemaps_enabled_false_when_option_missing(): void {
		delete_option( 'wpseo' );

		$wpseo = $this->create_container()->get( WordPressSeo::class );

		$this->assertFalse( $wpseo->sitemaps_enabled() );
	}

	public function test_sitemaps_enabled_true_when_option_enabled(): void {
		update_option( 'wpseo', [ 'enable_xml_sitemap' => true ] );

		$wpseo = $this->create_container()->get( WordPressSeo::class );

		$this->assertTrue( $wpseo->sitemaps_enabled() );
	}

	public function test_sitemaps_enabled_false_when_option_disabled(): void {
		update_option( 'wpseo', [ 'enable_xml_sitemap' => false ] );

		$wpseo = $this->create_container()->get( WordPressSeo::class );

		$this->assertFalse( $wpseo->sitemaps_enabled() );
	}
}
