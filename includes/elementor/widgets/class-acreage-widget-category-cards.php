<?php
/**
 * Category Cards — the "Game farms / Cattle farms" pair from the mockup.
 *
 * Image, name, live count and a short description per card. The description is
 * editable per card because it is marketing copy, not data.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Category_Cards extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-category-cards';
	}

	public function get_title() {
		return __( 'Category Cards', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-image-box';
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Cards', 'acreage' ),
		) );

		$repeater = new \Elementor\Repeater();

		$repeater->add_control( 'term', array(
			'label'   => __( 'Category', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $this->term_options( 'listing_category' ),
		) );

		$repeater->add_control( 'title', array(
			'label'       => __( 'Heading', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'description' => __( 'Leave empty to use the category name.', 'acreage' ),
		) );

		$repeater->add_control( 'text', array(
			'label' => __( 'Description', 'acreage' ),
			'type'  => \Elementor\Controls_Manager::TEXTAREA,
			'rows'  => 4,
		) );

		$repeater->add_control( 'image', array(
			'label' => __( 'Photograph', 'acreage' ),
			'type'  => \Elementor\Controls_Manager::MEDIA,
		) );

		$repeater->add_control( 'link_text', array(
			'label'   => __( 'Link wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Browse', 'acreage' ),
		) );

		$this->add_control( 'cards', array(
			'label'       => __( 'Cards', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::REPEATER,
			'fields'      => $repeater->get_controls(),
			'title_field' => '{{{ title || term }}}',
			'default'     => array(
				array( 'title' => __( 'Game farms', 'acreage' ), 'link_text' => __( 'Browse game farms', 'acreage' ) ),
				array( 'title' => __( 'Cattle farms', 'acreage' ), 'link_text' => __( 'Browse cattle farms', 'acreage' ) ),
			),
		) );

		$this->add_control( 'show_counts', array(
			'label'        => __( 'Show the number of listings', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_responsive_control( 'columns', array(
			'label'          => __( 'Columns', 'acreage' ),
			'type'           => \Elementor\Controls_Manager::SELECT,
			'default'        => '2',
			'mobile_default' => '1',
			'options'        => array( '1' => '1', '2' => '2', '3' => '3' ),
			'selectors'      => array(
				'{{WRAPPER}} .acreage-w-cats' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));',
			),
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-cats' => '--acreage-w-accent:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['cards'] ) ) {
			$this->editor_notice( __( 'Add a card in the panel on the left.', 'acreage' ) );
			return;
		}

		echo '<div class="acreage-w-cats">';

		foreach ( $settings['cards'] as $card ) {
			$term  = ! empty( $card['term'] ) ? get_term_by( 'slug', $card['term'], 'listing_category' ) : null;
			$title = ! empty( $card['title'] ) ? $card['title'] : ( $term ? $term->name : '' );

			if ( ! $title ) {
				continue;
			}

			$url = $term
				? add_query_arg(
					array( 'post_type' => Acreage_Core_Post_Types::POST_TYPE, 'listing_category' => $term->slug ),
					$this->archive_url()
				)
				: $this->archive_url();
			?>
			<a class="acreage-w-cat" href="<?php echo esc_url( $url ); ?>">
				<?php if ( ! empty( $card['image']['url'] ) ) : ?>
					<span class="acreage-w-cat__media">
						<img src="<?php echo esc_url( $card['image']['url'] ); ?>" alt="" loading="lazy" decoding="async">
					</span>
				<?php endif; ?>

				<span class="acreage-w-cat__body">
					<?php if ( 'yes' === $settings['show_counts'] && $term ) : ?>
						<span class="acreage-w-cat__count">
							<?php
							printf(
								/* translators: %s: number of listings. */
								esc_html( _n( '%s listing', '%s listings', $term->count, 'acreage' ) ),
								esc_html( number_format_i18n( $term->count ) )
							);
							?>
						</span>
					<?php endif; ?>

					<span class="acreage-w-cat__title"><?php echo esc_html( $title ); ?></span>

					<?php if ( ! empty( $card['text'] ) ) : ?>
						<span class="acreage-w-cat__text"><?php echo esc_html( $card['text'] ); ?></span>
					<?php endif; ?>

					<?php if ( ! empty( $card['link_text'] ) ) : ?>
						<span class="acreage-w-cat__more"><?php echo esc_html( $card['link_text'] ); ?></span>
					<?php endif; ?>
				</span>
			</a>
			<?php
		}

		echo '</div>';
	}
}
