<?php
/**
 * SourceAwareLogger
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Logger
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Logger;

use Psr\Log\LogLevel;

class SourceAwareLogger {

	protected Logger $logger;
	protected string $source;

	public function __construct( Logger $logger, string $source ) {
		$this->logger = $logger;
		$this->source = $source;
	}

	/**
	 * System is unusable.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function emergency( $message, array $context = [] ) {
		$this->logger->log( LogLevel::EMERGENCY, "$this->source: $message", $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function alert( $message, array $context = [] ) {
		$this->logger->log( LogLevel::ALERT, "$this->source: $message", $context );
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function critical( $message, array $context = [] ) {
		$this->logger->log( LogLevel::CRITICAL, "$this->source: $message", $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function error( $message, array $context = [] ) {
		$this->logger->log( LogLevel::ERROR, "$this->source: $message", $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function warning( $message, array $context = [] ) {
		$this->logger->log( LogLevel::WARNING, "$this->source: $message", $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function notice( $message, array $context = [] ) {
		$this->logger->log( LogLevel::NOTICE, "$this->source: $message", $context );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function info( $message, array $context = [] ) {
		$this->logger->log( LogLevel::INFO, "$this->source: $message", $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function debug( $message, array $context = [] ) {
		$this->logger->log( LogLevel::DEBUG, "$this->source: $message", $context );
	}

}
