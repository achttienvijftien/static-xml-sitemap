<?php
/**
 * YoastNewsSeo
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\YoastNewsSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\YoastNewsSeo;

/**
 * Class YoastNewsSeo
 */
class YoastNewsSeo {

	public function is_activated(): bool {
		return defined( 'WPSEO_NEWS_VERSION' ) && class_exists( 'WPSEO_News_Sitemap' );
	}

	public function add_hooks(): void {
		add_filter( 'static_sitemap_index_content', [ $this, 'index_content' ] );
		add_action( 'static_sitemap_request', [ $this, 'request' ], 10, 3 );
	}

	/**
	 * @param mixed|string $content
	 *
	 * @return mixed|string
	 */
	public function index_content( $content ) {
		// Strictly speaking this is a WPSEO filter, but the WPSEO_News_Sitemap instance is
		// initialized in WPSEO_News without storing a reference to it, so this is the cleanest
		// way to get to the sitemap index entry XML for the news sitemap.
		return apply_filters( 'wpseo_sitemap_index', $content );
	}

	/**
	 * @param mixed|\WP_Query $query
	 * @param string          $type
	 * @param int             $paged
	 *
	 * @return void
	 */
	public function request( $query, string $type, int $paged ): void {
		global $wpseo_sitemaps;

		if ( ! $query instanceof \WP_Query
			|| ! $wpseo_sitemaps instanceof \WPSEO_Sitemaps
			|| $type !== $this->get_sitemap_type()
		) {
			return;
		}

		set_query_var( 'sitemap', $type );

		if ( $paged > 1 ) {
			set_query_var( 'sitemap_n', $paged );
		}

		$wpseo_sitemaps->redirect( $query );
	}

	private function get_sitemap_type(): string {
		if ( ! method_exists( \WPSEO_News_Sitemap::class, 'get_sitemap_name' ) ) {
			return 'news';
		}

		return \WPSEO_News_Sitemap::get_sitemap_name( false );
	}

}
