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

	/**
	 * Query var => taxonomy.
	 *
	 * The list itself lives in Acreage_Core_Filters, which the panel and the
	 * live-filter endpoint read too. Three copies of seven taxonomy names is
	 * how an axis ends up honoured here and missing from the checkboxes.
	 */
	public function filters() {
		return array_combine(
			Acreage_Core_Filters::taxonomies(),
			Acreage_Core_Filters::taxonomies()
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filtering.
		foreach ( Acreage_Core_Filters::read( $_GET ) as $taxonomy => $terms ) {
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
			esc_html( Acreage_Core_Grid::number( $price ) ),
			esc_html__( 'Excludes VAT if applicable', 'acreage' )
		);
	}

	/**
	 * The map embed for a farm, or '' when no location has been set.
	 *
	 * NO API KEY, BY DEFAULT
	 *
	 * Google's documented Embed API needs a key, a Cloud project and a billing
	 * account attached to it. That is a reasonable ask of an agency and an
	 * unreasonable one of a farm agent who wants a map on a listing, and it is a
	 * support burden for anyone who buys this theme. The keyless embed below
	 * needs none of it and has been Google's public embed URL for well over a
	 * decade.
	 *
	 * A site that would rather use the official API — for styling, or because
	 * it already has a key — sets one through the filter and gets it.
	 *
	 * @param int $post_id Farm.
	 * @return string Embed URL, or ''.
	 */
	public static function map_url( $post_id ) {
		$place = trim( (string) get_post_meta( $post_id, 'acreage_map', true ) );

		if ( '' === $place ) {
			return '';
		}

		$zoom = (int) get_post_meta( $post_id, 'acreage_map_zoom', true );
		$zoom = $zoom ? $zoom : Acreage_Core_Fields::MAP_ZOOM_DEFAULT;
		$zoom = max( Acreage_Core_Fields::MAP_ZOOM_MIN, min( Acreage_Core_Fields::MAP_ZOOM_MAX, $zoom ) );

		/**
		 * Filter the Google Maps Embed API key.
		 *
		 * Empty — the default — uses the keyless embed instead.
		 *
		 * @param string $key     API key.
		 * @param int    $post_id Farm being shown.
		 */
		$key = (string) apply_filters( 'acreage_map_api_key', '', $post_id );

		if ( '' !== $key ) {
			$url = add_query_arg(
				array(
					'key'  => rawurlencode( $key ),
					'q'    => rawurlencode( $place ),
					'zoom' => $zoom,
				),
				'https://www.google.com/maps/embed/v1/place'
			);
		} else {
			$url = add_query_arg(
				array(
					'q'      => rawurlencode( $place ),
					'z'      => $zoom,
					'output' => 'embed',
					'hl'     => rawurlencode( substr( get_locale(), 0, 2 ) ),
				),
				'https://maps.google.com/maps'
			);
		}

		/**
		 * Filter the finished map embed URL.
		 *
		 * The hook for swapping Google out entirely — OpenStreetMap, Mapbox, a
		 * static image — without touching the widget that prints it.
		 *
		 * @param string $url     Embed URL.
		 * @param int    $post_id Farm being shown.
		 * @param string $place   What the client typed.
		 * @param int    $zoom    Zoom level.
		 */
		return apply_filters( 'acreage_map_url', $url, $post_id, $place, $zoom );
	}

	/** Ordered gallery attachment IDs for a farm. */
	public static function gallery_ids( $post_id ) {
		$raw = (string) get_post_meta( $post_id, 'acreage_gallery', true );

		return array_filter( array_map( 'absint', explode( ',', $raw ) ) );
	}
}
