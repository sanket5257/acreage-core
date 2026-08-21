<?php
/**
 * Province Tiles — "Browse by province" from the homepage mockup.
 *
 * Counts come from the taxonomy, so the numbers are never a stale hand-typed
 * figure. The tile swaps to the accent colour on hover, as the mockup specifies.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Province_Tiles extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-province-tiles';
	}

	public function get_title() {
		return __( 'Province Tiles', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Tiles', 'acreage' ),
		) );

		$this->add_control( 'taxonomy', array(
			'label'   => __( 'Show', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'province',
			'options' => array(
				'province'  => __( 'Provinces', 'acreage' ),
				'region'    => __( 'Regions', 'acreage' ),
				'size_band' => __( 'Size bands', 'acreage' ),
				'species'   => __( 'Species', 'acreage' ),
			),
		) );

		$this->add_control( 'hide_empty', array(
			'label'        => __( 'Hide ones with no farms', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'show_counts', array(
			'label'        => __( 'Show the number of farms', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'limit', array(
			'label'   => __( 'Most to show', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'default' => 12,
			'min'     => 1,
			'max'     => 60,
		) );

		$this->add_control( 'orderby', array(
			'label'   => __( 'Order', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'count',
			'options' => array(
				'count' => __( 'Most farms first', 'acreage' ),
				'name'  => __( 'Alphabetical', 'acreage' ),
			),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'acreage' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '4',
			'tablet_default' => '3',
			'mobile_default' => '2',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6' ),
			'selectors'      => array(
				'{{WRAPPER}} .acreage-w-tiles' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));',
			),
		) );

		$this->add_control( 'tile_bg', array(
			'label'     => __( 'Tile background', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-tile' => 'background:{{VALUE}};' ),
		) );

		$this->add_control( 'hover_bg', array(
			'label'     => __( 'Tile background on hover', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-tile:hover' => 'background:{{VALUE}};' ),
		) );

		$this->add_control( 'hover_text', array(
			'label'     => __( 'Tile text on hover', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .acreage-w-tile:hover .acreage-w-tile__name'  => 'color:{{VALUE}};',
				'{{WRAPPER}} .acreage-w-tile:hover .acreage-w-tile__count' => 'color:{{VALUE}};',
			),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$taxonomy = $settings['taxonomy'];

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$this->editor_notice( __( 'That set of terms does not exist yet. Activate the Acreage Core plugin.', 'acreage' ) );
			return;
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => 'yes' === $settings['hide_empty'],
			'number'     => (int) $settings['limit'],
			'orderby'    => 'count' === $settings['orderby'] ? 'count' : 'name',
			'order'      => 'count' === $settings['orderby'] ? 'DESC' : 'ASC',
		) );

		if ( ! $terms || is_wp_error( $terms ) ) {
			$this->editor_notice( __( 'No terms to show yet. Add a farm, or untick "Hide ones with no farms".', 'acreage' ) );
			return;
		}

		echo '<div class="acreage-w-tiles">';

		foreach ( $terms as $term ) {
			$url = add_query_arg(
				array( 'post_type' => Acreage_Core_Post_Types::POST_TYPE, $taxonomy => $term->slug ),
				$this->archive_url()
			);
			?>
			<a class="acreage-w-tile" href="<?php echo esc_url( $url ); ?>">
				<span class="acreage-w-tile__name"><?php echo esc_html( $term->name ); ?></span>
				<?php if ( 'yes' === $settings['show_counts'] ) : ?>
					<span class="acreage-w-tile__count">
						<?php
						printf(
							/* translators: %s: number of farms. */
							esc_html( _n( '%s listing', '%s listings', $term->count, 'acreage' ) ),
							esc_html( number_format_i18n( $term->count ) )
						);
						?>
					</span>
				<?php endif; ?>
			</a>
			<?php
		}

		echo '</div>';
	}
}
