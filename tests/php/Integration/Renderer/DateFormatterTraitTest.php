<?php
/**
 * DateFormatterTraitTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Renderer;

use AchttienVijftien\Plugin\StaticXMLSitemap\Renderer\DateFormatterTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;

/**
 * Class DateFormatterTraitTest
 */
class DateFormatterTraitTest extends TestCase {

	/**
	 * Returns an object exposing the trait's private format_date method.
	 *
	 * @return object
	 */
	private function formatter(): object {
		return new class() {

			use DateFormatterTrait;

			/**
			 * Calls the wrapped format_date method.
			 *
			 * @param mixed $date Date to format.
			 *
			 * @return mixed
			 */
			public function call( $date ) {
				return $this->format_date( $date );
			}
		};
	}

	public function test_null_is_returned_unchanged(): void {
		$this->assertNull( $this->formatter()->call( null ) );
	}

	public function test_non_string_is_returned_unchanged(): void {
		$this->assertSame( 1234, $this->formatter()->call( 1234 ) );
	}

	public function test_valid_datetime_is_formatted_as_w3c(): void {
		$this->assertSame(
			'2020-01-02T03:04:05+00:00',
			$this->formatter()->call( '2020-01-02 03:04:05' )
		);
	}

	public function test_unparsable_string_is_returned_unchanged(): void {
		$this->assertSame( 'not a date', $this->formatter()->call( 'not a date' ) );
	}

	public function test_zero_datetime_is_interpreted_as_negative_year(): void {
		$this->assertSame(
			'-0001-11-30T00:00:00+00:00',
			$this->formatter()->call( '0000-00-00 00:00:00' )
		);
	}
}
