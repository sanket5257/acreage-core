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

		$this->end_controls_section();

		/* ---------------------------------------------------------- what to say */

		$this->start_controls_section( 'display', array(
			'label' => __( 'What to show on each card', 'acreage' ),
		) );

		foreach ( array(
			'show_image'    => array( __( 'Photograph', 'acreage' ), 'yes' ),
			'show_province' => array( __( 'Province', 'acreage' ), 'yes' ),
			'show_status'   => array( __( 'Status badge', 'acreage' ), 'yes' ),
			'show_extent'   => array( __( 'Extent in hectares', 'acreage' ), 'yes' ),
			'show_price'    => array( __( 'Price', 'acreage' ), 'yes' ),
			'show_vat'      => array( __( 'The "excludes VAT" line', 'acreage' ), 'yes' ),
			'show_excerpt'  => array( __( 'Short summary', 'acreage' ), '' ),
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
		if ( 'archive' === $settings['source'] ) {
			$count = ! empty( $settings['per_page'] ) ? (int) $settings['per_page'] : 12;
		} else {
			$count = ! empty( $settings['count'] ) ? (int) $settings['count'] : 3;
		}

		return Acreage_Core_Grid::normalise(
			array(
				'count'         => $count,
				'orderby'       => ! empty( $settings['orderby'] ) ? $settings['orderby'] : 'latest',
				'featured'      => 'featured' === $settings['source'],
				'province'      => ! empty( $settings['province'] ) ? $settings['province'] : '',
				'show_image'    => 'yes' === $settings['show_image'],
				'show_status'   => 'yes' === $settings['show_status'],
				'show_province' => 'yes' === $settings['show_province'],
				'show_excerpt'  => 'yes' === $settings['show_excerpt'],
				'show_extent'   => 'yes' === $settings['show_extent'],
				'show_price'    => 'yes' === $settings['show_price'],
				'show_vat'      => 'yes' === $settings['show_vat'],
				'link_text'     => $settings['link_text'],
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

		if ( $tabs ) {
			$this->render_tabs( $settings );
		}

		if ( ! $query->have_posts() ) {
			printf( '<p class="acreage-w-empty">%s</p>', esc_html( $settings['empty_text'] ) );
			return;
		}

		/*
		 * The signature travels with the markup so the AJAX endpoint can trust the
		 * display settings without letting the browser invent its own. Only printed
		 * when something can actually make a request.
		 */
		$attrs = '';

		if ( $tabs || $more ) {
			// Only a grid that can actually make a request pays for the script.
			wp_enqueue_script( 'acreage-grid' );

			$attrs = sprintf(
				' data-args="%s" data-sig="%s" data-nonce="%s" data-endpoint="%s" data-category="%s"',
				esc_attr( wp_json_encode( $payload ) ),
				esc_attr( Acreage_Core_Grid::sign( $payload ) ),
				esc_attr( wp_create_nonce( Acreage_Core_Grid::NONCE ) ),
				esc_url( admin_url( 'admin-ajax.php' ) ),
				esc_attr( ! empty( $settings['category'] ) ? $settings['category'] : '' )
			);
		}

		printf(
			'<div class="acreage-w-gridwrap%1$s"%2$s>',
			$carousel ? ' acreage-w-gridwrap--scroll' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- each part escaped above.
		);

		printf(
			'<div class="acreage-w-grid%s" aria-live="polite">',
			$carousel ? ' acreage-w-grid--scroll' : ''
		);

		while ( $query->have_posts() ) {
			$query->the_post();
			Acreage_Core_Grid::card( $payload );
		}

		wp_reset_postdata();

		echo '</div>';

		if ( $more && $query->max_num_pages > 1 ) {
			printf(
				'<div class="acreage-w-more"><button type="button" class="acreage-w-more__btn">%s</button></div>',
				esc_html( $settings['load_more_text'] )
			);
		}

		// A results page we queried ourselves gets page links; the theme's archive
		// template already provides them when the main query is in charge.
		if ( ! $interactive && $this->owns_query ) {
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

		$links = paginate_links( array(
			'base'      => add_query_arg( self::PAGE_ARG, '%#%' ),
			'format'    => '',
			'current'   => $this->current_page(),
			'total'     => (int) $query->max_num_pages,
			'type'      => 'array',
			'mid_size'  => 1,
			'prev_text' => __( 'Previous', 'acreage' ),
			'next_text' => __( 'Next', 'acreage' ),
		) );

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
