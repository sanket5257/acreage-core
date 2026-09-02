<?php
/**
 * Site Nav — logo, menu and a real mobile burger.
 *
 * WHY THIS WIDGET EXISTS
 *
 * Elementor's own Nav Menu widget is Pro. On Free the only option is WordPress's
 * core nav-menu widget, which renders an unstyled <ul> with no responsive
 * behaviour at all — so an Elementor-built header collapses into a tall vertical
 * list of links that eats the whole first screen on a phone. That is exactly
 * what it did here.
 *
 * So the theme ships its own: one row on desktop, a burger below the breakpoint,
 * keyboard operable, and no dependency on a paid licence.
 *
 * ACCESSIBILITY NOTES
 *
 * The toggle is a real <button> carrying aria-expanded and aria-controls. The
 * panel is a <nav> with an accessible name. Escape closes it and returns focus
 * to the button, and focus is trapped while it is open — without that, tabbing
 * out of an open overlay menu strands a keyboard user behind it.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Nav extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-nav';
	}

	public function get_title() {
		return __( 'Site Nav', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-nav-menu';
	}

	/** Menus the site actually has, as id => name. */
	private function menu_options() {
		$options = array( '' => __( '— Primary menu location —', 'acreage' ) );

		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ $menu->term_id ] = $menu->name;
		}

		return $options;
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Header', 'acreage' ),
		) );

		$this->add_control( 'menu', array(
			'label'       => __( 'Menu', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => '',
			'options'     => $this->menu_options(),
			'description' => __( 'Leave as-is to follow whatever menu is assigned to the Primary location, so changing the menu in Appearance > Menus is enough.', 'acreage' ),
		) );

		$this->add_control( 'show_brand', array(
			'label'        => __( 'Show the site name or logo', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'show_tagline', array(
			'label'        => __( 'Show the tagline', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'condition'    => array( 'show_brand' => 'yes' ),
		) );

		$this->add_control( 'show_search', array(
			'label'        => __( 'Show the search button', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
			'description'  => __( 'A magnifier in the bar that opens a keyword box. Searching goes to the farms page with the results already filtered, where the visitor can narrow them further.', 'acreage' ),
		) );

		$this->add_control( 'search_placeholder', array(
			'label'       => __( 'Search wording', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'condition'   => array( 'show_search' => 'yes' ),
			'description' => __( 'Shown greyed out inside the empty box. Leave empty for “Search farms”.', 'acreage' ),
		) );

		$this->add_control( 'cta_text', array(
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => '',
			'description' => __( 'Leave empty to show the phone number from the theme settings.', 'acreage' ),
		) );

		$this->add_control( 'cta_link', array(
			'label'   => __( 'Button link', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::URL,
			'default' => array( 'url' => '' ),
		) );

		$this->add_control( 'sticky', array(
			'label'        => __( 'Stick to the top when scrolling', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->end_controls_section();

		/* --------------------------------------------------------------- style */

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'bg', array(
			'label'     => __( 'Background', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-nav' => '--acreage-nav-bg:{{VALUE}};' ),
		) );

		$this->add_control( 'link_colour', array(
			'label'     => __( 'Link colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-nav' => '--acreage-nav-link:{{VALUE}};' ),
		) );

		$this->add_control( 'breakpoint', array(
			'label'      => __( 'Switch to the burger below', 'acreage' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 480, 'max' => 1200 ) ),
			'default'    => array( 'unit' => 'px', 'size' => 900 ),
			'description' => __( 'Below this width the menu collapses behind a burger.', 'acreage' ),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$menu = ! empty( $s['menu'] ) ? (int) $s['menu'] : 0;
		$uid  = 'acreage-nav-' . $this->get_id();

		// The burger breakpoint is per-widget, so it has to be an inline custom
		// property rather than a media query in the stylesheet.
		$bp = ! empty( $s['breakpoint']['size'] ) ? (int) $s['breakpoint']['size'] : 900;

		$cta_text = $s['cta_text'];
		$cta_url  = ! empty( $s['cta_link']['url'] ) ? $s['cta_link']['url'] : '';

		if ( ! $cta_text && function_exists( 'acreage_option' ) ) {
			$phone = acreage_option( 'phone', '' );

			if ( $phone ) {
				$cta_text = $phone;
				$cta_url  = $cta_url ? $cta_url : 'tel:' . preg_replace( '/[^0-9+]/', '', $phone );
			}
		}

		$classes = 'acreage-nav';

		if ( 'yes' === $s['sticky'] ) {
			$classes .= ' acreage-nav--sticky';
		}
		?>
		<div class="<?php echo esc_attr( $classes ); ?>" style="--acreage-nav-bp:<?php echo esc_attr( $bp ); ?>px" data-bp="<?php echo esc_attr( $bp ); ?>">
			<div class="acreage-nav__bar">

				<?php if ( 'yes' === $s['show_brand'] ) : ?>
					<div class="acreage-nav__brand">
						<?php if ( has_custom_logo() ) : ?>
							<?php the_custom_logo(); ?>
						<?php else : ?>
							<a class="acreage-nav__name" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
								<?php bloginfo( 'name' ); ?>
							</a>
							<?php
							$tagline = '';

							if ( 'yes' === $s['show_tagline'] ) {
								/*
								 * WordPress's own tagline first, the theme setting as
								 * fallback. A bare wordmark with nothing beneath it reads
								 * as unfinished on a full-bleed hero, and most installs
								 * never change the default "Just another WordPress site"
								 * — so we prefer a real strapline the theme knows about.
								 */
								$tagline = get_bloginfo( 'description', 'display' );

								if ( ! $tagline && function_exists( 'acreage_option' ) ) {
									$tagline = acreage_option( 'tagline', '' );
								}
							}
							if ( $tagline ) :
								?>
								<span class="acreage-nav__tag"><?php echo esc_html( $tagline ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<button
					type="button"
					class="acreage-nav__burger"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $uid ); ?>">
					<span aria-hidden="true"></span>
					<span aria-hidden="true"></span>
					<span aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'acreage' ); ?></span>
				</button>

				<nav id="<?php echo esc_attr( $uid ); ?>" class="acreage-nav__panel" aria-label="<?php esc_attr_e( 'Primary', 'acreage' ); ?>">
					<?php
					$args = array(
						'container'  => false,
						'menu_class' => 'acreage-nav__list',
						'depth'      => 2,
						'fallback_cb' => false,
					);

					if ( $menu ) {
						$args['menu'] = $menu;
					} else {
						$args['theme_location'] = 'primary';
					}

					wp_nav_menu( $args );

					if ( 'yes' === $s['show_search'] ) :
						$this->render_search( $uid, $s );
					endif;

					if ( $cta_text ) :
						?>
						<a class="acreage-nav__cta" href="<?php echo esc_url( $cta_url ? $cta_url : home_url( '/' ) ); ?>">
							<?php echo esc_html( $cta_text ); ?>
						</a>
					<?php endif; ?>
				</nav>
			</div>
		</div>
		<?php

		wp_enqueue_script( 'acreage-nav' );
	}

	/**
	 * The header search.
	 *
	 * WHY THE BUTTON IS A LINK
	 *
	 * With JavaScript running, this is a disclosure: the magnifier opens a
	 * keyword box under the bar and nothing navigates. Written as a <button>
	 * that is what it would also be with JavaScript off — a control that does
	 * nothing at all, next to a box the stylesheet is hiding.
	 *
	 * So it ships as a link to the farms page, where the full search and the
	 * filter panel already live. Turn scripting off and the magnifier still
	 * takes a visitor somewhere they can search; turn it on and nav.js upgrades
	 * the same element into the disclosure, adding aria-expanded as it binds.
	 * Nobody gets a dead control.
	 *
	 * The form itself is a plain GET to the archive with one field named "s",
	 * which is the same shape the Farm Search widget submits — so a header
	 * search and a search made on the page land on identical URLs, and the
	 * filter panel reads the keyword back out of the query string either way.
	 */
	private function render_search( $uid, $s ) {
		$id          = $uid . '-search';
		$placeholder = ! empty( $s['search_placeholder'] )
			? $s['search_placeholder']
			: __( 'Search farms', 'acreage' );
		?>
		<div class="acreage-nav__search">
			<a
				class="acreage-nav__searchtoggle"
				href="<?php echo esc_url( $this->archive_url() ); ?>"
				aria-controls="<?php echo esc_attr( $id ); ?>"
				data-search-toggle>
				<svg class="acreage-nav__searchicon" width="17" height="17" viewBox="0 0 17 17" fill="none" aria-hidden="true" focusable="false">
					<circle cx="7.2" cy="7.2" r="5.4" stroke="currentColor" stroke-width="1.6" />
					<path d="M11.3 11.3L15.4 15.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
				</svg>
				<span class="acreage-nav__searchlabel"><?php esc_html_e( 'Search', 'acreage' ); ?></span>
			</a>

			<form
				id="<?php echo esc_attr( $id ); ?>"
				class="acreage-nav__searchform"
				role="search"
				method="get"
				action="<?php echo esc_url( $this->archive_url() ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-field">
					<?php esc_html_e( 'Search farms', 'acreage' ); ?>
				</label>
				<input
					id="<?php echo esc_attr( $id ); ?>-field"
					class="acreage-nav__searchfield"
					type="search"
					name="s"
					value="<?php echo esc_attr( Acreage_Core_Filters::search( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only. ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					autocomplete="off" />
				<button class="acreage-nav__searchsubmit" type="submit">
					<?php esc_html_e( 'Search', 'acreage' ); ?>
				</button>
			</form>
		</div>
		<?php
	}
}
