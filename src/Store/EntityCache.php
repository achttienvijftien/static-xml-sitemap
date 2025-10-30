<?php
/**
 * EntityCache
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityInterface;

/**
 * Class EntityCache
 */
class EntityCache {

	protected array $entities = [];
	protected array $tags = [];
	/**
	 * @var callable
	 */
	protected $tagger = null;

	public function get( int $id ) {
		return $this->entities[ $id ] ?? null;
	}

	public function get_by_tag( array $tag ) {
		$value = reset( $tag );
		$key   = key( $tag );
		$id    = $this->tags[ $key ][ $value ] ?? null;

		if ( null === $id ) {
			return null;
		}

		return $this->entities[ $id ] ?? null;
	}

	public function add( EntityInterface $entity ): void {
		$this->entities[ $entity->get_id() ] = $entity;

		if ( null === $this->tagger ) {
			return;
		}

		$tags = ( $this->tagger )( $entity );

		foreach ( $tags as $tag => $value ) {
			$existing_value = null;
			if ( key_exists( $tag, $this->tags ) ) {
				$existing_value = array_search( $entity->get_id(), $this->tags[ $tag ], true );
			}

			if ( $existing_value === $value ) {
				continue;
			}

			if ( null !== $existing_value ) {
				unset( $this->tags[ $tag ][ $existing_value ] );
			}

			$this->tags[ $tag ][ $value ] = $entity->get_id();
		}
	}

	public function delete( int $entity_id ) {
		unset( $this->entities[ $entity_id ] );

		foreach ( $this->tags as &$ids ) {
			$ids = array_filter( $ids, fn( $id ) => $entity_id !== $id );
		}
	}

	public function clear() {
		$this->entities = [];
		$this->tags     = [];
	}

	public function set_tagger( callable $tagger ): EntityCache {
		$this->tagger = $tagger;

		return $this;
	}
}
