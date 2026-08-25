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

	/**
	 * The range the gallery's "photograph size" slider is held to.
	 *
	 * Below the lower bound a thumbnail stops showing what the photograph is of;
	 * above the upper one the row is back to being a stack of full-width images,
	 * which is the thing the setting exists to avoid.
	 */
	const MIN_THUMB = 120;
	const MAX_THUMB = 400;

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
			'location'     => __( 'Location map', 'acreage' ),
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
			'condition'    => array( 'part' => array( 'sections', 'description', 'improvements', 'wildlife', 'land_claims', 'location' ) ),
		) );

		$this->add_control( 'location_heading', array(
			'label'     => __( 'Map heading', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Location', 'acreage' ),
			'condition' => array( 'part' => 'location', 'show_headings' => 'yes' ),
		) );

		$this->add_control( 'map_height', array(
			'label'      => __( 'Map height', 'acreage' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 180, 'max' => 700 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 420 ),
			'condition'  => array( 'part' => 'location' ),
			'selectors'  => array(
				'{{WRAPPER}} .acreage-w-location__map' => '--acreage-w-map-h:{{SIZE}}px;',
			),
		) );

		/*
		 * A size, not a column count.
		 *
		 * The gallery used to be three fixed columns, which meant that on a wide
		 * listing page five photographs came out at 467px each — bigger than the
		 * cards on the results page, and a scroll apiece. What a visitor wants
		 * from the row under the hero is to see at a glance how many photographs
		 * there are and pick one, so the right control is how big a thumbnail
		 * should be; how many fit is then the page's business, not a number
		 * chosen once at build time and wrong on every other screen.
		 */
		$this->add_control( 'lightbox', array(
			'label'        => __( 'Open photographs in a lightbox', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'part' => 'gallery' ),
			'description'  => __( 'All the photographs of the farm become one slideshow, with arrows and the arrow keys to move between them. Turned off, each photograph opens on its own in a new tab.', 'acreage' ),
		) );

		$this->add_control( 'thumb_size', array(
			'label'       => __( 'Photograph size', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px' ),
			'range'       => array( 'px' => array( 'min' => self::MIN_THUMB, 'max' => self::MAX_THUMB ) ),
			'default'     => array( 'unit' => 'px', 'size' => 260 ),
			'condition'   => array( 'part' => 'gallery' ),
			'description' => __( 'As many as fit the row, at roughly this width. Smaller means more per row.', 'acreage' ),
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

		/*
		 * A PART WITH NOTHING TO SHOW HAS TO TAKE UP NO ROOM
		 *
		 * Every part here can legitimately be empty: a cattle farm has no
		 * wildlife section, most farms have no video, and a farm whose position
		 * has not been published has no map. The widget already prints nothing
		 * in those cases — but the Elementor section holding it still draws its
		 * own background, its top rule and its 160px of padding, so the visitor
		 * gets an empty cream band that reads as a page half-loaded.
		 *
		 * The layout cannot know in advance which fields a given farm has
		 * filled in, so the widget says so after the fact instead, and the
		 * stylesheet folds the band away. Anything the part did print — the
		 * editor notice included — keeps the band open.
		 */
		ob_start();

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
			case 'improvements':
			case 'wildlife':
			case 'land_claims':
				$this->render_section( $post_id, $settings['part'], 'yes' === $settings['show_headings'] );
				break;
			case 'sections':
				/*
				 * The description is the first of the four, not a preamble to the
				 * other three. In the comp it carries its own heading and its own
				 * hairline, which is what makes the page read as one run of
				 * sections rather than a block of prose that three labelled
				 * fields were bolted onto.
				 */
				foreach ( array( 'description', 'improvements', 'wildlife', 'land_claims' ) as $section ) {
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
			case 'location':
				$this->render_location( $post_id, $settings );
				break;
		}

		$body = ob_get_clean();

		printf(
			'<div class="acreage-w-detail%s">',
			'' === trim( $body ) ? ' acreage-w-detail--empty' : ''
		);

		echo $body; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped as it was built.
		echo '</div>';
	}

	private function render_html( $html ) {
		echo '<div class="acreage-w-prose">' . wp_kses_post( $html ) . '</div>';
	}

	private function section_label( $key ) {
		$labels = array(
			'description'  => __( 'Description', 'acreage' ),
			'improvements' => __( 'Improvements', 'acreage' ),
			'wildlife'     => __( 'Wildlife & vegetation', 'acreage' ),
			'land_claims'  => __( 'Land claims', 'acreage' ),
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : '';
	}

	/**
	 * The written body of a section.
	 *
	 * The description is the post's own content; the other three are fields on
	 * the farm. Reading them through one function is what lets the description
	 * be laid out as a section like any other, which is how the comp has it —
	 * four headings down the page with a hairline above each, not one
	 * unannounced block of prose followed by three labelled ones.
	 *
	 * @param int    $post_id Farm.
	 * @param string $key     Section key.
	 * @return string Raw HTML, ready for the_content filters or wpautop.
	 */
	private function section_body( $post_id, $key ) {
		if ( 'description' === $key ) {
			return apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) );
		}

		return wpautop( (string) get_post_meta( $post_id, 'acreage_' . $key, true ) );
	}

	private function render_section( $post_id, $key, $with_heading ) {
		$value = $this->section_body( $post_id, $key );

		// A cattle farm has no wildlife section, and an empty heading with
		// nothing under it looks like a fault rather than an omission.
		if ( '' === trim( wp_strip_all_tags( (string) $value ) ) ) {
			return;
		}

		/*
		 * Each section is wrapped, so that it can carry the rule above it.
		 *
		 * The divider belongs to the section, not to the gap between two of
		 * them: a border drawn on the heading alone disappears the moment a
		 * heading is switched off, and a border on the gap has nothing to
		 * attach to when a cattle farm skips the wildlife section entirely.
		 * Hung on the section, the rules land in the right places whichever
		 * fields this particular farm happens to have filled in.
		 */
		printf( '<section class="acreage-w-detail__section acreage-w-detail__section--%s">', esc_attr( str_replace( '_', '-', $key ) ) );

		if ( $with_heading ) {
			printf( '<h2 class="acreage-w-detail__heading">%s</h2>', esc_html( $this->section_label( $key ) ) );
		}

		$this->render_html( $value );

		echo '</section>';
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
						Acreage_Core_Grid::number( round( $price / $hectares ) )
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
	 * The hero: photograph on one side, the farm's particulars on the other.
	 *
	 * WHY THE TEXT IS NOT ON THE PHOTOGRAPH ANY MORE
	 *
	 * The first version laid the name, location and price over the image with a
	 * gradient scrim under them. That works on the one photograph it was designed
	 * against and is a lottery on the rest: these are drone shots of real farms,
	 * so the bottom third is sometimes dark bushveld and sometimes bright Karoo
	 * dust or a white-roofed werf, and the asking price would disappear into it.
	 * A scrim heavy enough to guarantee contrast on the worst image ruins the
	 * best one — and the photography is the product here.
	 *
	 * So the two are set side by side. Text sits on the paper colour where it is
	 * always legible, the photograph is never darkened, and the price is a plain
	 * readable figure rather than something floating over grass.
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
		$badge = $big_five ? __( 'Big Five', 'acreage' ) : $status;
		$alt   = get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );
		?>
		<div class="acreage-w-hero<?php echo $img ? '' : ' acreage-w-hero--noimg'; ?>">

			<div class="acreage-w-hero__media">
				<?php if ( $img ) : ?>
					<img src="<?php echo esc_url( $img ); ?>"
						alt="<?php echo esc_attr( $alt ? $alt : get_the_title( $post_id ) ); ?>"
						loading="eager" decoding="async">
				<?php endif; ?>

				<?php if ( $badge ) : ?>
					<span class="acreage-w-hero__badge"><?php echo esc_html( $badge ); ?></span>
				<?php endif; ?>
			</div>

			<div class="acreage-w-hero__panel">
				<?php if ( $place ) : ?>
					<span class="acreage-w-hero__meta"><?php echo esc_html( $place ); ?></span>
				<?php endif; ?>

				<h1 class="acreage-w-hero__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>

				<p class="acreage-w-hero__price"><?php echo esc_html( $this->price( $post_id ) ); ?></p>

				<?php if ( $extent ) : ?>
					<p class="acreage-w-hero__extent"><?php echo esc_html( $extent ); ?></p>
				<?php endif; ?>

				<?php
				/*
				 * Anchored rather than linked to a contact page: the enquiry form
				 * is already further down this page, and sending a buyer to a
				 * different URL loses which farm they were reading about.
				 */
				?>
				<p class="acreage-w-hero__cta">
					<a class="acreage-btn" href="#acreage-enquire">
						<?php esc_html_e( 'Enquire about this farm', 'acreage' ); ?>
					</a>
				</p>
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

		$settings = $this->get_settings_for_display();
		$size     = isset( $settings['thumb_size']['size'] ) ? (int) $settings['thumb_size']['size'] : 260;
		$size     = max( self::MIN_THUMB, min( self::MAX_THUMB, $size ) );

		/*
		 * Printed inline rather than through the control's own selectors.
		 *
		 * This row was three fixed columns until now, and that rule lives in a
		 * per-page stylesheet Elementor only rewrites when the page is next
		 * saved. A stylesheet rule of ours would lose to the stale one on every
		 * listing until someone opened and re-saved each page; an inline style
		 * is right the moment the plugin updates.
		 *
		 * The min() is what keeps a phone sensible without a media query — which
		 * an inline style could not be overridden by anyway. Capping the track
		 * at half the row less half the gap means two thumbnails always fit,
		 * whatever the size above says, so the row never degenerates into one
		 * picture per line.
		 */
		printf(
			'<div class="acreage-w-gallery" style="grid-template-columns:repeat(auto-fill,minmax(min(%dpx,calc(50%% - 5px)),1fr))">',
			$size
		);
		/*
		 * ONE SLIDESHOW, NOT SIX SEPARATE LINKS
		 *
		 * Naming every photograph into the same slideshow group is what turns a
		 * row of links into a gallery: open any one of them and the lightbox
		 * carries arrows, the arrow keys, a counter and swipe on a phone, with
		 * the rest of the farm's photographs behind them. Opening the fourth
		 * picture and finding no way to reach the fifth is the thing a visitor
		 * looking at a farm minds most.
		 *
		 * The lightbox is Elementor's rather than one of ours, deliberately. It
		 * is already on the page, it is already what every other image on the
		 * site does, and a hand-rolled modal is a focus trap, a keyboard map and
		 * a set of ARIA roles to get wrong. "yes" forces it on for these links
		 * even on a site with the global image lightbox switched off, because
		 * here it is the point of the row rather than a nicety.
		 *
		 * target="_blank" stays underneath as the fallback: with the lightbox
		 * turned off in the widget, or with no JavaScript at all, a photograph
		 * still opens.
		 */
		$slideshow = 'acreage-farm-' . (int) $post_id;
		$lightbox  = 'yes' === $settings['lightbox'];
		$farm      = get_the_title( $post_id );

		if ( $lightbox ) {
			/*
			 * ASK FOR THE STYLESHEETS, BECAUSE NOBODY ELSE WILL
			 *
			 * Elementor loads its slideshow CSS only for pages where one of ITS
			 * widgets declares that it needs it. These links are ours, written by
			 * hand, so as far as Elementor's asset loader is concerned there is
			 * no slideshow on this page and e-swiper never goes out.
			 *
			 * Everything then half-works, which is the worst way for it to fail:
			 * the lightbox opens, the arrows exist and respond, but the one
			 * declaration that makes them position:absolute lives in e-swiper, so
			 * they lay out in flow as two full-height blocks under the picture.
			 * That makes the scrolling box three screens tall, and the first
			 * press of an arrow scrolls the photograph out of sight.
			 *
			 * e-swiper depends on swiper, so this one line covers both.
			 */
			wp_enqueue_style( 'e-swiper' );
			wp_enqueue_style( 'e-lightbox' );
		}

		foreach ( $ids as $id ) {
			$full  = wp_get_attachment_image_url( $id, 'full' );
			$thumb = wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) );

			if ( ! $thumb ) {
				continue;
			}

			$attrs = '';

			if ( $lightbox ) {
				$caption = get_the_title( $id );

				/*
				 * No explicit index. Elementor works the position out from where
				 * the link sits among its slideshow group, which is already the
				 * order the client arranged the photographs in — and its index is
				 * counted from zero, so stating one is a way to open the wrong
				 * picture and nothing else.
				 */
				$attrs = sprintf(
					' data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="%1$s" data-elementor-lightbox-title="%2$s"',
					esc_attr( $slideshow ),
					esc_attr( $caption ? $caption : $farm )
				);
			}

			printf(
				'<a class="acreage-w-gallery__item" href="%1$s"%2$s target="_blank" rel="noopener">%3$s</a>',
				esc_url( $full ),
				$attrs, // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- each part escaped above.
				wp_kses_post( $thumb )
			);
		}
		echo '</div>';
	}

	/**
	 * The Location band: a heading, the client's own note, and the map.
	 *
	 * THE MAP IS LAZY AND SANDBOXED
	 *
	 * An embedded map is a third-party frame that loads a megabyte of script
	 * from Google, and it sits at the bottom of a long page most visitors never
	 * scroll to. loading="lazy" means they only pay for it if they get there.
	 *
	 * A farm with no location set prints nothing at all — not an empty grey box
	 * and not a map of the whole country, both of which read as a fault rather
	 * than as an agent who has not published the position yet.
	 *
	 * @param int   $post_id  Farm.
	 * @param array $settings Widget settings.
	 */
	private function render_location( $post_id, $settings ) {
		$url = Acreage_Core_Query::map_url( $post_id );

		if ( ! $url ) {
			$this->editor_notice( __( 'This farm has no map location yet. Set one under "Map location" on the farm itself.', 'acreage' ) );
			return;
		}

		$note = (string) get_post_meta( $post_id, 'acreage_directions', true );
		?>
		<section class="acreage-w-location">
			<?php if ( 'yes' === $settings['show_headings'] && $settings['location_heading'] ) : ?>
				<h2 class="acreage-w-detail__heading acreage-w-location__heading">
					<?php echo esc_html( $settings['location_heading'] ); ?>
				</h2>
			<?php endif; ?>

			<?php if ( '' !== trim( $note ) ) : ?>
				<p class="acreage-w-location__note"><?php echo esc_html( $note ); ?></p>
			<?php endif; ?>

			<div class="acreage-w-location__map">
				<iframe
					src="<?php echo esc_url( $url ); ?>"
					title="<?php
					printf(
						/* translators: %s: farm name. */
						esc_attr__( 'Map showing roughly where %s is', 'acreage' ),
						esc_attr( get_the_title( $post_id ) )
					);
					?>"
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade"
					allowfullscreen></iframe>
			</div>
		</section>
		<?php
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
