<?php
/**
 * Job
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Job
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Job;

use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Entity\EntityTrait;
use AchttienVijftien\Plugin\StaticXMLSitemap\Sitemap\SitemapItemInterface;
use AchttienVijftien\Plugin\StaticXMLSitemap\Util\PropertyAccessor;

/**
 * Class Job
 */
class Job implements EntityInterface {

	use EntityTrait;

	public const ADD_ITEM             = 'add_item';
	public const REMOVE_ITEM          = 'remove_item';
	public const REINDEX_ITEM         = 'reindex_item';
	public const REINDEX_SITEMAP      = 'reindex_sitemap';
	public const UPDATE_LAST_MODIFIED = 'update_last_modified';

	public ?int $sitemap_id;
	public ?int $sitemap_item_id;
	public ?int $object_id;
	public ?string $action;
	public ?string $scheduled_at;
	public ?string $claim_id;
	public ?string $claimed_at;

	/**
	 * Job constructor.
	 *
	 * @param array|object{id: int, sitemap_id: int, sitemap_item_id: int, object_id: int|null, action: string, scheduled_at: string, claim_id: string|null, claimed_at: string|null} $data
	 */
	public function __construct( $data ) {
		$data = PropertyAccessor::create( $data );

		$this->id              = null !== $data->id ? (int) $data->id : null;
		$this->sitemap_id      = (int) $data->sitemap_id;
		$this->sitemap_item_id = null !== $data->sitemap_item_id ? (int) $data->sitemap_item_id : null;
		$this->object_id       = null !== $data->object_id ? (int) $data->object_id : null;
		$this->action          = $data->action;
		$this->scheduled_at    = $data->scheduled_at;
		$this->claim_id        = $data->claim_id;
		$this->claimed_at      = $data->claimed_at;
	}

	public static function add_item( int $sitemap_id, int $object_id ): Job {
		return new self(
			[
				'sitemap_id'   => $sitemap_id,
				'object_id'    => $object_id,
				'action'       => self::ADD_ITEM,
				'scheduled_at' => current_time( 'mysql', true ),
			]
		);
	}

	public static function remove_item( SitemapItemInterface $item ): Job {
		return new self(
			[
				'sitemap_id'      => $item->get_sitemap_id(),
				'sitemap_item_id' => $item->get_id(),
				'object_id'       => $item->get_object_id(),
				'action'          => self::REMOVE_ITEM,
				'scheduled_at'    => current_time( 'mysql', true ),
			]
		);
	}

	public static function reindex_item( SitemapItemInterface $item ): Job {
		return new self(
			[
				'sitemap_id'      => $item->get_sitemap_id(),
				'sitemap_item_id' => $item->get_id(),
				'object_id'       => $item->get_object_id(),
				'action'          => self::REINDEX_ITEM,
				'scheduled_at'    => current_time( 'mysql', true ),
			]
		);
	}

	public static function update_last_modified( SitemapItemInterface $item ): Job {
		return new self(
			[
				'sitemap_id'      => $item->get_sitemap_id(),
				'sitemap_item_id' => $item->get_id(),
				'object_id'       => $item->get_object_id(),
				'action'          => self::UPDATE_LAST_MODIFIED,
				'scheduled_at'    => current_time( 'mysql', true ),
			]
		);
	}

}
