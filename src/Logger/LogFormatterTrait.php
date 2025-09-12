<?php
/**
 * LogFormatterTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Logger
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Logger;

trait LogFormatterTrait {

	protected function format( string $message ): string {
		return str_replace( 'AchttienVijftien\\Plugin\\StaticXMLSitemap\\', '', $message );
	}

}
