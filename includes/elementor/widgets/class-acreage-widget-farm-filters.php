<?php
/**
 * Farm Filters — the archive sidebar from the "Farms for Sale" mockup.
 *
 * Checkbox lists per axis, submitted as a normal form. Filters read from and
 * write to the URL, so every combination is linkable, exactly as the mockup's
 * build note asks. Counts are shown so a visitor can see where the inventory
 * actually is before clicking.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Farm_Filters extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-farm-filters';
	}

	public function get_title() {
		return __( 'Farm Filters', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	private function axes() {
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

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Filters', 'acreage' ),
		) );

		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Filter', 'acreage' ),
		) );

		foreach ( $this->axes() as $taxonomy => $label ) {
			$this->add_control( 'show_' . $taxonomy, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => in_array( $taxonomy, array( 'species', 'status', 'region' ), true ) ? '' : 'yes',
				'return_value' => 'yes',
			) );
		}

		$this->add_control( 'show_counts', array(
			'label'        => __( 'Show how many farms are in each', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'hide_empty', array(
			'label'        => __( 'Hide options with no farms', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'submit_text', array(
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Apply filters', 'acreage' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-filters' => '--acreage-w-accent:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The term slugs currently filtering one axis.
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
	 * The panel used to look at $_GET alone. So a visitor who arrived from a
	 * province tile saw a filtered list of farms, every checkbox unticked, and
	 * no way to clear anything — because as far as this widget was concerned
	 * nothing was filtered at all. Asking the query, not just the URL, is what
	 * makes the three routes behave the same.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return string[] Term slugs.
	 */
	private function current_terms( $taxonomy ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filtering.
		$raw    = isset( $_GET[ $taxonomy ] ) ? wp_unslash( $_GET[ $taxonomy ] ) : '';
		$chosen = array_filter( array_map( 'sanitize_title', is_array( $raw ) ? $raw : explode( ',', $raw ) ) );

		if ( is_tax( $taxonomy ) ) {
			$term = get_queried_object();

			if ( $term instanceof WP_Term ) {
				$chosen[] = $term->slug;
			}
		}

		return array_values( array_unique( $chosen ) );
	}

	/** The keyword currently searched, if any. */
	private function current_search() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only search.
		return isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	}

	/**
	 * Every filter in force, as removable chips.
	 *
	 * Built from ALL seven axes, not only the ones this widget is set to
	 * display. Hiding the Species list in the widget settings does not stop a
	 * ?species=sable arriving from somewhere else, and a filter the visitor can
	 * neither see nor switch off is the worst of both.
	 *
	 * @return array[] label, and the URL that drops just this one.
	 */
	private function active_filters() {
		$state = array();

		foreach ( array_keys( $this->axes() ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$state[ $taxonomy ] = $this->current_terms( $taxonomy );
			}
		}

		$search = $this->current_search();
		$chips  = array();

		/*
		 * Every chip's URL is rebuilt from the canonical state rather than by
		 * editing the current URL, so a pretty term archive and a query string
		 * both collapse to the same shape and removing one filter can never
		 * strand the others.
		 */
		$build = function ( $state, $search ) {
			$url = $this->archive_url();

			foreach ( $state as $taxonomy => $slugs ) {
				if ( $slugs ) {
					$url = add_query_arg( $taxonomy, implode( ',', $slugs ), $url );
				}
			}

			if ( '' !== $search ) {
				$url = add_query_arg( 's', rawurlencode( $search ), $url );
			}

			return $url;
		};

		foreach ( $state as $taxonomy => $slugs ) {
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, $taxonomy );

				$without                        = $state;
				$without[ $taxonomy ]           = array_values( array_diff( $slugs, array( $slug ) ) );

				$chips[] = array(
					'label' => $term ? $term->name : $slug,
					'url'   => $build( $without, $search ),
				);
			}
		}

		if ( '' !== $search ) {
			$chips[] = array(
				/* translators: %s: the keyword searched for. */
				'label' => sprintf( __( 'Search: %s', 'acreage' ), $search ),
				'url'   => $build( $state, '' ),
			);
		}

		return $chips;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active, so there is nothing to filter.', 'acreage' ) );
			return;
		}

		$chips = $this->active_filters();
		?>
		<form class="acreage-w-filters" method="get" action="<?php echo esc_url( $this->archive_url() ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Acreage_Core_Post_Types::POST_TYPE ); ?>">

			<?php if ( $settings['heading'] ) : ?>
				<h2 class="acreage-w-filters__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>

			<?php if ( $chips ) : ?>
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

					<a class="acreage-w-filters__clearall" href="<?php echo esc_url( $this->archive_url() ); ?>">
						<?php esc_html_e( 'Clear all', 'acreage' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php
			foreach ( $this->axes() as $taxonomy => $label ) :
				if ( 'yes' !== $settings[ 'show_' . $taxonomy ] || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = get_terms( array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => 'yes' === $settings['hide_empty'],
				) );

				if ( ! $terms || is_wp_error( $terms ) ) {
					continue;
				}

				$chosen = $this->current_terms( $taxonomy );
				?>
				<fieldset class="acreage-w-filters__group">
					<legend class="acreage-w-filters__legend"><?php echo esc_html( $label ); ?></legend>

					<ul class="acreage-w-filters__list">
						<?php foreach ( $terms as $term ) : ?>
							<li>
								<label class="acreage-w-filters__option">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $taxonomy ); ?>[]"
										value="<?php echo esc_attr( $term->slug ); ?>"
										<?php checked( in_array( $term->slug, $chosen, true ) ); ?>>
									<span class="acreage-w-filters__name"><?php echo esc_html( $term->name ); ?></span>
									<?php if ( 'yes' === $settings['show_counts'] ) : ?>
										<span class="acreage-w-filters__count"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</fieldset>
			<?php endforeach; ?>

			<div class="acreage-w-filters__actions">
				<button class="acreage-w-filters__submit" type="submit"><?php echo esc_html( $settings['submit_text'] ); ?></button>

				<?php if ( $chips ) : ?>
					<a class="acreage-w-filters__clear" href="<?php echo esc_url( $this->archive_url() ); ?>">
						<?php
						printf(
							/* translators: %d: number of filters currently applied. */
							esc_html( _n( 'Clear %d filter', 'Clear %d filters', count( $chips ), 'acreage' ) ),
							count( $chips )
						);
						?>
					</a>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}
}
