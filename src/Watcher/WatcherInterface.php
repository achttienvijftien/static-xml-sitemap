<?php
/**
 * WatcherInterface
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Watcher;

use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;

/**
 * Interface WatcherInterface
 */
interface WatcherInterface {

	public function add_events( int $object_id, int $events ): void;

	public function process_events(): void;

	public function add_hooks(): void;

	public function set_provider( ProviderInterface $provider ): void;
}
