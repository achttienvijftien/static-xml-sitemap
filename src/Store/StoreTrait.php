<?php
/**
 * StoreTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Store
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Store;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityInterface;

trait StoreTrait {

	protected string $table;
	protected array $field_types = [];

	/**
	 * @template T
	 *
	 * @phpstan-param T       $entity
	 *
	 * @param EntityInterface $entity
	 *
	 * @phpstan-return T
	 * @return EntityInterface|false
	 */
	public function insert( EntityInterface $entity ) {
		global $wpdb;

		$data   = $entity->to_array();
		$format = $this->get_field_types( array_keys( $data ) );

		$num_rows = $wpdb->insert( $this->table, $data, $format );

		if ( false === $num_rows ) {
			return false;
		}

		$entity->set_id( $wpdb->insert_id );

		return $entity;
	}

	public function get_field_types( array $fields ): array {
		$field_types = [];

		foreach ( $fields as $field ) {
			$field_types[ $field ] = $this->field_types[ $field ] ?? '%s';
		}

		return $field_types;
	}

	public function update_query( $data, $where = null, $format = null, $where_format = null ) {
		global $wpdb;

		if ( null === $format ) {
			$format = $this->get_field_types( array_keys( $data ) );
		}

		if ( $where && null === $where_format ) {
			$where_format = $this->get_field_types( array_keys( $where ) );
		}

		return $wpdb->update( $this->table, $data, $where, $format, $where_format );
	}

	/**
	 * @template T
	 *
	 * @phpstan-param T       $entity
	 *
	 * @param EntityInterface $entity
	 *
	 * @return int|false
	 */
	public function update( EntityInterface $entity ) {
		global $wpdb;

		$id = $entity->get_id();
		if ( ! $id ) {
			return false;
		}

		$data = $entity->to_array();
		unset( $data['id'] );

		return $wpdb->update( $this->table, $data, [ 'id' => $id ] );
	}

	public function delete_where_id_in( array $ids ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return false;
		}

		$where = $wpdb->prepare(
			'id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
			$ids
		);

		return $wpdb->query( "DELETE FROM $this->table WHERE $where" );
	}

	public function query( string $query, array $prepare = null ) {
		global $wpdb;

		if ( $prepare ) {
			$query = $wpdb->prepare( $query, $prepare );
		}

		return $wpdb->query( $query );
	}

}
