<?php
/**
 * Farm Details — the parts of a single listing, as separate placeable pieces.
 *
 * Elementor Pro's dynamic tags are what would normally bind a field to a widget.
 * Instead this widget takes one setting — which part of the farm to show — and
 * reads it from the listing currently being viewed. Drop several onto a page and
 * you have built a single-listing layout in the free editor.
 *
 * The four labelled sections, the facts, the species list, the gallery and the
 * video all come from data, so nothing here can drift out of step with what the
 * client typed on the farm's own screen.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Farm_Details extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-farm-details';
	}

	public function get_title() {
		return __( 'Farm Details', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-post-info';
	}

	private function parts() {
		return array(
			'breadcrumb'   => __( 'Breadcrumb and result navigation', 'acreage' ),
			'hero'         => __( 'Hero band (photograph, name, price)', 'acreage' ),
			'facts'        => __( 'Facts (extent, price, province, status)', 'acreage' ),
			'price'        => __( 'Price, with the VAT line', 'acreage' ),
			'description'  => __( 'Description', 'acreage' ),
			'improvements' => __( 'Improvements', 'acreage' ),
			'wildlife'     => __( 'Wildlife & vegetation', 'acreage' ),
			'land_claims'  => __( 'Land claims', 'acreage' ),
			'sections'     => __( 'All four sections at once', 'acreage' ),
			'species'      => __( 'Species list', 'acreage' ),
			'gallery'      => __( 'Photograph gallery', 'acreage' ),
			'video'        => __( 'YouTube video', 'acreage' ),
			'similar'      => __( 'Similar farms', 'acreage' ),
		);
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'What to show', 'acreage' ),
		) );

		$this->add_control( 'part', array(
			'label'   => __( 'Part of the farm', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'sections',
			'options' => $this->parts(),
		) );

		$this->add_control( 'show_headings', array(
			'label'        => __( 'Show the section headings', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'part' => array( 'sections', 'improvements', 'wildlife', 'land_claims' ) ),
		) );

		$this->add_control( 'columns', array(
			'label'     => __( 'Gallery columns', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '3',
			'options'   => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'condition' => array( 'part' => 'gallery' ),
			'selectors' => array(
				'{{WRAPPER}} .acreage-w-gallery' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));',
			),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-detail' => '--acreage-w-accent:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * Which listing are we describing?
	 *
	 * In the editor there is no listing being viewed, so fall back to the most
	 * recent one — otherwise the canvas is blank and the layout impossible to
	 * design against.
	 */
	private function target() {
		if ( is_singular( Acreage_Core_Post_Types::POST_TYPE ) ) {
			return get_queried_object_id();
		}

		$recent = get_posts( array(
			'post_type'      => Acreage_Core_Post_Types::POST_TYPE,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
		) );

		return $recent ? (int) $recent[0] : 0;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active.', 'acreage' ) );
			return;
		}

		$post_id = $this->target();

		if ( ! $post_id ) {
			$this->editor_notice( __( 'Add a farm and this will show its details. Place this widget on the page you assign as the single-farm layout.', 'acreage' ) );
			return;
		}

		if ( ! is_singular( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice(
				sprintf(
					/* translators: %s: farm name used as a stand-in. */
					__( 'Previewing with “%s”. On the live page this shows whichever farm the visitor opened.', 'acreage' ),
					get_the_title( $post_id )
				)
			);
		}

		echo '<div class="acreage-w-detail">';

		switch ( $settings['part'] ) {
			case 'breadcrumb':
				$this->render_breadcrumb( $post_id );
				break;
			case 'hero':
				$this->render_hero( $post_id );
				break;
			case 'similar':
				$this->render_similar( $post_id );
				break;
			case 'facts':
				$this->render_facts( $post_id );
				break;
			case 'price':
				$this->render_price( $post_id );
				break;
			case 'description':
				$this->render_html( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
				break;
			case 'improvements':
			case 'wildlife':
			case 'land_claims':
				$this->render_section( $post_id, $settings['part'], 'yes' === $settings['show_headings'] );
				break;
			case 'sections':
				$this->render_html( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
				foreach ( array( 'improvements', 'wildlife', 'land_claims' ) as $section ) {
					$this->render_section( $post_id, $section, 'yes' === $settings['show_headings'] );
				}
				break;
			case 'species':
				$this->render_species( $post_id );
				break;
			case 'gallery':
				$this->render_gallery( $post_id );
				break;
			case 'video':
				$this->render_video( $post_id );
				break;
		}

		echo '</div>';
	}

	private function render_html( $html ) {
		echo '<div class="acreage-w-prose">' . wp_kses_post( $html ) . '</div>';
	}

	private function section_label( $key ) {
		$labels = array(
			'improvements' => __( 'Improvements', 'acreage' ),
			'wildlife'     => __( 'Wildlife & vegetation', 'acreage' ),
			'land_claims'  => __( 'Land claims', 'acreage' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	private function render_section( $post_id, $key, $with_heading ) {
		$value = get_post_meta( $post_id, 'acreage_' . $key, true );

		// A cattle farm has no wildlife section, and an empty heading with
		// nothing under it looks like a fault rather than an omission.
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			return;
		}

		if ( $with_heading ) {
			printf( '<h2 class="acreage-w-detail__heading">%s</h2>', esc_html( $this->section_label( $key ) ) );
		}

		$this->render_html( wpautop( $value ) );
	}

	private function render_price( $post_id ) {
		// phpcs:ignore WordPress.Security.EscapeOutput -- price_html escapes its own parts.
		echo '<p class="acreage-w-price">' . Acreage_Core_Query::price_html( $post_id ) . '</p>';

		/*
		 * Price per hectare.
		 *
		 * The single most useful derived figure on a land listing — it is how
		 * buyers compare two farms of different sizes, and working it out by hand
		 * is exactly the sort of friction that loses an enquiry. Shown only when
		 * both numbers exist, because a rate derived from a missing one is a lie.
		 */
		$price    = (float) get_post_meta( $post_id, 'acreage_price', true );
		$hectares = (float) get_post_meta( $post_id, 'acreage_hectares', true );

		if ( $price > 0 && $hectares > 0 ) {
			printf(
				'<p class="acreage-w-price__rate">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: formatted price per hectare. */
						__( 'R%s per hectare', 'acreage' ),
						number_format_i18n( round( $price / $hectares ) )
					)
				)
			);
		}
	}

	/**
	 * Breadcrumb plus the result navigation.
	 *
	 * A buyer arriving from a filtered archive needs a way back to that list, and
	 * a way to keep moving through it. Without prev/next they have to reverse out
	 * to the archive after every farm, which is how a browsing session ends early.
	 *
	 * @param int $post_id Listing.
	 */
	private function render_breadcrumb( $post_id ) {
		$archive  = $this->archive_url();
		$category = $this->first_term( $post_id, 'listing_category' );
		$province = $this->first_term( $post_id, 'province' );

		$crumbs = array( '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'acreage' ) . '</a>' );

		if ( $category ) {
			$term = get_the_terms( $post_id, 'listing_category' );
			$crumbs[] = '<a href="' . esc_url( get_term_link( $term[0] ) ) . '">' . esc_html( $category ) . '</a>';
		}

		if ( $province ) {
			$term = get_the_terms( $post_id, 'province' );
			$crumbs[] = '<a href="' . esc_url( get_term_link( $term[0] ) ) . '">' . esc_html( $province ) . '</a>';
		}

		$crumbs[] = '<span aria-current="page">' . esc_html( get_the_title( $post_id ) ) . '</span>';

		$prev = get_previous_post( true, '', 'listing_category' );
		$next = get_next_post( true, '', 'listing_category' );
		?>
		<nav class="acreage-w-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'acreage' ); ?>">
			<span class="acreage-w-crumbs__trail">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput -- each crumb escaped above.
				echo implode( '<span class="acreage-w-crumbs__sep" aria-hidden="true">/</span>', $crumbs );
				?>
			</span>

			<span class="acreage-w-crumbs__nav">
				<a class="acreage-w-crumbs__back" href="<?php echo esc_url( $archive ); ?>">
					<?php esc_html_e( '← Back to results', 'acreage' ); ?>
				</a>

				<span class="acreage-w-crumbs__steps">
					<?php if ( $prev ) : ?>
						<a class="acreage-w-crumbs__step" href="<?php echo esc_url( get_permalink( $prev ) ); ?>"
							rel="prev" title="<?php echo esc_attr( get_the_title( $prev ) ); ?>">
							<?php esc_html_e( '← Prev', 'acreage' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $next ) : ?>
						<a class="acreage-w-crumbs__step" href="<?php echo esc_url( get_permalink( $next ) ); ?>"
							rel="next" title="<?php echo esc_attr( get_the_title( $next ) ); ?>">
							<?php esc_html_e( 'Next →', 'acreage' ); ?>
						</a>
					<?php endif; ?>
				</span>
			</span>
		</nav>
		<?php
	}

	/**
	 * The hero band: photograph, location, name and price in one block.
	 *
	 * @param int $post_id Listing.
	 */
	private function render_hero( $post_id ) {
		$img      = get_the_post_thumbnail_url( $post_id, 'full' );
		$province = $this->first_term( $post_id, 'province' );
		$region   = $this->first_term( $post_id, 'region' );
		$status   = $this->first_term( $post_id, 'status' );
		$big_five = get_post_meta( $post_id, 'acreage_big_five', true );
		$extent   = $this->extent( $post_id );

		$place = trim( implode( ', ', array_filter( array( $region, $province ) ) ), ', ' );
		$meta  = trim( implode( ' · ', array_filter( array( $place, $extent ) ) ), ' ·' );
		$badge = $big_five ? __( 'Big Five', 'acreage' ) : $status;
		?>
		<div class="acreage-w-hero<?php echo $img ? '' : ' acreage-w-hero--noimg'; ?>"
			<?php if ( $img ) : ?>style="background-image:url('<?php echo esc_url( $img ); ?>')"<?php endif; ?>>

			<span class="acreage-w-hero__scrim" aria-hidden="true"></span>

			<?php if ( $badge ) : ?>
				<span class="acreage-w-hero__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>

			<div class="acreage-w-hero__body">
				<div>
					<?php if ( $meta ) : ?>
						<span class="acreage-w-hero__meta"><?php echo esc_html( $meta ); ?></span>
					<?php endif; ?>
					<h1 class="acreage-w-hero__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				</div>
				<span class="acreage-w-hero__price"><?php echo esc_html( $this->price( $post_id ) ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Three other farms, preferring the same category.
	 *
	 * A buyer who has read to the foot of a listing and not enquired is about to
	 * leave. Three comparable farms is the cheapest thing that keeps them.
	 *
	 * @param int $post_id Current listing.
	 */
	private function render_similar( $post_id ) {
		$terms = wp_get_object_terms( $post_id, 'listing_category', array( 'fields' => 'ids' ) );

		$args = array(
			'post_type'           => Acreage_Core_Post_Types::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true,
			'orderby'             => 'rand',
		);

		if ( $terms && ! is_wp_error( $terms ) ) {
			$args['tax_query'] = array(
				array( 'taxonomy' => 'listing_category', 'field' => 'term_id', 'terms' => $terms ),
			);
		}

		$query = new WP_Query( $args );

		// Fall back to any farm rather than printing an empty band.
		if ( ! $query->have_posts() && isset( $args['tax_query'] ) ) {
			unset( $args['tax_query'] );
			$query = new WP_Query( $args );
		}

		if ( ! $query->have_posts() ) {
			$this->editor_notice( __( 'No other farms to show yet.', 'acreage' ) );
			return;
		}

		$card = array(
			'count' => 3, 'orderby' => 'latest', 'show_image' => 1, 'show_status' => 1,
			'show_province' => 1, 'show_excerpt' => 0, 'show_extent' => 1,
			'show_price' => 1, 'show_vat' => 0, 'link_text' => __( 'View listing', 'acreage' ),
		);

		if ( class_exists( 'Acreage_Core_Grid' ) ) {
			$card = Acreage_Core_Grid::normalise( $card );
		}

		echo '<div class="acreage-w-grid acreage-w-grid--similar">';

		while ( $query->have_posts() ) {
			$query->the_post();

			if ( class_exists( 'Acreage_Core_Grid' ) ) {
				Acreage_Core_Grid::card( $card );
			}
		}

		wp_reset_postdata();

		echo '</div>';
	}

	private function render_facts( $post_id ) {
		$facts = array(
			array( __( 'Extent', 'acreage' ), $this->extent( $post_id ) ),
			array( __( 'Province', 'acreage' ), $this->first_term( $post_id, 'province' ) ),
			array( __( 'Region', 'acreage' ), $this->first_term( $post_id, 'region' ) ),
			array( __( 'Kind', 'acreage' ), $this->first_term( $post_id, 'listing_category' ) ),
			array( __( 'Status', 'acreage' ), $this->first_term( $post_id, 'status' ) ),
		);

		if ( get_post_meta( $post_id, 'acreage_big_five', true ) ) {
			$facts[] = array( __( 'Big Five', 'acreage' ), __( 'Yes', 'acreage' ) );
		}

		// Drop the blanks first: an empty definition list renders as a stray gap
		// and reads as a broken widget rather than a farm with nothing recorded.
		$facts = array_filter( $facts, static function ( $fact ) {
			return '' !== $fact[1];
		} );

		if ( ! $facts ) {
			$this->editor_notice( __( 'This farm has no extent, province or status recorded yet.', 'acreage' ) );
			return;
		}

		echo '<dl class="acreage-w-facts">';
		foreach ( $facts as $fact ) {
			list( $label, $value ) = $fact;

			printf(
				'<div class="acreage-w-facts__row"><dt>%s</dt><dd>%s</dd></div>',
				esc_html( $label ),
				esc_html( $value )
			);
		}
		echo '</dl>';
	}

	private function render_species( $post_id ) {
		$terms = get_the_terms( $post_id, 'species' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			$this->editor_notice( __( 'This farm has no species listed yet.', 'acreage' ) );
			return;
		}

		echo '<ul class="acreage-w-species">';
		foreach ( $terms as $term ) {
			$url = add_query_arg(
				array( 'post_type' => Acreage_Core_Post_Types::POST_TYPE, 'species' => $term->slug ),
				$this->archive_url()
			);
			printf(
				'<li><a class="acreage-w-species__chip" href="%s">%s</a></li>',
				esc_url( $url ),
				esc_html( $term->name )
			);
		}
		echo '</ul>';
	}

	private function render_gallery( $post_id ) {
		$ids = Acreage_Core_Query::gallery_ids( $post_id );

		if ( ! $ids ) {
			$this->editor_notice( __( 'This farm has no gallery photographs yet.', 'acreage' ) );
			return;
		}

		echo '<div class="acreage-w-gallery">';
		foreach ( $ids as $id ) {
			$full  = wp_get_attachment_image_url( $id, 'full' );
			$thumb = wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) );

			if ( ! $thumb ) {
				continue;
			}

			printf(
				'<a class="acreage-w-gallery__item" href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( $full ),
				wp_kses_post( $thumb )
			);
		}
		echo '</div>';
	}

	private function render_video( $post_id ) {
		$url = get_post_meta( $post_id, 'acreage_youtube', true );

		if ( ! $url ) {
			$this->editor_notice( __( 'This farm has no video link yet.', 'acreage' ) );
			return;
		}

		$embed = wp_oembed_get( $url );

		if ( ! $embed ) {
			return;
		}

		echo '<div class="acreage-w-video">' . wp_kses_post( $embed ) . '</div>';
	}
}
