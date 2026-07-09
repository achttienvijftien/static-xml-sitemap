<?php
/**
 * LockTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Lock
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Lock;

use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\WithLockTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class LockTest
 */
class LockTest extends TestCase {

	use WithLockTrait;

	private function option_exists( string $name ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->options WHERE option_name = %s",
				"sitemap_lock_$name"
			)
		);
	}

	public function test_acquire_succeeds(): void {
		$lock = new Lock( 'acquire' );

		$this->assertTrue( $lock->acquire() );
		$this->assertTrue( $this->option_exists( 'acquire' ) );

		$lock->release();
	}

	public function test_second_acquire_fails_while_held(): void {
		$holder = new Lock( 'contended' );
		$this->assertTrue( $holder->acquire() );

		$contender = ( new Lock( 'contended' ) )->set_max_tries( 1 );
		$this->assertFalse( $contender->acquire() );

		$holder->release();
	}

	public function test_release_allows_reacquire(): void {
		$first = new Lock( 'reacquire' );
		$this->assertTrue( $first->acquire() );
		$first->release();

		$this->assertFalse( $this->option_exists( 'reacquire' ) );

		$second = ( new Lock( 'reacquire' ) )->set_max_tries( 1 );
		$this->assertTrue( $second->acquire() );

		$second->release();
	}

	public function test_max_tries_one_fails_fast_when_held(): void {
		$holder = new Lock( 'fastfail' );
		$holder->acquire();

		$contender = ( new Lock( 'fastfail' ) )->set_max_tries( 1 )->set_wait( 300 );

		$start    = microtime( true );
		$acquired = $contender->acquire();
		$elapsed  = microtime( true ) - $start;

		$this->assertFalse( $acquired );
		$this->assertLessThan( 2, $elapsed );

		$holder->release();
	}

	public function test_stale_lock_is_released_and_reacquired(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => 'sitemap_lock_stale',
				'option_value' => (string) ( time() - 400 ),
				'autoload'     => 'no',
			],
			[ '%s', '%s', '%s' ]
		);

		$lock = ( new Lock( 'stale' ) )->set_max_tries( 1 );

		$this->assertTrue( $lock->acquire() );

		$lock->release();
	}

	public function test_fresh_lock_is_not_stolen(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->options,
			[
				'option_name'  => 'sitemap_lock_fresh',
				'option_value' => (string) time(),
				'autoload'     => 'no',
			],
			[ '%s', '%s', '%s' ]
		);

		$lock = ( new Lock( 'fresh' ) )->set_max_tries( 1 );

		$this->assertFalse( $lock->acquire() );
	}

	public function test_refresh_returns_false_without_lock(): void {
		$lock = new Lock( 'refresh_none' );

		$this->assertFalse( $lock->refresh() );
	}

	public function test_refresh_returns_true_for_fresh_lock(): void {
		$lock = new Lock( 'refresh_fresh' );
		$lock->acquire();

		$this->assertTrue( $lock->refresh() );

		$lock->release();
	}

	public function test_release_with_mismatched_time_keeps_lock(): void {
		$lock = new Lock( 'mismatch' );
		$lock->acquire();

		$lock->release( 1, true );

		$this->assertTrue( $this->option_exists( 'mismatch' ) );

		$lock->release();
	}

	public function test_with_lock_runs_callback_and_releases(): void {
		$lock = new Lock( 'with_lock' );

		$ran    = false;
		$result = $this->with_lock(
			$lock,
			function () use ( &$ran ) {
				$ran = true;

				return 'done';
			}
		);

		$this->assertTrue( $ran );
		$this->assertSame( 'done', $result );
		$this->assertFalse( $this->option_exists( 'with_lock' ) );
	}

	public function test_with_lock_skips_callback_when_not_acquired(): void {
		$holder = new Lock( 'with_lock_held' );
		$holder->acquire();

		$ran      = false;
		$contender = ( new Lock( 'with_lock_held' ) )->set_max_tries( 1 );

		$result = $this->with_lock(
			$contender,
			function () use ( &$ran ) {
				$ran = true;

				return 'should-not-run';
			}
		);

		$this->assertFalse( $ran );
		$this->assertNull( $result );
		$this->assertTrue( $this->option_exists( 'with_lock_held' ) );

		$holder->release();
	}
}
