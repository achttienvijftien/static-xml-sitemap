<?php
/**
 * PostItem
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Entity
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Post;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\Sitemap;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

/**
 * Class PostItem
 *
 * @implements SitemapItemInterface<\WP_Post>
 */
class PostItem implements SitemapItemInterface {

	use SitemapItemTrait, EntityTrait;

	public ?int $post_id;

	/**
	 * @var \WP_Post|null|false
	 */
	private $post = null;

	/**
	 * PostItem constructor.
	 *
	 * @param array|object{id: int, post_id: int, sitemap_id: int, url: string, item_index: int|null, next_item_index: int|null} $data
	 */
	public function __construct( $data ) {
		$data = PropertyAccessor::create( $data );

		$this->id              = null !== $data->id ? (int) $data->id : null;
		$this->post_id         = null !== $data->post_id ? (int) $data->post_id : null;
		$this->sitemap_id      = (int) $data->sitemap_id;
		$this->url             = $data->url;
		$this->item_index      = $data->item_index !== null ? (int) $data->item_index : null;
		$this->next_item_index = $data->next_item_index !== null ? (int) $data->next_item_index : null;
	}

	public static function compare( PostItem $a, PostItem $b ): int {
		$a_post = $a->get_object();
		$b_post = $b->get_object();

		return $a_post->post_modified_gmt === $b_post->post_modified_gmt
			? $a_post->ID <=> $b_post->ID
			: $a_post->post_modified_gmt <=> $b_post->post_modified_gmt;
	}

	public function get_object() {
		if ( null === $this->post && null !== $this->post_id ) {
			$post       = get_post( $this->post_id );
			$this->post = $post ?: false;
		}

		return $this->post ?: null;
	}

	/**
	 * @param \WP_Post|int $post
	 * @param Sitemap      $sitemap
	 *
	 * @return PostItem|null
	 */
	public static function for_post( $post, Sitemap $sitemap ): ?PostItem {
		if ( ! $post instanceof \WP_Post ) {
			$post = get_post( $post );
		}

		if ( ! $post ) {
			return null;
		}

		$data = [
			'post_id'    => $post->ID,
			'url'        => get_permalink( $post->ID ),
			'sitemap_id' => $sitemap->id,
		];

		$data['url'] = apply_filters( 'static_sitemap_post_url', $data['url'], $post );

		if ( empty( $data['url'] ) ) {
			return null;
		}

		$home_url_regex = preg_quote( untrailingslashit( home_url() ), '|' );

		if ( ! preg_match( "|^$home_url_regex(.+)$|", $data['url'], $matches ) ) {
			return null;
		}

		$data['url'] = $matches[1];

		return new PostItem( $data );
	}

	public function get_object_id(): ?int {
		return $this->post_id;
	}

	public function get_modified(): ?string {
		$post = $this->get_object();

		return $post ? $post->post_modified_gmt : null;
	}

	public function __toString() {
		return 'PostItem { '
			. "id: $this->id, "
			. "post_id: $this->post_id, "
			. "sitemap_id: $this->sitemap_id, "
			. "url: $this->url, "
			. "item_index: $this->item_index, "
			. "next_item_index: $this->next_item_index"
			. ' }';
	}
}
