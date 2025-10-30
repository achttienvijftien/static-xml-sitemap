<?php
/**
 * Sitemap
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Lock\Lock;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

/**
 * Class Sitemap
 */
class Sitemap implements EntityInterface {

	use EntityTrait;

	public const STATUS_UNINDEXED = 'unindexed';
	public const STATUS_INDEXED   = 'indexed';
	public const STATUS_INDEXING  = 'indexing';
	public const STATUS_UPDATING  = 'updating';

	public ?string $object_type;
	public ?string $object_subtype;
	public ?string $last_modified;
	public ?int $last_object_id;
	public ?int $last_item_index;
	public ?string $last_indexed_value;
	public ?int $last_indexed_id;
	public ?int $item_count;
	public ?string $status;

	/**
	 * Sitemap constructor.
	 *
	 * @param array|object{id: int, object_type: string, object_subtype: string, last_modified: string, last_object_id: int, last_item_index: int, last_indexed_value: mixed, last_indexed_it: int, item_count: int, status: string} $data
	 */
	public function __construct( $data ) {
		$data = PropertyAccessor::create( $data );

		$this->id                 = null !== $data->id ? (int) $data->id : null;
		$this->object_type        = $data->object_type;
		$this->object_subtype     = $data->object_subtype;
		$this->last_modified      = $data->last_modified;
		$this->last_object_id     = null !== $data->last_object_id ? (int) $data->last_object_id : null;
		$this->last_item_index    = null !== $data->last_item_index ? (int) $data->last_item_index : null;
		$this->last_indexed_value = $data->last_indexed_value;
		$this->last_indexed_id    = null !== $data->last_indexed_id ? (int) $data->last_indexed_id : null;
		$this->item_count         = (int) $data->item_count;
		$this->status             = $data->status;
	}

	/**
	 * @param Sitemap|int $sitemap
	 *
	 * @return Lock
	 */
	public static function get_lock( $sitemap ): Lock {
		$id = $sitemap instanceof Sitemap ? $sitemap->id : $sitemap;

		return new Lock( "sitemap_$id" );
	}

	public function append( SitemapItemInterface $object ) {
		$object->set_item_index( $this->item_count );

		$this->last_modified   = $object->get_modified();
		$this->last_object_id  = $object->get_object_id();
		$this->last_item_index = $object->get_item_index();

		$this->item_count++;
	}

	public function is_updating(): bool {
		return $this->status !== self::STATUS_INDEXED;
	}

	public function __toString() {
		$type = $this->get_description();

		return "Sitemap { id: $this->id, type: $type }";
	}

	public function get_description(): string {
		switch ( $this->object_type ) {
			case 'post':
				return "post type $this->object_subtype";
			case 'user':
				return "authors";
			case 'term':
				return "taxonomy $this->object_subtype";
			default:
				return '';
		}
	}

	public static function for_object_type( string $object_type, string $object_subtype = null ): ?self {
		$sitemap = new self( [
			'object_type'    => $object_type,
			'object_subtype' => $object_subtype,
		] );

		return $sitemap->initialize();
	}

	private function initialize(): self {
		$this->item_count         = 0;
		$this->last_modified      = null;
		$this->last_object_id     = null;
		$this->last_item_index    = null;
		$this->last_indexed_id    = null;
		$this->last_indexed_value = null;
		$this->status             = Sitemap::STATUS_UNINDEXED;

		return $this;
	}

	public function reset(): void {
		$this->initialize();
	}

}
