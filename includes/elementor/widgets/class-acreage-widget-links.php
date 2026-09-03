<?php
/**
 * Link List — a footer column that does not go stale.
 *
 * WHAT THIS REPLACES
 *
 * The footer's link columns used to be generated as literal HTML: a Text Editor
 * widget holding <a href="https://site.com/properties/?province=limpopo">. The
 * URLs were correct at the moment the template was generated and frozen from
 * then on, so the day the customer changed the farms archive base — a supported
 * setting, on Settings > Permalinks — the whole footer quietly became a column
 * of 404s. Nothing warned them, because nothing was wrong: the content said what
 * it had always said.
 *
 * The header never had this problem. It uses the Site Nav widget, which is given
 * a menu and resolves it on every render. This is the same idea for the columns
 * underneath: the widget stores what a link POINTS AT, not where that happened
 * to be. A WordPress menu, or a taxonomy whose terms are listed automatically.
 *
 * Either way the href is built during the render, from
 * get_post_type_archive_link(), so renaming the archive moves the footer with it
 * and a new province appears in the list the moment it has a farm in it.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Links extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-links';
	}

	public function get_title() {
		return __( 'Link List', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-bullet-list';
	}

	/** Menus the site actually has, as id => name. */
	private function menu_options() {
		$options = array( '' => __( '— Select a menu —', 'acreage' ) );

		foreach ( wp_get_nav_menus() as $menu ) {
			$options[ $menu->term_id ] = $menu->name;
		}

		return $options;
	}

	/** The filter axes, as taxonomy => label. */
	private function taxonomy_options() {
		$options = array();

		$taxonomies = class_exists( 'Acreage_Core_Filters' )
			? Acreage_Core_Filters::taxonomies()
			: array( 'province' );

		foreach ( $taxonomies as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );

			if ( $object ) {
				$options[ $taxonomy ] = $object->labels->name;
			}
		}

		return $options;
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Links', 'acreage' ),
		) );

		$this->add_control( 'source', array(
			'label'       => __( 'Take the links from', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'menu',
			'options'     => array(
				'menu'     => __( 'A menu', 'acreage' ),
				'taxonomy' => __( 'Every term in a taxonomy', 'acreage' ),
			),
			'description' => __( 'A menu is edited under Appearance > Menus. A taxonomy list keeps itself up to date as terms are added.', 'acreage' ),
		) );

		$this->add_control( 'menu', array(
			'label'     => __( 'Menu', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => '',
			'options'   => $this->menu_options(),
			'condition' => array( 'source' => 'menu' ),
		) );

		$this->add_control( 'taxonomy', array(
			'label'     => __( 'Taxonomy', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::SELECT,
			'default'   => 'province',
			'options'   => $this->taxonomy_options(),
			'condition' => array( 'source' => 'taxonomy' ),
		) );

		$this->add_control( 'limit', array(
			'label'     => __( 'How many', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'max'       => 40,
			'default'   => 10,
			'condition' => array( 'source' => 'taxonomy' ),
		) );

		$this->add_control( 'hide_empty', array(
			'label'       => __( 'Only terms with farms', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SWITCHER,
			'default'     => 'yes',
			'condition'   => array( 'source' => 'taxonomy' ),
			'description' => __( 'A province with nothing for sale in it is a link to an empty page.', 'acreage' ),
		) );

		$this->add_control( 'split', array(
			'label'   => __( 'Two columns', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::SWITCHER,
			'default' => '',
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'colour', array(
			'label'     => __( 'Text', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array(
				'{{WRAPPER}} .acreage-f__nav a' => 'color: {{VALUE}};',
			),
		) );

		$this->add_responsive_control( 'size', array(
			'label'      => __( 'Size', 'acreage' ),
			'type'       => \Elementor\Controls_Manager::SLIDER,
			'size_units' => array( 'px' ),
			'range'      => array( 'px' => array( 'min' => 11, 'max' => 22 ) ),
			'selectors'  => array(
				'{{WRAPPER}} .acreage-f__nav' => 'font-size: {{SIZE}}{{UNIT}};',
			),
		) );

		$this->end_controls_section();
	}

	/**
	 * The links, as [ label, url ] pairs, whichever source is chosen.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function links( $settings ) {
		if ( 'taxonomy' === $settings['source'] ) {
			return $this->taxonomy_links( $settings );
		}

		return $this->menu_links( $settings );
	}

	/**
	 * A menu, resolved now.
	 *
	 * wp_get_nav_menu_items() gives objects, not markup, and their ->url is
	 * computed per item type — so a "Farms" item added as a post type archive
	 * follows the archive base wherever it moves, and a page item follows the
	 * page's slug. That is the whole reason to hold a menu rather than HTML.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function menu_links( $settings ) {
		$menu_id = absint( $settings['menu'] );

		if ( ! $menu_id ) {
			return array();
		}

		$items = wp_get_nav_menu_items( $menu_id );

		if ( ! $items ) {
			return array();
		}

		$links = array();

		foreach ( $items as $item ) {
			// Sub-items have nowhere to go in a single-level footer column.
			if ( (int) $item->menu_item_parent ) {
				continue;
			}

			$links[] = array( $item->title, $item->url );
		}

		return $links;
	}

	/**
	 * Every term on one axis, pointing at the archive filtered by it.
	 *
	 * @param array $settings Widget settings.
	 * @return array
	 */
	private function taxonomy_links( $settings ) {
		$taxonomy = sanitize_key( $settings['taxonomy'] );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => 'yes' === $settings['hide_empty'],
			'number'     => max( 1, absint( $settings['limit'] ) ),
			'orderby'    => 'count',
			'order'      => 'DESC',
		) );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		$base  = $this->archive_url();
		$links = array();

		foreach ( $terms as $term ) {
			$links[] = array(
				$term->name,
				add_query_arg( $taxonomy, $term->slug, $base ),
			);
		}

		return $links;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$links    = $this->links( $settings );

		if ( ! $links ) {
			$this->editor_notice(
				'taxonomy' === $settings['source']
					? __( 'No terms to list yet.', 'acreage' )
					: __( 'Choose a menu, or build one under Appearance > Menus.', 'acreage' )
			);

			return;
		}

		printf(
			'<ul class="acreage-f__nav%s">',
			'yes' === $settings['split'] ? ' acreage-f__nav--split' : ''
		);

		foreach ( $links as $link ) {
			list( $label, $url ) = $link;

			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( $url ),
				esc_html( $label )
			);
		}

		echo '</ul>';
	}
}
