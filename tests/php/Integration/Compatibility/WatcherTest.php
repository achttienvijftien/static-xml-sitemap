<?php
/**
 * WatcherTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility;

use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\PostWatcher as CompatPostWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\TermWatcher as CompatTermWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo\UserWatcher as CompatUserWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Post\Watcher as PostWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Term\Watcher as TermWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\User\Watcher as UserWatcher;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\AbstractWatcher;

/**
 * Class WatcherTest
 */
class WatcherTest extends TestCase {

	private function read_events( AbstractWatcher $watcher ): array {
		$property = new \ReflectionProperty( AbstractWatcher::class, 'events' );
		$property->setAccessible( true );

		return $property->getValue( $watcher );
	}

	public function test_post_noindex_meta_maps_to_noindex_event(): void {
		$container = $this->create_container();
		$base      = $container->get( PostWatcher::class );
		$compat    = $container->get( CompatPostWatcher::class );

		$compat->updated_post_meta( 1, 123, '_yoast_wpseo_meta-robots-noindex', '1' );

		$this->assertSame( CompatPostWatcher::NOINDEX_META_UPDATED, $this->read_events( $base )[123] );
	}

	public function test_post_canonical_meta_maps_to_canonical_event(): void {
		$container = $this->create_container();
		$base      = $container->get( PostWatcher::class );
		$compat    = $container->get( CompatPostWatcher::class );

		$compat->updated_post_meta( 1, 123, '_yoast_wpseo_canonical', '/x' );

		$this->assertSame( CompatPostWatcher::CANONICAL_META_UPDATED, $this->read_events( $base )[123] );
	}

	public function test_post_unrelated_meta_registers_no_event(): void {
		$container = $this->create_container();
		$base      = $container->get( PostWatcher::class );
		$compat    = $container->get( CompatPostWatcher::class );

		$compat->updated_post_meta( 1, 123, '_some_other_meta', '1' );

		$this->assertArrayNotHasKey( 123, $this->read_events( $base ) );
	}

	public function test_user_profile_updated_meta_maps_to_profile_event(): void {
		$container = $this->create_container();
		$base      = $container->get( UserWatcher::class );
		$compat    = $container->get( CompatUserWatcher::class );

		$compat->updated_user_meta( 1, 5, '_yoast_wpseo_profile_updated', 123 );

		$this->assertSame( CompatUserWatcher::PROFILE_UPDATED_UPDATED, $this->read_events( $base )[5] );
	}

	public function test_user_noindex_author_meta_maps_to_noindex_event(): void {
		$container = $this->create_container();
		$base      = $container->get( UserWatcher::class );
		$compat    = $container->get( CompatUserWatcher::class );

		$compat->updated_user_meta( 1, 5, 'wpseo_noindex_author', 'on' );

		$this->assertSame( CompatUserWatcher::NOINDEX_AUTHOR_UPDATED, $this->read_events( $base )[5] );
	}

	public function test_user_level_meta_maps_to_user_level_event(): void {
		global $wpdb;

		$container = $this->create_container();
		$base      = $container->get( UserWatcher::class );
		$compat    = $container->get( CompatUserWatcher::class );

		$compat->updated_user_meta( 1, 5, $wpdb->get_blog_prefix() . 'user_level', '10' );

		$this->assertSame( CompatUserWatcher::USER_LEVEL_UPDATED, $this->read_events( $base )[5] );
	}

	public function test_user_role_change_maps_to_roles_event(): void {
		$container = $this->create_container();
		$base      = $container->get( UserWatcher::class );
		$compat    = $container->get( CompatUserWatcher::class );

		$compat->update_user_role( 7 );

		$this->assertSame( CompatUserWatcher::USER_ROLES_UPDATED, $this->read_events( $base )[7] );
	}

	public function test_term_noindex_meta_maps_to_noindex_event(): void {
		$container = $this->create_container();
		$base      = $container->get( TermWatcher::class );
		$compat    = $container->get( CompatTermWatcher::class );

		$compat->updated_term_meta( 1, 9, 'wpseo_noindex', 'noindex' );

		$this->assertSame( CompatTermWatcher::NOINDEX_META_UPDATED, $this->read_events( $base )[9] );
	}

	public function test_term_canonical_meta_maps_to_canonical_event(): void {
		$container = $this->create_container();
		$base      = $container->get( TermWatcher::class );
		$compat    = $container->get( CompatTermWatcher::class );

		$compat->updated_term_meta( 1, 9, 'wpseo_canonical', '/x' );

		$this->assertSame( CompatTermWatcher::CANONICAL_META_UPDATED, $this->read_events( $base )[9] );
	}

	public function test_term_unrelated_meta_registers_no_event(): void {
		$container = $this->create_container();
		$base      = $container->get( TermWatcher::class );
		$compat    = $container->get( CompatTermWatcher::class );

		$compat->updated_term_meta( 1, 9, '_some_other_meta', '1' );

		$this->assertArrayNotHasKey( 9, $this->read_events( $base ) );
	}
}
