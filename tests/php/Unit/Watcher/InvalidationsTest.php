<?php
/**
 * InvalidationsTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Watcher;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Watcher\Invalidations;

/**
 * Class InvalidationsTest
 */
class InvalidationsTest extends TestCase {

	public function test_flags_are_distinct_powers_of_two(): void {
		$this->assertSame( 1, Invalidations::IS_INDEXABLE );
		$this->assertSame( 2, Invalidations::ITEM_INDEX );
		$this->assertSame( 4, Invalidations::ITEM_URL );
		$this->assertSame( 8, Invalidations::OBJECT_EXISTS );
		$this->assertSame( 16, Invalidations::ITEM_LAST_MODIFIED );
	}

	public function test_combined_mask_reports_added_flags(): void {
		$mask = Invalidations::IS_INDEXABLE | Invalidations::ITEM_URL;

		$this->assertSame( Invalidations::IS_INDEXABLE, $mask & Invalidations::IS_INDEXABLE );
		$this->assertSame( Invalidations::ITEM_URL, $mask & Invalidations::ITEM_URL );
		$this->assertSame( 0, $mask & Invalidations::ITEM_INDEX );
		$this->assertSame( 0, $mask & Invalidations::OBJECT_EXISTS );
	}

	public function test_full_mask_contains_every_flag(): void {
		$flags = $this->all_flags();
		$full  = array_reduce( $flags, static fn( $carry, $flag ) => $carry | $flag, 0 );

		$this->assertSame( 31, $full );

		foreach ( $flags as $flag ) {
			$this->assertSame( $flag, $full & $flag );
		}
	}

	public function test_flags_do_not_overlap(): void {
		$flags = $this->all_flags();

		foreach ( $flags as $left ) {
			foreach ( $flags as $right ) {
				if ( $left === $right ) {
					continue;
				}

				$this->assertSame( 0, $left & $right );
			}
		}
	}

	/**
	 * @return int[]
	 */
	private function all_flags(): array {
		return [
			Invalidations::IS_INDEXABLE,
			Invalidations::ITEM_INDEX,
			Invalidations::ITEM_URL,
			Invalidations::OBJECT_EXISTS,
			Invalidations::ITEM_LAST_MODIFIED,
		];
	}
}
