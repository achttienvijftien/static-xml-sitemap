<?php
/**
 * DateFormatterTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Renderer;

trait DateFormatterTrait {

	/**
	 * @param mixed|string $date
	 *
	 * @return mixed|string
	 */
	private function format_date( $date ) {
		if ( ! is_string( $date ) ) {
			return $date;
		}

		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $date, timezone_open( 'UTC' ) );

		if ( ! $datetime ) {
			return $date;
		}

		return $datetime->format( \DATE_W3C );
	}

}
