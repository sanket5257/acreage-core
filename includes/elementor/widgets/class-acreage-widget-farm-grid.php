<?php
/**
 * Farm Grid — what the mockups call a Loop Grid, without needing Elementor Pro.
 *
 * On the homepage it shows a fixed number of recent farms. On the archive page
 * it obeys the current query instead, so the same widget serves both and the
 * client only learns one thing.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Farm_Grid extends Acreage_Widget_Base {

	/**
	 * Query argument this grid pages on.
	 *
	 * Not 'paged'. A results page built on an ordinary page cannot use /page/2/
	 * without colliding with WordPress's own content pagination.
	 */
	const PAGE_ARG = 'farm-page';

	/**
	 * The range the "narrowest a card may get" slider is held to.
	 *
	 * Below the lower bound the price and the two links stop fitting on their
	 * rows; above the upper one a 1440px screen is back to two columns, which
	 * is the thing the setting exists to avoid.
	 */
	const MIN_CARD_WIDTH = 220;
	const MAX_CARD_WIDTH = 480;

	/**
	 * Whether this widget built its own query rather than reusing the main one.
	 *
	 * Only a query we own gets page links from us; on a real archive the theme
	 * template is already responsible for them and two sets would appear.
	 *
	 * @var bool
	 */
	private $owns_query = true;

	public function get_name() {
		return 'acreage-farm-grid';
	}

	public function get_title() {
		return __( 'Farm Grid', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-posts-grid';
	}

	protected function register_controls() {

		/* ------------------------------------------------------- what to show */

		$this->start_controls_section( 'content', array(
			'label' => __( 'Which farms', 'acreage' ),
		) );

		$this->add_control( 'source', array(
			'label'       => __( 'Show', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'recent',
			'options'     => array(
				'recent'   => __( 'Chosen farms', 'acreage' ),
				'featured' => __( 'Featured farms only', 'acreage' ),
				'archive'  => __( 'Whatever the page is filtered to', 'acreage' ),
			),
			'description' => __( 'Use the last option on your Farms for Sale page so the search and filters drive this grid. "Featured" shows only farms ticked as featured on the farm itself.', 'acreage' ),
		) );

		$this->add_control( 'count', array(
			'label'     => __( 'How many', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'default'   => 3,
			'min'       => 1,
			'max'       => 48,
			'condition' => array( 'source' => array( 'recent', 'featured' ) ),
		) );

		/*
		 * The archive source has its own page size.
		 *
		 * "How many" above is a fixed total for a homepage band. A results page
		 * is different: it shows a page of farms and then pages through the rest,
		 * so the number means something else and needs its own control. Sharing
		 * one control meant a results page inherited the homepage's count and
		 * silently hid every farm past the third.
		 */
		$this->add_control( 'per_page', array(
			'label'       => __( 'Farms per page', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'default'     => 12,
			'min'         => 1,
			'max'         => Acreage_Core_Grid::MAX_PER_PAGE,
			'condition'   => array( 'source' => 'archive' ),
			'description' => __( 'Farms beyond this number move onto page two, with page links below the grid.', 'acreage' ),
		) );

		$this->add_control( 'category', array(
			'label'     => __( 'Only this kind', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '',
			'options'   => $this->term_options( 'listing_category', __( 'Game and cattle', 'acreage' ) ),
			'condition' => array( 'source' => array( 'recent', 'featured' ) ),
		) );

		$this->add_control( 'province', array(
			'label'     => __( 'Only this province', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '',
			'options'   => $this->term_options( 'province', __( 'Everywhere', 'acreage' ) ),
			'condition' => array( 'source' => array( 'recent', 'featured' ) ),
		) );

		$this->add_control( 'orderby', array(
			'label'     => __( 'Order', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'latest',
			'options'   => array(
				'latest'     => __( 'Newest first', 'acreage' ),
				'oldest'     => __( 'Oldest first', 'acreage' ),
				'price-high' => __( 'Highest price first', 'acreage' ),
				'price-low'  => __( 'Lowest price first', 'acreage' ),
				'rand'       => __( 'Random', 'acreage' ),
			),
			'condition' => array( 'source' => array( 'recent', 'featured' ) ),
		) );

		$this->end_controls_section();

		/* --------------------------------------------------------- how to browse */

		$this->start_controls_section( 'browsing', array(
			'label' => __( 'How visitors browse it', 'acreage' ),
		) );

		$this->add_control( 'presentation', array(
			'label'   => __( 'Arrangement', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'grid',
			'options' => array(
				'grid'     => __( 'Grid', 'acreage' ),
				'carousel' => __( 'Sideways scroller', 'acreage' ),
			),
			'description' => __( 'The scroller is a plain horizontal strip with snap points — it works by swiping or with the arrow keys, and needs no JavaScript to be usable.', 'acreage' ),
		) );

		$this->add_control( 'show_tabs', array(
			'label'        => __( 'Category tabs above the grid', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'acreage' ),
			'label_off'    => __( 'No', 'acreage' ),
			'default'      => '',
			'return_value' => 'yes',
			'description'  => __( 'Adds an All / Game farms / Cattle farms row that swaps the cards without reloading the page.', 'acreage' ),
			'condition'    => array( 'source' => array( 'recent', 'featured' ) ),
		) );

		$this->add_control( 'tab_all_label', array(
			'label'     => __( 'Wording for the first tab', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'All farms', 'acreage' ),
			'condition' => array( 'show_tabs' => 'yes', 'source' => array( 'recent', 'featured' ) ),
		) );

		$this->add_control( 'load_more', array(
			'label'        => __( 'Load more button', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Yes', 'acreage' ),
			'label_off'    => __( 'No', 'acreage' ),
			'default'      => '',
			'return_value' => 'yes',
			'description'  => __( 'Appends the next set of farms below the current ones. Hidden automatically once there are none left.', 'acreage' ),
			'condition'    => array( 'source' => array( 'recent', 'featured' ), 'presentation' => 'grid' ),
		) );

		$this->add_control( 'load_more_text', array(
			'label'     => __( 'Button wording', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Show more farms', 'acreage' ),
			'condition' => array( 'load_more' => 'yes', 'source' => array( 'recent', 'featured' ), 'presentation' => 'grid' ),
		) );

		/*
		 * The bar above the results. Only a results page has one — a homepage
		 * band shows a chosen three and there is nothing to count or reorder.
		 */
		$this->add_control( 'show_count', array(
			'label'        => __( 'How many farms matched', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'source' => 'archive' ),
			'description'  => __( 'Reads "10 of 12 matching farms", and says how many were filtered out.', 'acreage' ),
		) );

		$this->add_control( 'show_sort', array(
			'label'        => __( 'Sorting dropdown', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'source' => 'archive' ),
			'description'  => __( 'Newest, oldest and price, at the top right of the results.', 'acreage' ),
		) );

		$this->add_control( 'sort_label', array(
			'label'     => __( 'Sorting label', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Sort', 'acreage' ),
			'condition' => array( 'source' => 'archive', 'show_sort' => 'yes' ),
		) );

		$this->end_controls_section();

		/* ---------------------------------------------------------- what to say */

		$this->start_controls_section( 'display', array(
			'label' => __( 'What to show on each card', 'acreage' ),
		) );

		foreach ( array(
			'show_image'    => array( __( 'Photograph', 'acreage' ), 'yes' ),
			'show_status'   => array( __( 'Status badge', 'acreage' ), 'yes' ),
			'show_category' => array( __( 'Kind of farm', 'acreage' ), 'yes' ),
			'show_region'   => array( __( 'Region', 'acreage' ), 'yes' ),
			'show_province' => array( __( 'Province', 'acreage' ), 'yes' ),
			'show_price'    => array( __( 'Price', 'acreage' ), 'yes' ),
			'show_vat'      => array( __( 'The "excludes VAT" line', 'acreage' ), 'yes' ),
			'show_excerpt'  => array( __( 'Short summary', 'acreage' ), 'yes' ),
			'show_extent'   => array( __( 'Extent in hectares', 'acreage' ), 'yes' ),
		) as $key => $config ) {
			list( $label, $default ) = $config;

			$this->add_control( $key, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'acreage' ),
				'label_off'    => __( 'No', 'acreage' ),
				'default'      => $default,
				'return_value' => 'yes',
			) );
		}

		$this->add_control( 'link_text', array(
			'label'   => __( 'Link wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'View listing', 'acreage' ),
		) );

		$this->add_control( 'enquire_text', array(
			'label'       => __( 'Enquire link wording', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Enquire', 'acreage' ),
			'description' => __( 'Jumps straight to the enquiry form on the listing. Leave empty to show only the first link.', 'acreage' ),
		) );

		$this->add_control( 'empty_text', array(
			'label'       => __( 'When nothing matches', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'No farms match that combination. Try widening one of the filters.', 'acreage' ),
			'description' => __( 'An empty result is a moment to give direction, not a blank space.', 'acreage' ),
		) );

		$this->end_controls_section();

		/* ------------------------------------------------------------- layout */

		$this->start_controls_section( 'layout', array(
			'label' => __( 'Layout', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		/*
		 * A results page and a homepage band want opposite things here.
		 *
		 * The band shows a chosen three, so three columns is the design. The
		 * results page shows whatever the filters left, on whatever screen the
		 * visitor brought, and a number fixed at build time is wrong on most of
		 * them: two fat cards on a 27" monitor with half the width empty, which
		 * is what this widget did until now. So the results page measures
		 * instead — as many columns as fit at the card width below — and only
		 * the band is asked to commit to a number.
		 */
		$this->add_control( 'columns_mode', array(
			'label'       => __( 'How many across', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'auto',
			'options'     => array(
				'auto'  => __( 'As many as fit the screen', 'acreage' ),
				'fixed' => __( 'A fixed number', 'acreage' ),
			),
			'condition'   => array( 'source' => 'archive' ),
			'description' => __( 'A wide screen gets more farms per row instead of wider cards. The Columns setting below is used when you choose a fixed number.', 'acreage' ),
		) );

		$this->add_control( 'column_min', array(
			'label'       => __( 'Narrowest a card may get', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SLIDER,
			'size_units'  => array( 'px' ),
			'range'       => array( 'px' => array( 'min' => self::MIN_CARD_WIDTH, 'max' => self::MAX_CARD_WIDTH ) ),
			/*
			 * 288, not the 300 the homepage band uses. The comp sets the archive
			 * grid narrower on purpose, because the filter panel takes a quarter
			 * of the row: at 300 a 1440px screen fits two cards with 300px of
			 * empty gutter, and at 288 it fits three.
			 */
			'default'     => array( 'unit' => 'px', 'size' => 288 ),
			'condition'   => array( 'source' => 'archive', 'columns_mode' => 'auto' ),
			'description' => __( 'This is what decides how many fit. A smaller number means more, narrower cards.', 'acreage' ),
		) );

		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'acreage' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '3',
			'tablet_default' => '2',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ),
			'selectors'      => array(
				'{{WRAPPER}} .acreage-w-grid' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));',
			),
		) );

		$this->add_responsive_control( 'gap', array(
			'label'      => __( 'Space between', 'acreage' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 28 ),
			'selectors'  => array( '{{WRAPPER}} .acreage-w-grid' => 'gap:{{SIZE}}{{UNIT}};' ),
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-card' => '--acreage-w-accent:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}


	/**
	 * Turn Elementor's settings into the payload the grid engine understands.
	 *
	 * Elementor stores switchers as the string 'yes' or an empty string; the
	 * engine wants booleans, and it wants them in a fixed shape so the signature
	 * is reproducible. One conversion point, here.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function payload( $settings ) {
		$archive = 'archive' === $settings['source'];

		if ( $archive ) {
			$count = ! empty( $settings['per_page'] ) ? (int) $settings['per_page'] : 12;
		} else {
			$count = ! empty( $settings['count'] ) ? (int) $settings['count'] : 3;
		}

		/*
		 * "Only this province" belongs to the two chosen-farms sources; the
		 * archive obeys the page instead. Elementor keeps a control's value after
		 * its condition stops being met, though, so a grid switched from Featured
		 * to Archive still has a province sitting in its settings. It is ignored
		 * on first paint, because the archive reuses the main query — but the AJAX
		 * endpoint builds its own, and would have quietly applied it on the first
		 * live filter. Dropped here, at the one place settings become a payload.
		 */
		return Acreage_Core_Grid::normalise(
			array(
				'count'         => $count,
				'orderby'       => ! empty( $settings['orderby'] ) ? $settings['orderby'] : 'latest',
				'featured'      => 'featured' === $settings['source'],
				'province'      => ( ! $archive && ! empty( $settings['province'] ) ) ? $settings['province'] : '',
				'show_image'    => 'yes' === $settings['show_image'],
				'show_status'   => 'yes' === $settings['show_status'],
				'show_category' => 'yes' === $settings['show_category'],
				'show_region'   => 'yes' === $settings['show_region'],
				'show_province' => 'yes' === $settings['show_province'],
				'show_excerpt'  => 'yes' === $settings['show_excerpt'],
				'show_extent'   => 'yes' === $settings['show_extent'],
				'show_price'    => 'yes' === $settings['show_price'],
				'show_vat'      => 'yes' === $settings['show_vat'],
				'link_text'     => $settings['link_text'],
				'enquire_text'  => $settings['enquire_text'],
			)
		);
	}

	/**
	 * The query for this widget.
	 *
	 * On a real archive the main query already carries the visitor's filters, so
	 * we hand that straight back rather than running a second one — otherwise the
	 * grid would quietly ignore the search the visitor just performed.
	 *
	 * @param array $settings Widget settings.
	 * @param array $payload  Normalised payload.
	 * @return WP_Query
	 */
	private function query( $settings, $payload ) {
		$this->owns_query = true;

		if ( 'archive' === $settings['source'] ) {
			global $wp_query;

			if ( $wp_query instanceof WP_Query && $wp_query->get( 'post_type' ) === Acreage_Core_Post_Types::POST_TYPE ) {
				$this->owns_query = false;
				return $wp_query;
			}
			if ( is_post_type_archive( Acreage_Core_Post_Types::POST_TYPE ) || is_tax() ) {
				$this->owns_query = false;
				return $wp_query;
			}

			/*
			 * Anywhere else — a normal page acting as the results page, or the
			 * editor preview — we run the query ourselves. It still has to page,
			 * otherwise everything past the first screenful is unreachable: there
			 * is no load-more button on this source by design.
			 */
			$category = ! empty( $settings['category'] ) ? $settings['category'] : '';

			return Acreage_Core_Grid::query( $payload, $category, $this->current_page() );
		}

		$category = ! empty( $settings['category'] ) ? $settings['category'] : '';

		return Acreage_Core_Grid::query( $payload, $category, 1 );
	}

	/**
	 * The page number to show.
	 *
	 * A results page built on an ordinary WordPress page cannot use /page/2/ —
	 * that URL belongs to the page's own content pagination and usually 404s. So
	 * this grid pages on its own query argument, and only falls back to the
	 * normal query vars when it is running on a real archive.
	 *
	 * Read-only navigation, so there is nothing here to protect with a nonce;
	 * the value is cast to an integer and floored at 1 before it is used.
	 *
	 * @return int
	 */
	private function current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		if ( isset( $_GET[ self::PAGE_ARG ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
			return max( 1, absint( wp_unslash( $_GET[ self::PAGE_ARG ] ) ) );
		}

		$paged = (int) get_query_var( 'paged' );

		if ( ! $paged ) {
			$paged = (int) get_query_var( 'page' );
		}

		return max( 1, $paged );
	}

	/**
	 * The track list for a results grid that measures instead of counting.
	 *
	 * WHY THIS IS AN INLINE STYLE AND NOT A STYLESHEET RULE
	 *
	 * Elementor writes the Columns control into a per-page stylesheet at a
	 * specificity nothing in our own file can reach without !important —
	 * .elementor-1516 .elementor-element-11bc242 .acreage-w-grid. Rather than
	 * escalate, the value that must win is put where specificity does not
	 * apply. Fixed mode prints nothing at all, so the Columns control and its
	 * tablet and mobile variants stay in charge exactly as before.
	 *
	 * min() guards the narrow end: a container thinner than one card would
	 * otherwise be given a track wider than itself and overflow the page.
	 *
	 * @param array $settings Widget settings.
	 * @param bool  $carousel Whether this grid is the sideways scroller, which
	 *                        lays itself out by column flow and must be left alone.
	 * @return string A style attribute, or ''.
	 */
	private function column_style( $settings, $carousel ) {
		if ( $carousel || 'archive' !== $settings['source'] || 'fixed' === $settings['columns_mode'] ) {
			return '';
		}

		$min = isset( $settings['column_min']['size'] ) ? (int) $settings['column_min']['size'] : 288;
		$min = max( self::MIN_CARD_WIDTH, min( self::MAX_CARD_WIDTH, $min ) );

		return sprintf(
			' style="grid-template-columns:repeat(auto-fill,minmax(min(%dpx,100%%),1fr))"',
			$min
		);
	}

	/**
	 * The bar above the results: what matched, and how it is ordered.
	 *
	 * THE SORT IS A FORM, NOT A DROPDOWN THAT NEEDS SCRIPT
	 *
	 * It is a real GET form carrying the filters already in force as hidden
	 * fields. With a script it reorders the farms in place the moment the select
	 * changes, and there is no button to press — pressing something to confirm a
	 * choice you have already made is a step the comp does not have and nobody
	 * wants. Without a script a submit button appears in its place, inside a
	 * <noscript>, and the page reloads ordered with every filter intact.
	 *
	 * It does NOT reuse the panel's form, tempting as that is. A select that
	 * belongs to a form elsewhere in the page works only through the HTML form=
	 * attribute, and that would make sorting depend on a filter panel being on
	 * the page at all. The bar has to stand up on its own.
	 *
	 * @param array    $settings Widget settings.
	 * @param WP_Query $query    The query behind the grid.
	 * @param int      $per_page How many farms one page holds.
	 */
	private function render_results_bar( $settings, $query, $per_page ) {
		$count = 'yes' === $settings['show_count'];
		$sort  = 'yes' === $settings['show_sort'];

		if ( ! $count && ! $sort ) {
			return;
		}

		$state   = Acreage_Core_Filters::from_request();
		$search  = Acreage_Core_Filters::search( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only.
		$chosen  = Acreage_Core_Filters::sort( $_GET );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only.
		$matched = (int) $query->found_posts;
		$shown   = min( $matched, max( 1, (int) $this->current_page() ) * max( 1, (int) $per_page ) );
		?>
		<div class="acreage-w-results">
			<?php if ( $count ) : ?>
				<p class="acreage-w-results__count" role="status" aria-live="polite">
					<?php echo esc_html( Acreage_Core_Filters::result_text( $shown, $matched, Acreage_Core_Filters::total() ) ); ?>
				</p>
			<?php else : ?>
				<span></span><?php // Keeps the sort at the right-hand end of the row. ?>
			<?php endif; ?>

			<?php if ( $sort ) : ?>
				<form class="acreage-w-results__sort" method="get" action="<?php echo esc_url( Acreage_Core_Filters::archive_url() ); ?>">
					<?php
					echo Acreage_Core_Filters::hidden_fields( $state, $search ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped at source.

					$id = 'acreage-sort-' . $this->get_id();
					?>
					<label class="acreage-w-results__label" for="<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( $settings['sort_label'] ); ?>
					</label>

					<select class="acreage-w-results__select" name="sort" id="<?php echo esc_attr( $id ); ?>">
						<?php foreach ( Acreage_Core_Filters::sort_labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $chosen ? $chosen : 'latest', $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<?php
					/*
					 * Inside <noscript>, so it is not merely hidden — it is not
					 * there. A button that only ever exists for the seconds
					 * before a script parses is a button that flickers, and this
					 * one has nothing to do the moment the select can reorder
					 * the farms on its own.
					 */
					?>
					<noscript>
						<button class="acreage-w-results__go" type="submit"><?php esc_html_e( 'Sort', 'acreage' ); ?></button>
					</noscript>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/** The tabs, built from the real category terms rather than a hardcoded pair. */
	private function render_tabs( $settings ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'listing_category',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || count( $terms ) < 2 ) {
			return;   // One category is not a choice, so it is not a tab strip.
		}

		$current = ! empty( $settings['category'] ) ? $settings['category'] : '';
		?>
		<div class="acreage-w-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter farms by kind', 'acreage' ); ?>">
			<button type="button" role="tab" class="acreage-w-tab<?php echo '' === $current ? ' is-on' : ''; ?>"
				data-category="" aria-selected="<?php echo '' === $current ? 'true' : 'false'; ?>">
				<?php echo esc_html( $settings['tab_all_label'] ); ?>
			</button>
			<?php foreach ( $terms as $term ) : ?>
				<button type="button" role="tab" class="acreage-w-tab<?php echo $current === $term->slug ? ' is-on' : ''; ?>"
					data-category="<?php echo esc_attr( $term->slug ); ?>"
					aria-selected="<?php echo $current === $term->slug ? 'true' : 'false'; ?>">
					<?php echo esc_html( $term->name ); ?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active, so there are no farms to show.', 'acreage' ) );
			return;
		}

		$payload     = $this->payload( $settings );
		$query       = $this->query( $settings, $payload );
		$interactive = 'archive' !== $settings['source'];
		$carousel    = 'carousel' === $settings['presentation'];
		$tabs        = $interactive && 'yes' === $settings['show_tabs'];
		$more        = $interactive && ! $carousel && 'yes' === $settings['load_more'];

		/*
		 * The archive grid is the one a filter panel can drive live. A homepage
		 * band is not: it shows a chosen set of farms and there is nothing on the
		 * page to filter it with.
		 */
		$live = ! $interactive && ! $carousel;

		/*
		 * When the main query is in charge, ITS page size is what the visitor is
		 * looking at, not the widget's. Sign that number instead, or the first
		 * live filter would quietly change how many farms a page holds — twelve
		 * before the click, ten after it.
		 */
		if ( $live && ! $this->owns_query ) {
			$per_page = (int) $query->get( 'posts_per_page' );

			if ( $per_page > 0 ) {
				$payload['count'] = $per_page;
				$payload          = Acreage_Core_Grid::normalise( $payload );
			}
		}

		if ( $tabs ) {
			$this->render_tabs( $settings );
		}

		/*
		 * An empty result still prints the wrapper when the grid is live, because
		 * the filter panel needs somewhere to put the farms once the visitor
		 * widens the filter that emptied it. Without the wrapper, unticking a box
		 * would do nothing at all.
		 */
		if ( ! $live && ! $query->have_posts() ) {
			printf( '<p class="acreage-w-empty">%s</p>', esc_html( $settings['empty_text'] ) );
			return;
		}

		/*
		 * The signature travels with the markup so the AJAX endpoint can trust the
		 * display settings without letting the browser invent its own. Only printed
		 * when something can actually make a request.
		 */
		$attrs = '';

		if ( $tabs || $more || $live ) {
			// Only a grid that can actually make a request pays for the script.
			if ( $tabs || $more ) {
				wp_enqueue_script( 'acreage-grid' );
			}
			if ( $live ) {
				wp_enqueue_script( 'acreage-filters' );
			}

			$attrs = sprintf(
				' data-args="%s" data-sig="%s" data-nonce="%s" data-endpoint="%s" data-category="%s"',
				esc_attr( wp_json_encode( $payload ) ),
				esc_attr( Acreage_Core_Grid::sign( $payload ) ),
				esc_attr( wp_create_nonce( Acreage_Core_Grid::NONCE ) ),
				esc_url( admin_url( 'admin-ajax.php' ) ),
				esc_attr( ( ! $live && ! empty( $settings['category'] ) ) ? $settings['category'] : '' )
			);
		}

		if ( $live ) {
			$attrs .= sprintf(
				' data-live="1" data-empty="%s"',
				esc_attr( $settings['empty_text'] )
			);
		}

		printf(
			'<div class="acreage-w-gridwrap%1$s"%2$s>',
			$carousel ? ' acreage-w-gridwrap--scroll' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- each part escaped above.
		);

		/*
		 * Inside the wrapper, above the grid: the script that reorders in place
		 * finds it the same way it finds the cards, and the loading dim applies
		 * to the results without greying out the control being used.
		 */
		if ( $live ) {
			$this->render_results_bar( $settings, $query, $payload['count'] );
		}

		printf(
			'<div class="acreage-w-grid%1$s" aria-live="polite"%2$s>',
			$carousel ? ' acreage-w-grid--scroll' : '',
			$this->column_style( $settings, $carousel ) // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- built from a clamped integer below.
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				Acreage_Core_Grid::card( $payload );
			}

			wp_reset_postdata();
		} else {
			printf( '<p class="acreage-w-empty">%s</p>', esc_html( $settings['empty_text'] ) );
		}

		echo '</div>';

		if ( $more && $query->max_num_pages > 1 ) {
			printf(
				'<div class="acreage-w-more"><button type="button" class="acreage-w-more__btn">%s</button></div>',
				esc_html( $settings['load_more_text'] )
			);
		}

		/*
		 * A live grid gets a Load more button too, but hidden until the visitor
		 * actually filters something. Before that the page links below are still
		 * correct and still the better affordance; after it they describe a result
		 * set that no longer exists, so the script swaps one for the other.
		 */
		if ( $live ) {
			$more_text = ! empty( $settings['load_more_text'] )
				? $settings['load_more_text']
				: __( 'Show more farms', 'acreage' );

			printf(
				'<div class="acreage-w-more" hidden><button type="button" class="acreage-w-more__btn" data-failed="%s">%s</button></div>',
				esc_attr__( 'Could not load more', 'acreage' ),
				esc_html( $more_text )
			);
		}

		/*
		 * Page links for a results page.
		 *
		 * Both cases need them, for different reasons and with different URLs.
		 * A query we built pages on our own argument, because /page/2/ belongs to
		 * the host page's content pagination. The main query pages the ordinary
		 * way — and it needs us to say so: the theme's archive template hands the
		 * whole page over to the builder layout the moment one is assigned, and
		 * returns before it reaches its own the_posts_pagination(). Until this
		 * printed them, farm eleven of twelve was unreachable without JavaScript.
		 */
		if ( ! $interactive ) {
			$this->render_pagination( $query );
		}

		echo '</div>';
	}

	/**
	 * Page links for a grid running its own query.
	 *
	 * @param WP_Query $query The grid's query.
	 */
	private function render_pagination( $query ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$args = array(
			'total'     => (int) $query->max_num_pages,
			'type'      => 'array',
			'mid_size'  => 1,
			'prev_text' => __( 'Previous', 'acreage' ),
			'next_text' => __( 'Next', 'acreage' ),
		);

		if ( $this->owns_query ) {
			/*
			 * Our own argument, and an empty format because the base already says
			 * where the page number goes. The two go together: a base with no
			 * placeholder and an empty format produce a set of links that all
			 * point at page one.
			 */
			$args['base']    = add_query_arg( self::PAGE_ARG, '%#%' );
			$args['format']  = '';
			$args['current'] = $this->current_page();
		} else {
			// The main query pages the way WordPress already knows how to, so
			// paginate_links() is left to build both the base and the format.
			$args['current'] = max( 1, (int) $query->get( 'paged' ) );
		}

		$links = paginate_links( $args );

		if ( empty( $links ) ) {
			return;
		}

		echo '<nav class="acreage-w-pages" aria-label="' . esc_attr__( 'Farm listings pages', 'acreage' ) . '">';
		foreach ( $links as $link ) {
			echo wp_kses( $link, array(
				'a'    => array( 'href' => array(), 'class' => array() ),
				'span' => array( 'class' => array(), 'aria-current' => array() ),
			) );
		}
		echo '</nav>';
	}
}
