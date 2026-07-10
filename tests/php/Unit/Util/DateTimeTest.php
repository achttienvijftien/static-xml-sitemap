<?php
/**
 * DateTimeTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Util;

use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\DateTime;

/**
 * Class DateTimeTest
 */
class DateTimeTest extends TestCase {

	public function test_strtotime_parses_mysql_string_as_utc(): void {
		$expected = ( new \DateTimeImmutable( '2020-01-02 03:04:05', new \DateTimeZone( 'UTC' ) ) )->getTimestamp();

		$this->assertSame( $expected, DateTime::strtotime( '2020-01-02 03:04:05' ) );
	}

	public function test_strtotime_respects_timezone_argument(): void {
		$expected = ( new \DateTimeImmutable( '2020-01-02 03:04:05', new \DateTimeZone( 'America/New_York' ) ) )->getTimestamp();

		$this->assertSame( $expected, DateTime::strtotime( '2020-01-02 03:04:05', DateTime::MYSQL, 'America/New_York' ) );
	}

	public function test_strtotime_parses_custom_format(): void {
		$expected = ( new \DateTimeImmutable( '2021-06-15T10:20:30', new \DateTimeZone( 'UTC' ) ) )->getTimestamp();

		$this->assertSame( $expected, DateTime::strtotime( '2021-06-15T10:20:30', 'Y-m-d\TH:i:s' ) );
	}

	public function test_strtotime_returns_null_for_unparsable_string(): void {
		$this->assertNull( DateTime::strtotime( 'not-a-date' ) );
	}

	public function test_strtotime_returns_null_for_epoch(): void {
		$this->assertNull( DateTime::strtotime( '1970-01-01 00:00:00' ) );
	}

	public function test_to_mysql_formats_integer_timestamp(): void {
		$this->assertSame( gmdate( DateTime::MYSQL, 1577934245 ), DateTime::to_mysql( 1577934245 ) );
	}

	public function test_to_mysql_formats_zero_timestamp(): void {
		$this->assertSame( '1970-01-01 00:00:00', DateTime::to_mysql( 0 ) );
	}

	public function test_to_mysql_formats_numeric_string(): void {
		$this->assertSame( gmdate( DateTime::MYSQL, 1577934245 ), DateTime::to_mysql( '1577934245' ) );
	}

	public function test_to_mysql_returns_valid_string_unchanged(): void {
		$this->assertSame( '2020-01-02 03:04:05', DateTime::to_mysql( '2020-01-02 03:04:05' ) );
	}

	public function test_to_mysql_returns_null_for_invalid_string(): void {
		$this->assertNull( DateTime::to_mysql( 'not-a-date' ) );
	}

	public function test_to_mysql_returns_null_for_float(): void {
		$this->assertNull( DateTime::to_mysql( 1.5 ) );
	}

	public function test_to_mysql_returns_null_for_null(): void {
		$this->assertNull( DateTime::to_mysql( null ) );
	}

	public function test_to_mysql_returns_null_for_array(): void {
		$this->assertNull( DateTime::to_mysql( [] ) );
	}
}
