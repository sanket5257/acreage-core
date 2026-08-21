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
			'show_province' => ! empty( $payload['show_province'] ) ? 1 : 0,
			'show_excerpt'  => ! empty( $payload['show_excerpt'] ) ? 1 : 0,
			'show_extent'   => ! empty( $payload['show_extent'] ) ? 1 : 0,
			'show_price'    => ! empty( $payload['show_price'] ) ? 1 : 0,
			'show_vat'      => ! empty( $payload['show_vat'] ) ? 1 : 0,
			'link_text'     => isset( $payload['link_text'] ) ? sanitize_text_field( $payload['link_text'] ) : '',
		);

		ksort( $clean );

		return $clean;
	}

	/* --------------------------------------------------------------- query */

	/**
	 * Build the listing query for a grid.
	 *
	 * @param array $args     Normalised payload.
	 * @param string $category Category slug, or '' for all.
	 * @param int    $page     1-based page number.
	 * @return WP_Query
	 */
	public static function query( array $args, $category = '', $page = 1 ) {
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
		if ( count( $tax ) > 1 ) {
			$tax['relation'] = 'AND';
		}
		if ( $tax ) {
			$query['tax_query'] = $tax;
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
	 * @param array $a Normalised payload.
	 */
	public static function card( array $a ) {
		$id = get_the_ID();
		?>
		<article class="acreage-w-card">
			<?php if ( $a['show_image'] ) : ?>
				<a class="acreage-w-card__media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
					<?php else : ?>
						<span class="acreage-w-card__placeholder" aria-hidden="true"></span>
					<?php endif; ?>

					<?php
					$status = $a['show_status'] ? self::first_term( $id, 'status' ) : '';
					if ( $status ) :
						?>
						<span class="acreage-w-badge acreage-w-badge--<?php echo esc_attr( sanitize_title( $status ) ); ?>">
							<?php echo esc_html( $status ); ?>
						</span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<div class="acreage-w-card__body">
				<?php
				$province = $a['show_province'] ? self::first_term( $id, 'province' ) : '';
				if ( $province ) :
					?>
					<p class="acreage-w-card__where"><?php echo esc_html( $province ); ?></p>
				<?php endif; ?>

				<h3 class="acreage-w-card__title">
					<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>

				<?php if ( $a['show_excerpt'] ) : ?>
					<p class="acreage-w-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
				<?php endif; ?>

				<?php
				$extent = $a['show_extent'] ? self::extent( $id ) : '';
				$price  = $a['show_price'] ? self::price( $id ) : '';
				if ( $extent || $price ) :
					?>
					<p class="acreage-w-card__facts">
						<?php if ( $extent ) : ?>
							<span class="acreage-w-card__extent"><?php echo esc_html( $extent ); ?></span>
						<?php endif; ?>
						<?php if ( $extent && $price ) : ?>
							<span class="acreage-w-card__dot" aria-hidden="true">·</span>
						<?php endif; ?>
						<?php if ( $price ) : ?>
							<span class="acreage-w-card__price"><?php echo esc_html( $price ); ?></span>
						<?php endif; ?>
					</p>

					<?php if ( $a['show_vat'] && (float) get_post_meta( $id, 'acreage_price', true ) > 0 ) : ?>
						<p class="acreage-w-card__vat"><?php esc_html_e( 'Excludes VAT if applicable', 'acreage' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $a['link_text'] ) : ?>
					<a class="acreage-w-card__more" href="<?php the_permalink(); ?>">
						<?php echo esc_html( $a['link_text'] ); ?>
						<span class="screen-reader-text"><?php echo esc_html( ': ' . get_the_title() ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}

	/* ---------------------------------------------------------------- ajax */

	/**
	 * Return a page of cards for the tabs and the Load more button.
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

		$query = self::query( $args, $category, $page );

		ob_start();

		while ( $query->have_posts() ) {
			$query->the_post();
			self::card( $args );
		}

		wp_reset_postdata();

		wp_send_json_success(
			array(
				'html'  => ob_get_clean(),
				'more'  => $page < (int) $query->max_num_pages,
				'total' => (int) $query->found_posts,
			)
		);
	}

	/* -------------------------------------------------------------- shared */

	/** First term name of a taxonomy on a post. */
	public static function first_term( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
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
		return apply_filters( 'acreage_price', 'R' . number_format_i18n( $value ), $value );
	}

	/** Extent, or an empty string when it has not been recorded. */
	public static function extent( $post_id ) {
		$value = (float) get_post_meta( $post_id, 'acreage_hectares', true );

		if ( $value <= 0 ) {
			return '';
		}

		/* translators: %s: number of hectares. */
		return apply_filters( 'acreage_extent', sprintf( __( '%s ha', 'acreage' ), number_format_i18n( $value ) ), $value );
	}
}
