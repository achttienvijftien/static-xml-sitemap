<?php
/**
 * ObjectQueryInterface
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Query
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Query;

interface ObjectQueryInterface {

	public function get_query(): string;

	public function set_after( string $field, $value, int $id ): self;

	public function set_fields( array $fields ): self;

	public function set_limit( int $limit ): self;

	public function set_exclude( array $exclude ): self;

	public function set_indexable( bool $indexable ): self;

	public function set_orderby( ?string $orderby ): self;

	public function set_order( string $order ): self;

	public function set_sitemap( int $sitemap_id ): self;

}
