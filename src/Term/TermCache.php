<?php
/**
 * TermCache
 *
 * @package AchttienVijftien\Plugin\StaticXMLSitemap\Term
 */

namespace AchttienVijftien\Plugin\StaticXMLSitemap\Term;

/**
 * Class TermCache
 */
class TermCache {

	public function clean( string $taxonomy ) {
		wp_cache_delete( "{$taxonomy}_term_count", 'static-sitemap' );
	}

	/**
	 * Returns an array of {term_id: int, count: int} for all terms in $taxonomy, where count is the sum of the object
	 * count of the term itself and that of all its descendants.
	 *
	 * @param string $taxonomy
	 *
	 * @return array
	 */
	public function get_hierarchical_term_count( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return [];
		}

		// Force fetching from external object cache to avoid issues with stale data on long-running processes.
		$force = wp_using_ext_object_cache();
		$count = wp_cache_get( "{$taxonomy}_term_count", 'static-sitemap', $force );

		if ( is_array( $count ) ) {
			return $count;
		}

		$terms = $this->get_terms( $taxonomy );

		foreach ( $terms as $term ) {
			foreach ( $this->get_ancestors( $term, $terms ) as $ancestor ) {
				$terms[ $ancestor->term_id ]->count += $term->count;
			}
		}

		$count = [];

		foreach ( $terms as $term ) {
			$count[] = [
				'term_id' => $term->term_id,
				'count'   => $term->count,
			];
		}

		wp_cache_set( "{$taxonomy}_term_count", $count, 'static-sitemap' );

		return $count;
	}

	/**
	 * Returns the terms.
	 *
	 * @param string $taxonomy
	 *
	 * @return \stdClass[]
	 */
	private function get_terms( string $taxonomy ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT term_id, parent, count FROM %i WHERE taxonomy = %s',
				$wpdb->term_taxonomy,
				$taxonomy
			)
		);

		if ( ! is_array( $results ) ) {
			return [];
		}

		$terms = [];

		foreach ( $results as $term ) {
			$term->term_id = (int) $term->term_id;
			$term->parent  = (int) $term->parent;
			$term->count   = (int) $term->count;

			$terms[ $term->term_id ] = $term;
		}

		return $terms;
	}

	/**
	 * Returns the ancestors.
	 *
	 * @param \stdClass   $term
	 * @param \stdClass[] $terms
	 *
	 * @return \stdClass[]
	 */
	private function get_ancestors( \stdClass $term, array $terms ): array {
		$ancestors = [];

		$parent = $this->get_parent( $term, $terms );
		while ( null !== $parent ) {
			$ancestors[] = $parent;

			$parent = $this->get_parent( $parent, $terms );
		}

		return $ancestors;
	}

	/**
	 * Returns the parent.
	 *
	 * @param \stdClass   $term
	 * @param \stdClass[] $terms
	 *
	 * @return \stdClass|null
	 */
	private function get_parent( \stdClass $term, array $terms ): ?\stdClass {
		if ( 0 === $term->parent || ! isset( $terms[ $term->parent ] ) ) {
			return null;
		}

		return $terms[ $term->parent ];
	}

}
