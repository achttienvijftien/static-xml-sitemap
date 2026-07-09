<?php
/**
 * IndexerException
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Indexer
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Indexer;

/**
 * Class IndexerException
 */
class IndexerException extends \Exception {

	public const SITEMAP_UPDATE_ERROR = 1;
	public const ITEM_INSERT_ERROR    = 2;
}
