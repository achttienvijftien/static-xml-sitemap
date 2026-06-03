<?php
/**
 * TermItem
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\Url;

/**
 * Class TermItem
 *
 * @implements SitemapItemInterface<\WP_Term>
 */
class TermItem implements SitemapItemInterface {

	use SitemapItemTrait, EntityTrait;

	public ?int $term_taxonomy_id;
	public ?string $last_modified;
	public ?int $last_modified_object_id;

	/**
	 * @var \WP_Term|null|false
	 */
	private $term = null;

	/**
	 * TermItem constructor.
	 *
	 * @param array|object{id: int, term_taxonomy_id: int, sitemap_id: int, url: string, last_modified: string|null, last_modified_object_id: int|null, item_index: int|null, next_item_index: int|null} $data
	 */
	public function __construct( $data ) {
		$data = PropertyAccessor::create( $data );

		$this->id                      = null !== $data->id ? (int) $data->id : null;
		$this->term_taxonomy_id        = null !== $data->term_taxonomy_id ? (int) $data->term_taxonomy_id : null;
		$this->sitemap_id              = (int) $data->sitemap_id;
		$this->url                     = $data->url;
		$this->last_modified           = $data->last_modified;
		$this->last_modified_object_id = null !== $data->last_modified_object_id ? (int) $data->last_modified_object_id : null;
		$this->item_index              = null !== $data->item_index ? (int) $data->item_index : null;
		$this->next_item_index         = null !== $data->next_item_index ? (int) $data->next_item_index : null;
	}

	public static function compare_objects( TermItem $a, TermItem $b ): int {
		$a_term = $a->get_object();
		$b_term = $b->get_object();

		if ( null === $a_term || null === $b_term ) {
			return 0;
		}

		return SitemapProvider::compare_objects( $a_term, $b_term );
	}

	public function get_object() {
		if ( null === $this->term && null !== $this->term_taxonomy_id ) {
			$term       = get_term_by( 'term_taxonomy_id', $this->term_taxonomy_id );
			$this->term = $term ?: false;
		}

		return $this->term ?: null;
	}

	/**
	 * @param \WP_Term|int $object
	 * @param Sitemap      $sitemap
	 *
	 * @return TermItem|null
	 */
	public static function for_object( $object, Sitemap $sitemap ): ?TermItem {
		if ( is_int( $object ) ) {
			$object = get_term_by( 'term_taxonomy_id', $object );
		}

		if ( ! $object instanceof \WP_Term ) {
			return null;
		}

		$data = [
			'term_taxonomy_id' => $object->term_taxonomy_id,
			'url'              => get_term_link( $object ),
			'sitemap_id'       => $sitemap->id,
		];

		$data['url'] = apply_filters( 'static_sitemap_term_url', $data['url'], $object );

		if ( empty( $data['url'] ) || ! Url::is_site_url( $data['url'] ) ) {
			return null;
		}

		$data['url'] = Url::remove_home_url( $data['url'] );

		$data = apply_filters( 'static_sitemap_term_item_data', $data, $object );

		if ( ! is_array( $data ) || empty( $data['url'] ) ) {
			return null;
		}

		return new TermItem( $data );
	}

	public function get_object_id(): ?int {
		return $this->term_taxonomy_id;
	}

	public function get_modified(): ?string {
		return $this->last_modified;
	}

	public function __toString() {
		return 'TermItem { '
			. "id: $this->id, "
			. "term_taxonomy_id: $this->term_taxonomy_id, "
			. "sitemap_id: $this->sitemap_id, "
			. "url: $this->url, "
			. "item_index: $this->item_index, "
			. "next_item_index: $this->next_item_index"
			. ' }';
	}

	public function get_field( string $field ) {
		switch ( $field ) {
			case 'id':
				return $this->get_object_id();
			case 'modified':
				return $this->get_modified();
			default:
				$term = $this->get_object();

				return $term && property_exists( $term, $field ) ? $term->$field : null;
		}
	}
}
