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

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active, so there is nothing to filter.', 'acreage' ) );
			return;
		}

		$active = 0;
		?>
		<form class="acreage-w-filters" method="get" action="<?php echo esc_url( $this->archive_url() ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Acreage_Core_Post_Types::POST_TYPE ); ?>">

			<?php if ( $settings['heading'] ) : ?>
				<h2 class="acreage-w-filters__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
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

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only filtering.
				$raw     = isset( $_GET[ $taxonomy ] ) ? wp_unslash( $_GET[ $taxonomy ] ) : '';
				$chosen  = array_filter( array_map( 'sanitize_title', is_array( $raw ) ? $raw : explode( ',', $raw ) ) );
				$active += count( $chosen );
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

				<?php if ( $active ) : ?>
					<a class="acreage-w-filters__clear" href="<?php echo esc_url( $this->archive_url() ); ?>">
						<?php
						printf(
							/* translators: %d: number of filters currently applied. */
							esc_html( _n( 'Clear %d filter', 'Clear %d filters', $active, 'acreage' ) ),
							(int) $active
						);
						?>
					</a>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}
}
