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

	private const MAX_LOCK_AGE      = 60 * 5;
	private const DEFAULT_MAX_TRIES = 10;
	private const DEFAULT_WAIT      = 60;

	private string $name;
	private bool $have_lock = false;
	private ?int $lock_time = null;
	private int $wait       = self::DEFAULT_WAIT;
	private int $max_tries  = self::DEFAULT_MAX_TRIES;

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
	public function release( ?int $lock_time = null, bool $force = false ): void {
		global $wpdb;

		if ( $this->have_lock || $force ) {
			$where = [ 'option_name' => $this->name ];

			if ( $lock_time ) {
				$where['option_value'] = (string) $lock_time;
			}

			$wpdb->delete( $wpdb->options, $where );

			$this->have_lock = false;
			$this->lock_time = null;
		}
	}

	/**
	 * Tries to acquire the lock.
	 *
	 * @param int|null $wait Time in seconds to keep trying to acquire the lock.
	 *
	 * @return bool
	 */
	public function acquire( ?int $wait = null ): bool {
		global $wpdb;

		$wait ??= $this->wait;

		$suppress_errors = $wpdb->suppress_errors();

		$time_wait  = 1;
		$acquired   = false;
		$time_start = time();
		$tries      = 0;

		do {
			$now = $tries > 0 ? time() : $time_start;

			$existing_lock_time = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
					$this->name
				)
			);

			$lock_age = $existing_lock_time ? $now - $existing_lock_time : null;

			if ( null !== $lock_age && $lock_age > self::MAX_LOCK_AGE ) {
				$this->release( $existing_lock_time, true );
			}

			$lock_time = time();

			$lock_result = $wpdb->insert(
				$wpdb->options,
				[
					'option_name'  => $this->name,
					'option_value' => $lock_time,
					'autoload'     => 'no',
				],
				[ '%s', '%d', '%s' ]
			);

			if ( false !== $lock_result ) {
				$acquired        = true;
				$this->lock_time = $lock_time;
				break;
			}

			if ( ( $now + $time_wait ) - $time_start >= $wait ) {
				break;
			}

			if ( $this->max_tries <= 1 ) {
				break;
			}

			sleep( $time_wait );

			$time_wait *= 2;
			++$tries;
		} while ( ( $now - $time_start < $wait ) && $tries < $this->max_tries );

		$wpdb->suppress_errors( $suppress_errors );

		$this->have_lock = $acquired;

		return $acquired;
	}

	public function refresh(): bool {
		global $wpdb;

		if ( ! $this->have_lock ) {
			return false;
		}

		if ( time() < $this->lock_time + ( self::MAX_LOCK_AGE * 0.75 ) ) {
			return true;
		}

		$lock_time = time();
		$updated   = (bool) $wpdb->update(
			$wpdb->options,
			[ 'option_value' => $lock_time ],
			[
				'option_name'  => $this->name,
				'option_value' => $this->lock_time,
			]
		);

		if ( $updated ) {
			$this->lock_time = $lock_time;
		}

		return $updated;
	}

	public function set_wait( int $wait ): Lock {
		$this->wait = $wait;

		return $this;
	}

	public function set_max_tries( int $max_tries ): Lock {
		$this->max_tries = $max_tries;

		return $this;
	}
}
