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

		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'pre_get_posts', [ $this, 'xsl_request' ], 1 );
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

		if ( ! $wpseo_sitemaps instanceof \WPSEO_Sitemaps || ! method_exists( $wpseo_sitemaps, 'redirect' ) ) {
			return;
		}

		if ( $type !== $this->get_sitemap_type() ) {
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

	/**
	 * @param array|mixed $query_vars
	 *
	 * @return array|mixed
	 */
	public function add_query_vars( $query_vars ) {
		if ( ! is_array( $query_vars ) ) {
			return $query_vars;
		}

		if ( ! in_array( 'yoast-sitemap-xsl', $query_vars, true ) ) {
			$query_vars[] = 'yoast-sitemap-xsl';
		}

		return $query_vars;
	}

	/**
	 * Handle requests for News sitemap XSL files.
	 *
	 * @param \WP_Query|mixed $query Main query instance.
	 *
	 * @return void
	 */
	public function xsl_request( $query ) {
		global $wpseo_sitemaps;

		if ( ! $wpseo_sitemaps instanceof \WPSEO_Sitemaps || ! method_exists( $wpseo_sitemaps, 'xsl_output' ) ) {
			return;
		}

		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$yoast_sitemap_xsl = get_query_var( 'yoast-sitemap-xsl' );

		if ( $yoast_sitemap_xsl !== $this->get_sitemap_type() ) {
			return;
		}

		$wpseo_sitemaps->xsl_output( $yoast_sitemap_xsl );
		exit();
	}

}
