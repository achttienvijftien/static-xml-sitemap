<?php
/**
 * DateTime
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Util;

/**
 * Class DateTime
 */
class DateTime {

	public const MYSQL = 'Y-m-d H:i:s';

	public static function strtotime( $str, $format = self::MYSQL, string $timezone = 'UTC' ): ?int {
		$timezone = timezone_open( $timezone );
		$datetime = date_create_immutable_from_format( $format, $str, $timezone );

		if ( ! $datetime instanceof \DateTimeImmutable ) {
			return null;
		}

		$time = $datetime->getTimestamp();

		return $time > 0 ? $time : null;
	}

	public static function to_mysql( $value ): ?string {
		if ( is_string( $value ) ) {
			if ( ctype_digit( $value ) ) {
				return gmdate( self::MYSQL, (int) $value );
			}

			if ( ! date_parse_from_format( self::MYSQL, $value )['error_count'] ) {
				return $value;
			}

			return null;
		}

		if ( is_int( $value ) ) {
			return gmdate( self::MYSQL, $value );
		}

		return null;
	}

}
