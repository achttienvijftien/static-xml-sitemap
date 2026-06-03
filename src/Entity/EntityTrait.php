<?php
/**
 * EntityTrait
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Entity;

use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

trait EntityTrait {

	public ?int $id = null;

	public function to_array(): array {
		return PropertyAccessor::get_public_object_vars( $this );
	}

	public function get_id(): ?int {
		return $this->id;
	}

	public function set_id( int $id ): void {
		$this->id = $id;
	}

	public function exists(): bool {
		return null !== $this->id;
	}

}
