<?php
/**
 * AbstractWatcher
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Watcher
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Watcher;

use AchttienVijftien\Plugin\StaticXMLSitemap\Provider\ProviderInterface;

/**
 * Class AbstractWatcher
 */
abstract class AbstractWatcher implements WatcherInterface {

	protected ?ProviderInterface $provider = null;
	protected array $events = [];

	public function add_events( int $object_id, int $events ): void {
		$this->events[ $object_id ] ??= 0;
		$this->events[ $object_id ] |= $events;
	}

	public function process_events(): void {
		foreach ( $this->events as $post_id => $events ) {
			$this->provider->process_watches( $post_id, $events );
		}
	}

	public function add_hooks(): void {
		add_action( 'shutdown', [ $this, 'process_events' ] );

		$this->add_watch_hooks();
	}

	abstract protected function add_watch_hooks(): void;

	public function set_provider( ProviderInterface $provider ): void {
		$this->provider = $provider;
	}

}
