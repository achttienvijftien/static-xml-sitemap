<?php
/**
 * This file contains implementation of a lock mechanism.
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Lock
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Lock;

/**
 * Class Lock
 */
class Lock {

	private const MAX_LOCK_AGE = 60 * 5;
	private const MAX_TRIES    = 10;
	private const DEFAULT_WAIT = 60;

	private string $name;
	private bool $have_lock = false;
	private int $wait = self::DEFAULT_WAIT;

	/**
	 * Lock constructor.
	 *
	 * @param string $name Name of the lock.
	 */
	public function __construct( string $name ) {
		$this->name = "sitemap_lock_$name";
	}

	/**
	 * Lock destructor.
	 */
	public function __destruct() {
		$this->release();
	}

	/**
	 * Releases the lock.
	 *
	 * @param int|null $lock_time If given, lock will only be released if its timestamp matches.
	 * @param bool     $force Force release when locked.
	 *
	 * @return void
	 */
	public function release( int $lock_time = null, bool $force = false ): void {
		global $wpdb;

		if ( $this->have_lock || $force ) {
			$where = [ 'option_name' => $this->name ];

			if ( $lock_time ) {
				$where['option_value'] = (string) $lock_time;
			}

			$wpdb->delete( $wpdb->options, $where );

			$this->have_lock = false;
		}
	}

	/**
	 * Tries to acquire the lock.
	 *
	 * @param int|null $wait Time in seconds to keep trying to acquire the lock.
	 *
	 * @return bool
	 */
	public function acquire( int $wait = null ): bool {
		global $wpdb;

		$wait ??= $this->wait;

		$suppress_errors = $wpdb->suppress_errors();

		$time_wait  = 1;
		$acquired   = false;
		$time_start = time();
		$tries      = 0;

		do {
			$now = $tries > 0 ? time() : $time_start;

			$lock_time = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
					$this->name
				)
			);

			$lock_age = $lock_time ? $now - $lock_time : null;

			if ( null !== $lock_age && $lock_age > self::MAX_LOCK_AGE ) {
				$this->release( $lock_time, true );
			}

			$lock_result = $wpdb->insert(
				$wpdb->options,
				[
					'option_name'  => $this->name,
					'option_value' => time(),
					'autoload'     => 'no',
				],
				[ '%s', '%d', '%s' ]
			);

			if ( false !== $lock_result ) {
				$acquired = true;
				break;
			}

			if ( ( $now + $time_wait ) - $time_start >= $wait ) {
				break;
			}

			sleep( $time_wait );

			$time_wait *= 2;
			$tries++;
		} while ( ( $now - $time_start < $wait ) && $tries < self::MAX_TRIES );

		$wpdb->suppress_errors( $suppress_errors );

		$this->have_lock = $acquired;

		return $acquired;
	}

	public function set_wait( int $wait ): Lock {
		$this->wait = $wait;

		return $this;
	}
}
