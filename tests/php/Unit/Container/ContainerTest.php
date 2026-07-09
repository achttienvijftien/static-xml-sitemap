<?php
/**
 * ContainerTest
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Container
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Unit\Container;

use AchttienVijftien\Plugin\StaticXMLSitemap\Container\Container;
use AchttienVijftien\Plugin\StaticXMLSitemap\Container\ContainerTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Container\ServiceNotFoundException;
use AchttienVijftien\Plugin\StaticXMLSitemap\Installer;
use AchttienVijftien\Plugin\StaticXMLSitemap\Tests\TestCase;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class ContainerTest
 */
class ContainerTest extends TestCase {

	public function test_add_parameters_returns_self(): void {
		$container = new Container();

		$this->assertSame( $container, $container->add_parameters( [ 'page_size' => 1000 ] ) );
	}

	public function test_get_parameter_returns_stored_value(): void {
		$container = ( new Container() )->add_parameters( [ 'page_size' => 1000 ] );

		$this->assertSame( 1000, $container->get_parameter( 'page_size' ) );
	}

	public function test_get_parameter_returns_null_for_unknown_parameter(): void {
		$container = new Container();

		$this->assertNull( $container->get_parameter( 'does_not_exist' ) );
	}

	public function test_has_parameter_reflects_presence(): void {
		$container = new Container();

		$this->assertFalse( $container->has_parameter( 'page_size' ) );

		$container->add_parameters( [ 'page_size' => 1000 ] );

		$this->assertTrue( $container->has_parameter( 'page_size' ) );
		$this->assertFalse( $container->has_parameter( 'unknown' ) );
	}

	public function test_add_parameters_overwrites_existing_value(): void {
		$container = ( new Container() )->add_parameters( [ 'page_size' => 1000 ] );

		$container->add_parameters( [ 'page_size' => 2000 ] );

		$this->assertSame( 2000, $container->get_parameter( 'page_size' ) );
	}

	public function test_add_parameters_keeps_null_value_as_present(): void {
		$container = ( new Container() )->add_parameters( [ 'nullable' => null ] );

		$this->assertTrue( $container->has_parameter( 'nullable' ) );
		$this->assertNull( $container->get_parameter( 'nullable' ) );
	}

	public function test_has_returns_true_for_registered_service(): void {
		$container = new Container();

		$this->assertTrue( $container->has( Installer::class ) );
	}

	public function test_has_returns_false_for_unknown_service(): void {
		$container = new Container();

		$this->assertFalse( $container->has( 'Some\\Unregistered\\Service' ) );
	}

	public function test_get_resolves_registered_service(): void {
		$container = new Container();

		$this->assertInstanceOf( Installer::class, $container->get( Installer::class ) );
	}

	public function test_get_memoizes_service_within_container(): void {
		$container = new Container();

		$this->assertSame( $container->get( Installer::class ), $container->get( Installer::class ) );
	}

	public function test_get_returns_distinct_instances_across_containers(): void {
		$first  = new Container();
		$second = new Container();

		$this->assertNotSame( $first->get( Installer::class ), $second->get( Installer::class ) );
	}

	public function test_get_throws_service_not_found_for_unknown_service(): void {
		$container = new Container();

		$this->expectException( ServiceNotFoundException::class );

		$container->get( 'Some\\Unregistered\\Service' );
	}

	public function test_service_not_found_exception_is_psr_not_found_and_names_service(): void {
		$container = new Container();

		try {
			$container->get( 'Some\\Missing\\Service' );
			$this->fail( 'Expected ServiceNotFoundException was not thrown.' );
		} catch ( ServiceNotFoundException $exception ) {
			$this->assertInstanceOf( NotFoundExceptionInterface::class, $exception );
			$this->assertStringContainsString( 'Some\\Missing\\Service', $exception->getMessage() );
		}
	}

	public function test_factory_is_invoked_once_and_receives_id(): void {
		$container = $this->create_trait_container();
		$calls     = 0;

		$container->register_service(
			'service.a',
			static function ( $id ) use ( &$calls ) {
				++$calls;

				return (object) [ 'id' => $id ];
			}
		);

		$first  = $container->get( 'service.a' );
		$second = $container->get( 'service.a' );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, $calls );
		$this->assertSame( 'service.a', $first->id );
	}

	public function test_trait_has_reflects_registered_factory(): void {
		$container = $this->create_trait_container();

		$this->assertFalse( $container->has( 'service.a' ) );

		$container->register_service( 'service.a', static fn() => new \stdClass() );

		$this->assertTrue( $container->has( 'service.a' ) );
	}

	private function create_trait_container(): object {
		return new class() {

			use ContainerTrait;

			public function register_service( string $id, callable $factory ): void {
				$this->register( $id, $factory );
			}
		};
	}
}
