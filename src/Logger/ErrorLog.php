<?php
/**
 * ErrorLog
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Logger
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class ErrorLog
 */
class ErrorLog extends AbstractLogger implements LoggerInterface {

	use LogFormatterTrait;

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 *
	 * @return void
	 */
	public function log( $level, $message, array $context = [] ): void {
		$message = $this->format( $message );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- error logging is the purpose of this logger.
		error_log( "$level: $message" );
	}
}
