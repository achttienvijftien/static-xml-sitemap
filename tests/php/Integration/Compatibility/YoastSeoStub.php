<?php
/**
 * YoastSeoStub
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility {

	/**
	 * Class YoastSeoStub
	 */
	class YoastSeoStub {

		/**
		 * @var string[]
		 */
		public static array $accessible_post_types = [ 'post', 'page', 'attachment' ];

		/**
		 * @var string[]
		 */
		public static array $indexable_post_types = [ 'post', 'page', 'attachment' ];

		/**
		 * @var string[]
		 */
		public static array $author_archive_post_types = [ 'post' ];

		public YoastSeoHelpersStub $helpers;

		public function __construct() {
			$this->helpers = new YoastSeoHelpersStub();
		}

		public static function reset(): void {
			self::$accessible_post_types     = [ 'post', 'page', 'attachment' ];
			self::$indexable_post_types      = [ 'post', 'page', 'attachment' ];
			self::$author_archive_post_types = [ 'post' ];
		}
	}

	/**
	 * Class YoastSeoHelpersStub
	 */
	class YoastSeoHelpersStub {

		public YoastSeoPostTypeHelperStub $post_type;
		public YoastSeoMetaHelperStub $meta;
		public YoastSeoAuthorArchiveHelperStub $author_archive;

		public function __construct() {
			$this->post_type      = new YoastSeoPostTypeHelperStub();
			$this->meta           = new YoastSeoMetaHelperStub();
			$this->author_archive = new YoastSeoAuthorArchiveHelperStub();
		}
	}

	/**
	 * Class YoastSeoPostTypeHelperStub
	 */
	class YoastSeoPostTypeHelperStub {

		/**
		 * @return string[]
		 */
		public function get_accessible_post_types(): array {
			return YoastSeoStub::$accessible_post_types;
		}

		public function is_indexable( string $post_type ): bool {
			return in_array( $post_type, YoastSeoStub::$indexable_post_types, true );
		}
	}

	/**
	 * Class YoastSeoMetaHelperStub
	 */
	class YoastSeoMetaHelperStub {

		public function get_value( string $key, int $post_id = 0 ): string {
			if ( 'canonical' !== $key ) {
				return '';
			}

			return (string) get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
		}
	}

	/**
	 * Class YoastSeoAuthorArchiveHelperStub
	 */
	class YoastSeoAuthorArchiveHelperStub {

		/**
		 * @return string[]
		 */
		public function get_author_archive_post_types(): array {
			return YoastSeoStub::$author_archive_post_types;
		}
	}
}

namespace {

	if ( ! function_exists( 'YoastSEO' ) ) {
		/**
		 * Minimal stub of the Yoast SEO surface used by the compatibility layer.
		 *
		 * @return \AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility\YoastSeoStub
		 */
		function YoastSEO() {
			return new \AchttienVijftien\Plugin\StaticXMLSitemap\Tests\Integration\Compatibility\YoastSeoStub();
		}
	}
}
