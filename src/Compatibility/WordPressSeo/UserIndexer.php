<?php
/**
 * UserIndexer
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Compatibility\WordPressSeo;

/**
 * Class UserIndexer
 */
class UserIndexer {

	public function add_hooks(): void {
		add_action( 'static_sitemap_index_authors', [ $this, 'index_authors' ] );
	}

	public function index_authors(): void {
		$user_criteria = [
			'capability' => [ 'edit_posts' ],
			'meta_query' => [
				[
					'key'     => '_yoast_wpseo_profile_updated',
					'compare' => 'NOT EXISTS',
				],
			],
		];

		$users = get_users( $user_criteria );

		$time = time();

		foreach ( $users as $user ) {
			update_user_meta( $user->ID, '_yoast_wpseo_profile_updated', $time );
		}
	}

}
