<?php
/**
 * EntityTraitTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Entity;

use AchttienVijftien\Plugin\StaticXMLSitemap\Post\PostItem;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class EntityTraitTest
 */
class EntityTraitTest extends TestCase {

	public function test_get_id_returns_constructed_id(): void {
		$sitemap = $this->make_sitemap( [ 'id' => 5 ] );

		$this->assertSame( 5, $sitemap->get_id() );
	}

	public function test_get_id_returns_null_when_absent(): void {
		$sitemap = $this->make_sitemap();

		$this->assertNull( $sitemap->get_id() );
	}

	public function test_set_id_updates_id(): void {
		$sitemap = $this->make_sitemap();

		$sitemap->set_id( 42 );

		$this->assertSame( 42, $sitemap->get_id() );
	}

	public function test_exists_is_false_without_id(): void {
		$this->assertFalse( $this->make_sitemap()->exists() );
	}

	public function test_exists_is_true_with_id(): void {
		$this->assertTrue( $this->make_sitemap( [ 'id' => 1 ] )->exists() );
	}

	public function test_exists_becomes_true_after_set_id(): void {
		$sitemap = $this->make_sitemap();

		$sitemap->set_id( 9 );

		$this->assertTrue( $sitemap->exists() );
	}

	public function test_to_array_contains_public_properties(): void {
		$sitemap = $this->make_sitemap(
			[
				'id'          => 5,
				'object_type' => 'post',
				'status'      => Sitemap::STATUS_INDEXED,
				'item_count'  => 12,
			]
		);

		$array = $sitemap->to_array();

		$this->assertSame( 5, $array['id'] );
		$this->assertSame( 'post', $array['object_type'] );
		$this->assertSame( Sitemap::STATUS_INDEXED, $array['status'] );
		$this->assertSame( 12, $array['item_count'] );
	}

	public function test_to_array_reflects_set_id(): void {
		$sitemap = $this->make_sitemap();

		$sitemap->set_id( 77 );

		$this->assertSame( 77, $sitemap->to_array()['id'] );
	}

	public function test_json_encode_matches_to_array(): void {
		$sitemap = $this->make_sitemap( [ 'id' => 3, 'object_type' => 'term', 'object_subtype' => 'category' ] );

		$decoded = json_decode( wp_json_encode( $sitemap ), true );

		$this->assertEquals( $sitemap->to_array(), $decoded );
	}

	public function test_to_array_excludes_private_properties(): void {
		$item = $this->make_post_item( [ 'id' => 3 ] );

		$array = $item->to_array();

		$this->assertArrayNotHasKey( 'post', $array );
		$this->assertArrayHasKey( 'post_id', $array );
		$this->assertSame( 42, $array['post_id'] );
	}

	public function test_post_item_get_and_set_id(): void {
		$item = $this->make_post_item();

		$this->assertNull( $item->get_id() );
		$this->assertFalse( $item->exists() );

		$item->set_id( 99 );

		$this->assertSame( 99, $item->get_id() );
		$this->assertTrue( $item->exists() );
	}

	private function make_sitemap( array $overrides = [] ): Sitemap {
		return new Sitemap(
			array_merge(
				[
					'object_type'    => 'post',
					'object_subtype' => 'page',
					'item_count'     => 0,
					'status'         => Sitemap::STATUS_UNINDEXED,
				],
				$overrides
			)
		);
	}

	private function make_post_item( array $overrides = [] ): PostItem {
		return new PostItem(
			array_merge(
				[
					'post_id'         => 42,
					'sitemap_id'      => 7,
					'url'             => '/foo',
					'item_index'      => null,
					'next_item_index' => null,
				],
				$overrides
			)
		);
	}
}
