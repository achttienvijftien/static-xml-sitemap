<?php
/**
 * Invalidations
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Watcher;

interface Invalidations {

	public const IS_INDEXABLE       = 1 << 0;
	public const ITEM_INDEX         = 1 << 1;
	public const ITEM_URL           = 1 << 2;
	public const OBJECT_EXISTS      = 1 << 3;
	public const ITEM_LAST_MODIFIED = 1 << 4;

}
