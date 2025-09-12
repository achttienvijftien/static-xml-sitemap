<?php
/**
 * WithLockTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Lock
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Lock;

trait WithLockTrait {

	protected function with_lock( Lock $lock, $callback ) {
		try {
			return $lock->acquire() && is_callable( $callback ) ? $callback() : null;
		} finally {
			$lock->release();
		}
	}

}
