<?php
/**
 * EntityInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Entity;

interface EntityInterface {

	public function get_id(): ?int;

	public function set_id( int $id ): void;

	public function to_array(): array;

	public function exists(): bool;

}
