<?php
/**
 * The combined filter.
 *
 * All chosen filters apply together in one query — never one at a time. This is
 * the part of the current site the client is most attached to, so it has to
 * behave at least as well as what he already has.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Query {

	/** Query var => taxonomy. */
	public function filters() {
		return array(
			'listing_category' => 'listing_category',
			'province'         => 'province',
			'region'           => 'region',
			'size_band'        => 'size_band',
			'price_band'       => 'price_band',
			'status'           => 'status',
			'species'          => 'species',
		);
	}

	public function __construct() {
		add_action( 'pre_get_posts', array( $this, 'apply' ) );
	}

	public function apply( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_listing_archive = $query->is_post_type_archive( Acreage_Core_Post_Types::POST_TYPE )
			|| $query->is_tax( array_values( $this->filters() ) );

		// A plain search page can also be pointed at farms via ?post_type=listing.
		$searching_listings = $query->is_search()
			&& Acreage_Core_Post_Types::POST_TYPE === $query->get( 'post_type' );

		if ( ! $is_listing_archive && ! $searching_listings ) {
			return;
		}

		$tax_query = array();

		foreach ( $this->filters() as $param => $taxonomy ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filtering.
			if ( empty( $_GET[ $param ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw   = wp_unslash( $_GET[ $param ] );
			$terms = is_array( $raw ) ? $raw : explode( ',', $raw );
			$terms = array_filter( array_map( 'sanitize_title', $terms ) );

			if ( ! $terms ) {
				continue;
			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
			);
		}

		if ( count( $tax_query ) > 1 ) {
			// AND is the whole point: region *and* size *and* price together.
			$tax_query['relation'] = 'AND';
		}

		if ( $tax_query ) {
			$existing = $query->get( 'tax_query' );
			$query->set( 'tax_query', $existing ? array_merge( (array) $existing, $tax_query ) : $tax_query );
		}

		$this->apply_sort( $query );
	}

	/** Sorting, matching what the current archive already offers. */
	private function apply_sort( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';

		switch ( $sort ) {
			case 'price-low':
				$query->set( 'meta_key', 'acreage_price' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'ASC' );
				break;

			case 'price-high':
				$query->set( 'meta_key', 'acreage_price' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'DESC' );
				break;

			case 'oldest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'ASC' );
				break;

			case 'latest':
			default:
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
				break;
		}
	}

	/* ------------------------------------------------------------- helpers */

	/**
	 * Price with the VAT line the brief asks never to be forgotten.
	 * Rendered from data, so it cannot be left off a listing by accident.
	 */
	public static function price_html( $post_id ) {
		$price = (float) get_post_meta( $post_id, 'acreage_price', true );

		if ( $price <= 0 ) {
			return '<span class="acreage-price acreage-price--poa">' . esc_html__( 'Price on application', 'acreage' ) . '</span>';
		}

		return sprintf(
			'<span class="acreage-price">R%1$s</span> <span class="acreage-price__vat">%2$s</span>',
			esc_html( number_format_i18n( $price ) ),
			esc_html__( 'Excludes VAT if applicable', 'acreage' )
		);
	}

	/** Ordered gallery attachment IDs for a farm. */
	public static function gallery_ids( $post_id ) {
		$raw = (string) get_post_meta( $post_id, 'acreage_gallery', true );

		return array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	}
}
