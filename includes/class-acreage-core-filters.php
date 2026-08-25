<?php
/**
 * What the visitor is filtering by — worked out once, understood everywhere.
 *
 * WHY THIS IS ITS OWN CLASS
 *
 * Three things need the same answer to "which farms is this page showing?":
 * the main query, the filter panel that draws the checkboxes and the chips, and
 * the AJAX endpoint behind the live filter. Each of them used to carry its own
 * copy of the seven axes and its own idea of how a value is read from the URL,
 * which is exactly how a Species filter ends up honoured by the query and
 * invisible in the panel. One list, one reader, one URL builder — here.
 *
 * THE CHIP MARKUP LIVES HERE TOO
 *
 * The chips have to come back from AJAX byte-identical to the ones PHP printed,
 * for the same reason the cards do: two copies of the markup drift. So the
 * widget and the endpoint both call chips_html() rather than each writing it.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Filters {

	/**
	 * Most slugs any one axis will accept in a single request.
	 *
	 * The values themselves are harmless — an unknown slug simply matches
	 * nothing — but a URL carrying two thousand of them becomes a two thousand
	 * clause IN(), and that is a database stall a visitor can trigger by hand.
	 */
	const MAX_TERMS = 30;

	/**
	 * The axes a farm can be filtered on, in the order the panel shows them.
	 *
	 * @return array taxonomy => label.
	 */
	public static function axes() {
		return array(
			'listing_category' => __( 'Kind of farm', 'acreage' ),
			'province'         => __( 'Province', 'acreage' ),
			'region'           => __( 'Region', 'acreage' ),
			'size_band'        => __( 'Size', 'acreage' ),
			'price_band'       => __( 'Price', 'acreage' ),
			'status'           => __( 'Status', 'acreage' ),
			'species'          => __( 'Species', 'acreage' ),
		);
	}

	/** Just the taxonomy names. */
	public static function taxonomies() {
		return array_keys( self::axes() );
	}

	/** The sorts the archive offers, and the only values it will accept. */
	public static function sorts() {
		return array_keys( self::sort_labels() );
	}

	/**
	 * The sorts as a visitor reads them, in the comp's order.
	 *
	 * Newest first is deliberately first and is also the default: a farm agent's
	 * archive is a feed, and the question a returning visitor is asking is
	 * "what's new since I last looked".
	 *
	 * @return array key => label.
	 */
	public static function sort_labels() {
		return array(
			'latest'     => __( 'Newest first', 'acreage' ),
			'oldest'     => __( 'Oldest first', 'acreage' ),
			'price-low'  => __( 'Price: low to high', 'acreage' ),
			'price-high' => __( 'Price: high to low', 'acreage' ),
		);
	}

	/* ---------------------------------------------------------------- read */

	/**
	 * Filter state out of a request array.
	 *
	 * Accepts both shapes the site produces: province[]=x&province[]=y from the
	 * checkbox panel, and province=x,y from a shared link or the AJAX request.
	 *
	 * @param array $source $_GET or $_POST.
	 * @return array taxonomy => slugs.
	 */
	public static function read( array $source ) {
		$state = array();

		foreach ( self::taxonomies() as $taxonomy ) {
			if ( empty( $source[ $taxonomy ] ) ) {
				continue;
			}

			$raw   = wp_unslash( $source[ $taxonomy ] );
			$slugs = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
			$slugs = array_values( array_unique( array_filter( array_map( 'sanitize_title', $slugs ) ) ) );

			if ( $slugs ) {
				$state[ $taxonomy ] = array_slice( $slugs, 0, self::MAX_TERMS );
			}
		}

		return $state;
	}

	/**
	 * The filter state of the page being viewed.
	 *
	 * READS THE TERM ARCHIVE AS WELL AS THE QUERY STRING
	 *
	 * A farm can be filtered three different ways on this site and only one of
	 * them is a query argument:
	 *
	 *   /farms/?province=limpopo        the filter panel itself
	 *   /province/limpopo/              a province tile, a category card, or a
	 *                                   breadcrumb link — all get_term_link()
	 *   /?s=waterberg&post_type=listing a keyword search
	 *
	 * Asking the query, not just the URL, is what makes the three routes behave
	 * the same. A visitor who arrived from a province tile sees that province
	 * ticked, and can untick it.
	 *
	 * @return array taxonomy => slugs.
	 */
	public static function from_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only filtering.
		$state = self::read( $_GET );

		foreach ( self::taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_tax( $taxonomy ) ) {
				continue;
			}

			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$slugs              = isset( $state[ $taxonomy ] ) ? $state[ $taxonomy ] : array();
				$slugs[]            = $term->slug;
				$state[ $taxonomy ] = array_values( array_unique( $slugs ) );
			}
		}

		return $state;
	}

	/** The keyword being searched for, if any. */
	public static function search( array $source ) {
		return isset( $source['s'] ) ? sanitize_text_field( wp_unslash( $source['s'] ) ) : '';
	}

	/** The chosen sort, or '' for the default. Anything else is discarded. */
	public static function sort( array $source ) {
		$sort = isset( $source['sort'] ) ? sanitize_key( wp_unslash( $source['sort'] ) ) : '';

		return in_array( $sort, self::sorts(), true ) ? $sort : '';
	}

	/* ----------------------------------------------------------------- url */

	/** Where filter forms and browse links point. */
	public static function archive_url() {
		$url = get_post_type_archive_link( Acreage_Core_Post_Types::POST_TYPE );

		return $url ? $url : home_url( '/' );
	}

	/**
	 * The canonical URL for a filter state.
	 *
	 * Always rebuilt from the state rather than by editing the current URL, so a
	 * pretty term archive and a query string collapse to the same shape and
	 * removing one filter can never strand the others.
	 *
	 * @param array  $state  taxonomy => slugs.
	 * @param string $search Keyword.
	 * @param string $sort   Sort key.
	 * @return string
	 */
	public static function url( array $state, $search = '', $sort = '' ) {
		$url = self::archive_url();

		foreach ( $state as $taxonomy => $slugs ) {
			if ( $slugs ) {
				$url = add_query_arg( $taxonomy, implode( ',', $slugs ), $url );
			}
		}

		if ( '' !== $search ) {
			$url = add_query_arg( 's', rawurlencode( $search ), $url );
		}

		if ( '' !== $sort ) {
			$url = add_query_arg( 'sort', $sort, $url );
		}

		return $url;
	}

	/* --------------------------------------------------------------- chips */

	/**
	 * Every filter in force, as removable chips.
	 *
	 * Built from ALL seven axes, not only the ones a panel is set to display.
	 * Hiding the Species list in the widget settings does not stop a
	 * ?species=sable arriving from somewhere else, and a filter the visitor can
	 * neither see nor switch off is the worst of both.
	 *
	 * @param array  $state  taxonomy => slugs.
	 * @param string $search Keyword.
	 * @param string $sort   Sort key, carried through so removing a filter does
	 *                       not silently reset the ordering.
	 * @return array[] label, and the URL that drops just this one.
	 */
	public static function chips( array $state, $search = '', $sort = '' ) {
		$chips = array();

		foreach ( $state as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );

				$without              = $state;
				$without[ $taxonomy ] = array_values( array_diff( $slugs, array( $slug ) ) );

				$chips[] = array(
					'label' => $term ? $term->name : $slug,
					'url'   => self::url( $without, $search, $sort ),
				);
			}
		}

		if ( '' !== $search ) {
			$chips[] = array(
				/* translators: %s: the keyword searched for. */
				'label' => sprintf( __( 'Search: %s', 'acreage' ), $search ),
				'url'   => self::url( $state, '', $sort ),
			);
		}

		return $chips;
	}

	/**
	 * The chip bar, or an empty string when nothing is filtered.
	 *
	 * Returned rather than printed because the AJAX endpoint sends it as JSON.
	 *
	 * @param array  $state  taxonomy => slugs.
	 * @param string $search Keyword.
	 * @param string $sort   Sort key.
	 * @return string
	 */
	public static function chips_html( array $state, $search = '', $sort = '' ) {
		$chips = self::chips( $state, $search, $sort );

		if ( ! $chips ) {
			return '';
		}

		ob_start();
		?>
		<div class="acreage-w-filters__active">
			<span class="acreage-w-filters__activelabel">
				<?php esc_html_e( 'Filtering by', 'acreage' ); ?>
			</span>

			<ul class="acreage-w-filters__chips">
				<?php foreach ( $chips as $chip ) : ?>
					<li>
						<a class="acreage-w-filters__chip" href="<?php echo esc_url( $chip['url'] ); ?>">
							<span><?php echo esc_html( $chip['label'] ); ?></span>
							<span class="acreage-w-filters__chipx" aria-hidden="true">&times;</span>
							<span class="screen-reader-text">
								<?php
								printf(
									/* translators: %s: the filter being removed. */
									esc_html__( 'Remove filter: %s', 'acreage' ),
									esc_html( $chip['label'] )
								);
								?>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php
			/*
			 * Clear all clears the FILTERS, and the sort is not one of them.
			 * A visitor who asked for the cheapest first and then widened the
			 * search still wants the cheapest first; dropping the order here
			 * while every individual chip preserves it was the panel
			 * contradicting itself.
			 */
			?>
			<a class="acreage-w-filters__clearall" href="<?php echo esc_url( self::url( array(), '', $sort ) ); ?>">
				<?php esc_html_e( 'Clear all', 'acreage' ); ?>
			</a>
		</div>
		<?php

		return trim( ob_get_clean() );
	}

	/**
	 * "12 farms" — the line the live filter updates so a visitor who changes a
	 * checkbox is told what happened, rather than having to count cards.
	 *
	 * @param int $found Number of matching farms.
	 * @return string
	 */
	public static function count_text( $found ) {
		return sprintf(
			/* translators: %s: number of farms matching the current filters. */
			_n( '%s farm', '%s farms', (int) $found, 'acreage' ),
			number_format_i18n( (int) $found )
		);
	}

	/**
	 * The line above the results: how much of what matched is on screen.
	 *
	 * WHY IT IS THREE SENTENCES AND NOT A NUMBER
	 *
	 * The comp's version reads "3 of 12 matching farms · 63 live listings", and
	 * each half earns its place. The first tells a visitor looking at ten cards
	 * that there are two more below, which is the difference between paging and
	 * assuming they have seen everything. The second tells a visitor who has
	 * filtered down to two farms how much they have filtered OUT, which is the
	 * difference between "there is nothing here" and "I have asked for too
	 * little".
	 *
	 * Both halves drop out when they would only repeat themselves: nothing is
	 * hidden below the fold, or nothing is filtered.
	 *
	 * @param int $shown   Farms on screen so far.
	 * @param int $matched Farms matching the current filters.
	 * @param int $total   Farms on the site.
	 * @return string
	 */
	public static function result_text( $shown, $matched, $total ) {
		$shown   = (int) $shown;
		$matched = (int) $matched;
		$total   = (int) $total;

		if ( $matched < 1 ) {
			$line = __( 'No farms match', 'acreage' );
		} elseif ( $shown < $matched ) {
			$line = sprintf(
				/* translators: 1: farms on screen, 2: farms matching the filters. */
				_n( '%1$s of %2$s matching farm', '%1$s of %2$s matching farms', $matched, 'acreage' ),
				number_format_i18n( $shown ),
				number_format_i18n( $matched )
			);
		} else {
			$line = self::count_text( $matched );
		}

		if ( $matched < $total ) {
			$line .= ' · ' . sprintf(
				/* translators: %s: total number of farms on the site. */
				_n( '%s live listing', '%s live listings', $total, 'acreage' ),
				number_format_i18n( $total )
			);
		}

		return $line;
	}

	/** Every farm on the site, however the visitor has filtered. */
	public static function total() {
		$counts = wp_count_posts( Acreage_Core_Post_Types::POST_TYPE );

		return $counts ? (int) $counts->publish : 0;
	}

	/**
	 * The current filter state as hidden inputs.
	 *
	 * Lets a form that is not the filter panel — the sort bar — submit without
	 * throwing away what the visitor has already narrowed down to.
	 *
	 * @param array  $state  taxonomy => slugs.
	 * @param string $search Keyword.
	 * @return string
	 */
	public static function hidden_fields( array $state, $search = '' ) {
		$html = sprintf(
			'<input type="hidden" name="post_type" value="%s">',
			esc_attr( Acreage_Core_Post_Types::POST_TYPE )
		);

		foreach ( $state as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$html .= sprintf(
					'<input type="hidden" name="%s[]" value="%s">',
					esc_attr( $taxonomy ),
					esc_attr( $slug )
				);
			}
		}

		if ( '' !== $search ) {
			$html .= sprintf( '<input type="hidden" name="s" value="%s">', esc_attr( $search ) );
		}

		return $html;
	}
}
