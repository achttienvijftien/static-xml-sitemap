<?php
/**
 * Logger.
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Logger
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Logger;

use AchttienVijftien\Plugin\StaticXMLSitemap\Cli;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Class Logger.
 */
class Logger extends AbstractLogger implements LoggerInterface {

	/** @var LoggerInterface[] */
	private array $loggers = [];

	public function __construct() {
		if ( Cli::is_wp_cli() ) {
			$this->loggers[] = new WpCli();

			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->loggers[] = new ErrorLog();
		}
	}

	public function for_source( string $source ): SourceAwareLogger {
		return new SourceAwareLogger( $this, $source );
	}

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
		foreach ( $this->loggers as $logger ) {
			$logger->log( $level, $message, $context );
		}
	}
}
