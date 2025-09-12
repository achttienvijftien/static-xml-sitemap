<?php
/**
 * WpCli
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Logger
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class WpCli
 */
class WpCli extends AbstractLogger implements LoggerInterface {

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

		switch ( $level ) {
			case 'debug':
			case 'info':
				\WP_CLI::log( $message );
				break;
			case 'notice':
				\WP_CLI::line( $message );
				break;
			case 'warning':
			case 'error':
			case 'critical':
			case 'alert':
			case 'emergency':
				\WP_CLI::warning( $message );
				break;
		}
	}
}
