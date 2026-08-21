<?php
/**
 * Farm Search — the five-dropdown search from the homepage mockup.
 *
 * A plain GET form that redirects to the archive with the chosen values as
 * query args, which the plugin's combined filter then reads. No JavaScript, so
 * every result is a linkable, shareable, bookmarkable URL.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Farm_Search extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-farm-search';
	}

	public function get_title() {
		return __( 'Farm Search', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-search';
	}

	/** The axes a visitor can search on. */
	private function axes() {
		return array(
			'listing_category' => __( 'Category', 'acreage' ),
			'province'         => __( 'Province', 'acreage' ),
			'region'           => __( 'Region', 'acreage' ),
			'size_band'        => __( 'Size band', 'acreage' ),
			'price_band'       => __( 'Price band', 'acreage' ),
		);
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Search fields', 'acreage' ),
		) );

		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Search all listings', 'acreage' ),
		) );

		foreach ( $this->axes() as $taxonomy => $label ) {
			$this->add_control( 'show_' . $taxonomy, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'acreage' ),
				'label_off'    => __( 'Hide', 'acreage' ),
				'default'      => 'yes',
				'return_value' => 'yes',
			) );
		}

		$this->add_control( 'submit_text', array(
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Search farms', 'acreage' ),
		) );

		$this->add_control( 'show_clear', array(
			'label'        => __( 'Show a "Clear" link', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'bg', array(
			'label'     => __( 'Background', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-search' => 'background:{{VALUE}};' ),
		) );

		$this->add_control( 'button_bg', array(
			'label'     => __( 'Button colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-search__submit' => 'background:{{VALUE}};' ),
		) );

		$this->add_responsive_control( 'field_columns', array(
			'label'          => __( 'Fields per row', 'acreage' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '5',
			'tablet_default' => '2',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5' ),
			'selectors'      => array(
				'{{WRAPPER}} .acreage-w-search__fields' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));',
			),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active, so there is nothing to search.', 'acreage' ) );
			return;
		}
		?>
		<form class="acreage-w-search" method="get" action="<?php echo esc_url( $this->archive_url() ); ?>" role="search">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Acreage_Core_Post_Types::POST_TYPE ); ?>">

			<?php if ( $settings['heading'] ) : ?>
				<h2 class="acreage-w-search__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>

			<div class="acreage-w-search__fields">
				<?php
				foreach ( $this->axes() as $taxonomy => $label ) :
					if ( 'yes' !== $settings[ 'show_' . $taxonomy ] || ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

					if ( ! $terms || is_wp_error( $terms ) ) {
						continue;
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only search.
					$current = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
					$id      = 'acreage-search-' . $taxonomy . '-' . $this->get_id();
					?>
					<div class="acreage-w-search__field">
						<label class="acreage-w-search__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
						<select class="acreage-w-search__select" name="<?php echo esc_attr( $taxonomy ); ?>" id="<?php echo esc_attr( $id ); ?>">
							<option value=""><?php esc_html_e( 'Any', 'acreage' ); ?></option>
							<?php foreach ( $terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current, $term->slug ); ?>>
									<?php echo esc_html( $term->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="acreage-w-search__actions">
				<button class="acreage-w-search__submit" type="submit"><?php echo esc_html( $settings['submit_text'] ); ?></button>

				<?php if ( 'yes' === $settings['show_clear'] ) : ?>
					<a class="acreage-w-search__clear" href="<?php echo esc_url( $this->archive_url() ); ?>"><?php esc_html_e( 'Clear', 'acreage' ); ?></a>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}
}
