<?php
/**
 * The listing grid engine — query, card markup, and the AJAX endpoint behind
 * the category tabs and the Load more button.
 *
 * WHY THIS IS NOT INSIDE THE ELEMENTOR WIDGET
 *
 * The tabs and Load more fetch more cards over AJAX, and those cards have to
 * come out byte-identical to the ones PHP rendered on first paint — otherwise
 * row three looks subtly different from rows one and two. Two copies of the
 * markup guarantees that drift, so there is one copy, here, and both the widget
 * and the AJAX handler call it.
 *
 * It also keeps the widget file free of Elementor classes, which means the AJAX
 * endpoint keeps working on a site where Elementor has been deactivated.
 *
 * SECURITY NOTE
 *
 * The browser tells us which page and which category it wants, and those are
 * validated normally. It does NOT get to tell us how many posts to return or
 * which display options to use — an unsigned "posts_per_page" is an invitation
 * to ask for fifty thousand rows and stall the database. Those values travel
 * with an HMAC generated from the site's salts, exactly as the enquiry form's
 * recipient does, and a payload that fails the check is discarded in favour of
 * safe defaults.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Grid {

	const NONCE       = 'acreage_grid';
	const SIG_CONTEXT = 'acreage_grid_args';

	/** Nothing may ask for more than this in one request, signed or not. */
	const MAX_PER_PAGE = 24;

	/**
	 * Where the card's Enquire link lands on the listing page.
	 *
	 * The id the enquiry form widget prints. If a listing has no form on it the
	 * link simply opens the listing, which is the right thing to do anyway.
	 */
	const ENQUIRY_ANCHOR = 'acreage-enquire';

	public function __construct() {
		add_action( 'wp_ajax_acreage_grid', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_acreage_grid', array( $this, 'ajax' ) );
	}

	/* ------------------------------------------------------------- signing */

	/**
	 * Sign the parts of a grid request the browser must not be able to invent.
	 *
	 * @param array $payload Display and query settings.
	 * @return string
	 */
	public static function sign( array $payload ) {
		return wp_hash( self::SIG_CONTEXT . '|' . wp_json_encode( self::normalise( $payload ) ) );
	}

	/**
	 * Put a payload into a fixed shape and order so signing is deterministic.
	 *
	 * Without this, two arrays holding identical values but in a different key
	 * order would produce different signatures and every request would fail.
	 *
	 * @param array $payload Raw payload.
	 * @return array
	 */
	public static function normalise( array $payload ) {
		$clean = array(
			'count'         => isset( $payload['count'] ) ? min( self::MAX_PER_PAGE, max( 1, (int) $payload['count'] ) ) : 6,
			'orderby'       => isset( $payload['orderby'] ) ? sanitize_key( $payload['orderby'] ) : 'latest',
			'featured'      => ! empty( $payload['featured'] ) ? 1 : 0,
			'province'      => isset( $payload['province'] ) ? sanitize_title( $payload['province'] ) : '',
			'show_image'    => ! empty( $payload['show_image'] ) ? 1 : 0,
			'show_status'   => ! empty( $payload['show_status'] ) ? 1 : 0,
			'show_category' => ! empty( $payload['show_category'] ) ? 1 : 0,
			'show_region'   => ! empty( $payload['show_region'] ) ? 1 : 0,
			'show_province' => ! empty( $payload['show_province'] ) ? 1 : 0,
			'show_excerpt'  => ! empty( $payload['show_excerpt'] ) ? 1 : 0,
			'show_extent'   => ! empty( $payload['show_extent'] ) ? 1 : 0,
			'show_price'    => ! empty( $payload['show_price'] ) ? 1 : 0,
			'show_vat'      => ! empty( $payload['show_vat'] ) ? 1 : 0,
			'link_text'     => isset( $payload['link_text'] ) ? sanitize_text_field( $payload['link_text'] ) : '',
			'enquire_text'  => isset( $payload['enquire_text'] ) ? sanitize_text_field( $payload['enquire_text'] ) : '',
		);

		ksort( $clean );

		return $clean;
	}

	/* --------------------------------------------------------------- query */

	/**
	 * Build the listing query for a grid.
	 *
	 * @param array  $args     Normalised payload.
	 * @param string $category Category slug, or '' for all.
	 * @param int    $page     1-based page number.
	 * @param array  $filters  Live filter state, taxonomy => slugs.
	 * @param string $search   Keyword, or ''.
	 * @param string $sort     Sort key from the archive, or '' to use $args.
	 * @return WP_Query
	 */
	public static function query( array $args, $category = '', $page = 1, array $filters = array(), $search = '', $sort = '' ) {
		/*
		 * A sort chosen by the visitor beats the one set in the widget. It is
		 * safe to take unsigned because it is matched against a fixed list of
		 * four values before it gets here — unlike posts_per_page, it cannot be
		 * turned into a request for the whole table.
		 */
		if ( '' !== $sort ) {
			$args['orderby'] = $sort;
		}

		$query = array(
			'post_type'           => Acreage_Core_Post_Types::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $args['count'],
			'paged'               => max( 1, (int) $page ),
			'ignore_sticky_posts' => true,
			// The grid never needs term/meta caches for posts it will not print.
			'no_found_rows'       => false,
		);

		$tax = array();

		if ( $category ) {
			$tax[] = array( 'taxonomy' => 'listing_category', 'field' => 'slug', 'terms' => $category );
		}
		if ( ! empty( $args['province'] ) ) {
			$tax[] = array( 'taxonomy' => 'province', 'field' => 'slug', 'terms' => $args['province'] );
		}

		/*
		 * The visitor's own filters, on top of whatever the widget was set to.
		 * Each axis is its own clause so they combine with AND — region *and*
		 * size *and* price, which is the behaviour the client is most attached
		 * to — while several ticks within one axis stay an OR, as a checkbox
		 * list implies.
		 */
		foreach ( $filters as $taxonomy => $slugs ) {
			if ( $slugs && taxonomy_exists( $taxonomy ) ) {
				$tax[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $slugs );
			}
		}

		if ( count( $tax ) > 1 ) {
			$tax['relation'] = 'AND';
		}
		if ( $tax ) {
			$query['tax_query'] = $tax;
		}

		if ( '' !== $search ) {
			$query['s'] = $search;
		}

		if ( ! empty( $args['featured'] ) ) {
			$query['meta_query'] = array(
				array( 'key' => 'acreage_featured', 'value' => '1' ),
			);
		}

		switch ( $args['orderby'] ) {
			case 'oldest':
				$query['orderby'] = 'date';
				$query['order']   = 'ASC';
				break;
			case 'price-high':
				$query['meta_key'] = 'acreage_price';
				$query['orderby']  = 'meta_value_num';
				$query['order']    = 'DESC';
				break;
			case 'price-low':
				$query['meta_key'] = 'acreage_price';
				$query['orderby']  = 'meta_value_num';
				$query['order']    = 'ASC';
				break;
			case 'rand':
				$query['orderby'] = 'rand';
				break;
			default:
				$query['orderby'] = 'date';
				$query['order']   = 'DESC';
		}

		/**
		 * Filter the grid query arguments.
		 *
		 * @param array  $query    WP_Query arguments.
		 * @param array  $args     Normalised grid payload.
		 * @param string $category Category slug.
		 */
		return new WP_Query( apply_filters( 'acreage_grid_query_args', $query, $args, $category ) );
	}

	/* ---------------------------------------------------------------- card */

	/**
	 * One listing card. Assumes the loop is on the post.
	 *
	 * THIS IS THE SIGNED-OFF COMP, ELEMENT FOR ELEMENT
	 *
	 * The order and the wording come from the Loop Item in "Africa Game Farms
	 * Homepage.dc.html", which the archive shares:
	 *
	 *   photograph, with the status badge inset from the top-left corner
	 *   Kind · Region, Province          10px, letterspaced, uppercase
	 *   Name                             Georgia 22px
	 *   R42 500 000                      20px, moss
	 *   Excludes VAT if applicable       11px, stone
	 *   the short summary
	 *   ────────────────────────────
	 *   6 400 ha              View listing   Enquire
	 *
	 * The rule above the last row is part of the card, not a divider between
	 * cards: it separates the farm's own facts from the two things you can do
	 * about it. Every one of those lines is switchable in the widget, and the
	 * card closes up around whatever is turned off rather than leaving a gap.
	 *
	 * @param array $a Normalised payload.
	 */
	public static function card( array $a ) {
		$id     = get_the_ID();
		$status = self::badge( $id );
		$sold   = 'sold' === $status['slug'];

		/*
		 * "Game · Waterberg, Limpopo". The comp puts the kind of farm first and
		 * the place second, joined by a middot, and the two halves are separate
		 * spans so the dot can be hidden from a screen reader.
		 */
		$kind  = ! empty( $a['show_category'] ) ? self::first_term( $id, 'listing_category' ) : '';
		$place = array();

		if ( ! empty( $a['show_region'] ) ) {
			$region = self::first_term( $id, 'region' );

			if ( $region ) {
				$place[] = $region;
			}
		}
		if ( ! empty( $a['show_province'] ) ) {
			$province = self::first_term( $id, 'province' );

			if ( $province ) {
				$place[] = $province;
			}
		}

		$place  = implode( ', ', $place );
		$price  = $a['show_price'] ? self::price( $id ) : '';
		$extent = $a['show_extent'] ? self::extent( $id ) : '';
		?>
		<article class="acreage-w-card<?php echo $sold ? ' acreage-w-card--sold' : ''; ?>">
			<?php if ( $a['show_image'] ) : ?>
				<a class="acreage-w-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
					<?php else : ?>
						<span class="acreage-w-card__placeholder" aria-hidden="true"></span>
					<?php endif; ?>

					<?php if ( $a['show_status'] ) : ?>
						<span class="acreage-w-badge acreage-w-badge--<?php echo esc_attr( $status['slug'] ? $status['slug'] : 'default' ); ?>">
							<?php echo esc_html( $status['label'] ); ?>
						</span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<div class="acreage-w-card__body">
				<?php if ( $kind || $place ) : ?>
					<p class="acreage-w-card__where">
						<?php if ( $kind ) : ?>
							<span><?php echo esc_html( $kind ); ?></span>
						<?php endif; ?>
						<?php if ( $kind && $place ) : ?>
							<span class="acreage-w-card__dot" aria-hidden="true">&middot;</span>
						<?php endif; ?>
						<?php if ( $place ) : ?>
							<span><?php echo esc_html( $place ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<h3 class="acreage-w-card__title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<?php if ( $price ) : ?>
					<p class="acreage-w-card__facts">
						<span class="acreage-w-card__price"><?php echo esc_html( $price ); ?></span>
					</p>

					<?php if ( $a['show_vat'] && (float) get_post_meta( $id, 'acreage_price', true ) > 0 ) : ?>
						<p class="acreage-w-card__vat"><?php esc_html_e( 'Excludes VAT if applicable', 'acreage' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $a['show_excerpt'] ) : ?>
					<p class="acreage-w-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 26 ) ); ?></p>
				<?php endif; ?>

				<?php if ( $extent || $a['link_text'] || $a['enquire_text'] ) : ?>
					<div class="acreage-w-card__foot">
						<span class="acreage-w-card__extent"><?php echo esc_html( $extent ); ?></span>

						<?php if ( $a['link_text'] || $a['enquire_text'] ) : ?>
							<span class="acreage-w-card__links">
								<?php if ( $a['link_text'] ) : ?>
									<a class="acreage-w-card__more" href="<?php the_permalink(); ?>">
										<?php echo esc_html( $a['link_text'] ); ?>
										<span class="screen-reader-text"><?php echo esc_html( ': ' . get_the_title() ); ?></span>
									</a>
								<?php endif; ?>

								<?php if ( $a['enquire_text'] ) : ?>
									<a class="acreage-w-card__enquire" href="<?php echo esc_url( get_permalink() . '#' . self::ENQUIRY_ANCHOR ); ?>">
										<?php echo esc_html( $a['enquire_text'] ); ?>
										<span class="screen-reader-text"><?php echo esc_html( ': ' . get_the_title() ); ?></span>
									</a>
								<?php endif; ?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/**
	 * The status badge for a farm.
	 *
	 * A farm with no status term is not badgeless in the comp — it says "For
	 * sale", which is the whole point of the site. The slug travels with the
	 * label so the stylesheet can colour each status the way the comp does:
	 * moss for a new listing, ochre for Big Five, slate for off-market and a
	 * flat grey for sold.
	 *
	 * @param int $post_id Farm.
	 * @return array label and slug.
	 */
	public static function badge( $post_id ) {
		$terms = get_the_terms( $post_id, 'status' );

		if ( $terms && ! is_wp_error( $terms ) ) {
			return array(
				'label' => $terms[0]->name,
				'slug'  => $terms[0]->slug,
			);
		}

		/**
		 * Filter the wording used when a farm carries no status term.
		 *
		 * @param string $label Default badge text.
		 */
		return array(
			'label' => apply_filters( 'acreage_default_badge', __( 'For sale', 'acreage' ) ),
			'slug'  => '',
		);
	}


	/* ---------------------------------------------------------------- ajax */

	/**
	 * Return a page of cards for the tabs, the Load more button and the live
	 * filter panel.
	 *
	 * WHAT IS SIGNED AND WHAT IS NOT
	 *
	 * The display settings are signed, because "how many posts" and "which
	 * fields" are the site's decision. What the visitor is filtering by is not,
	 * because it is the visitor's decision and there is nothing to protect: a
	 * taxonomy slug either matches farms or it matches none, the axis names come
	 * from a fixed list, and the number of slugs per axis is capped. It is the
	 * same trust boundary the archive URL already lives on.
	 */
	public function ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );

		$raw = isset( $_POST['args'] ) ? json_decode( wp_unslash( $_POST['args'] ), true ) : array();
		$sig = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';

		// Unsigned or tampered settings are discarded, not honoured.
		if ( ! is_array( $raw ) || ! $sig || ! hash_equals( self::sign( $raw ), $sig ) ) {
			wp_send_json_error( array( 'message' => __( 'That request could not be verified.', 'acreage' ) ), 400 );
		}

		$args     = self::normalise( $raw );
		$category = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;

		// A category the site does not have is treated as "all", never as a filter
		// that silently returns nothing.
		if ( $category && ! term_exists( $category, 'listing_category' ) ) {
			$category = '';
		}

		$filters = Acreage_Core_Filters::read( $_POST );
		$search  = Acreage_Core_Filters::search( $_POST );
		$sort    = Acreage_Core_Filters::sort( $_POST );

		$query = self::query( $args, $category, $page, $filters, $search, $sort );

		ob_start();

		while ( $query->have_posts() ) {
			$query->the_post();
			self::card( $args );
		}

		wp_reset_postdata();

		/*
		 * "10 of 12 matching farms" counts what is on screen, and after a Load
		 * more that is every page so far, not just the one being sent. The
		 * server works it out rather than the browser counting cards, so the
		 * sentence stays translatable and stays in one place.
		 */
		$shown = min( (int) $query->found_posts, $page * $args['count'] );

		wp_send_json_success(
			array(
				'html'   => ob_get_clean(),
				'more'   => $page < (int) $query->max_num_pages,
				'total'  => (int) $query->found_posts,
				'count'  => Acreage_Core_Filters::count_text( $query->found_posts ),
				'result' => Acreage_Core_Filters::result_text( $shown, $query->found_posts, Acreage_Core_Filters::total() ),
				'chips'  => Acreage_Core_Filters::chips_html( $filters, $search, $sort ),
				'url'    => Acreage_Core_Filters::url( $filters, $search, $sort ),
			)
		);
	}

	/* -------------------------------------------------------------- shared */

	/** First term name of a taxonomy on a post. */
	public static function first_term( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	}

	/**
	 * A number the way South Africa writes it: R42 500 000, 6 400 ha.
	 *
	 * NOT number_format_i18n(), DELIBERATELY
	 *
	 * That function follows the site's locale, and a WordPress installed in
	 * en_US — which most of them are, this one included — groups with commas.
	 * The comp, the client's existing site and every price he has ever written
	 * use a space. The locale is about the software; this is about the trade.
	 * The filters below are how a site in another market changes it.
	 *
	 * @param float $value Amount.
	 * @return string
	 */
	public static function number( $value ) {
		/**
		 * Filter the thousands separator used on cards and listing pages.
		 *
		 * A non-breaking space would be typographically neater but breaks
		 * copy-and-paste into a spreadsheet, which is what an agent actually
		 * does with a price.
		 *
		 * @param string $separator Thousands separator.
		 */
		$separator = apply_filters( 'acreage_thousands_separator', ' ' );

		return number_format( (float) $value, 0, '.', $separator );
	}

	/** Price formatted the way the trade writes it. */
	public static function price( $post_id ) {
		$value = (float) get_post_meta( $post_id, 'acreage_price', true );

		if ( $value <= 0 ) {
			return __( 'Price on application', 'acreage' );
		}

		/**
		 * Filter the rendered price.
		 *
		 * The currency symbol is South African here because the client sells in
		 * Rand. This filter is how another site changes it without touching code
		 * we ship.
		 *
		 * @param string $formatted Rendered price.
		 * @param float  $value     Raw amount.
		 */
		return apply_filters( 'acreage_price', 'R' . self::number( $value ), $value );
	}

	/** Extent, or an empty string when it has not been recorded. */
	public static function extent( $post_id ) {
		$value = (float) get_post_meta( $post_id, 'acreage_hectares', true );

		if ( $value <= 0 ) {
			return '';
		}

		/* translators: %s: number of hectares. */
		return apply_filters( 'acreage_extent', sprintf( __( '%s ha', 'acreage' ), self::number( $value ) ), $value );
	}
}
