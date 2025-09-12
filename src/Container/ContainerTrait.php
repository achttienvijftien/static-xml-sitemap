<?php
/**
 * ContainerTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Container
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Container;

trait ContainerTrait {

	protected array $services = [];
	protected array $factories = [];
	protected array $parameters = [];

	/**
	 * @template T of object
	 *
	 * @param class-string<T> $id
	 *
	 * @return T
	 * @throws ServiceNotFoundException
	 */
	public function get( string $id ) {
		$service = $this->services[ $id ] ?? null;

		if ( null !== $service ) {
			return $service;
		}

		$factory = $this->factories[ $id ] ?? null;

		if ( null === $factory ) {
			throw new ServiceNotFoundException( "Service '$id' not found in container." );
		}

		$this->services[ $id ] = ( $this->factories[ $id ] )( $id );

		return $this->services[ $id ];
	}

	public function has( string $id ): bool {
		return isset( $this->services[ $id ] ) || isset( $this->factories[ $id ] );
	}

	public function get_parameter( string $name ) {
		return $this->parameters[ $name ] ?? null;
	}

	public function has_parameter( string $name ): bool {
		return key_exists( $name, $this->parameters );
	}

	protected function register( string $id, callable $factory ) {
		$this->factories[ $id ] = $factory;
	}
}
