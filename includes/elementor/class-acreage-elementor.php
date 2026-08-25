<?php
/**
 * Elementor widgets for farms — built to run on Elementor FREE.
 *
 * The approved mockups call for Loop Grid, the Form widget and Taxonomy Filter.
 * All three are Elementor Pro. Elementor's *widget API*, however, is free: a
 * plugin may register its own widgets and they appear in the free editor like
 * any other. So we ship the widgets the design needs instead of asking the
 * client — or anyone who buys this template — to pay $59 a year.
 *
 * What we cannot replace is Theme Builder, which is what places a template on an
 * archive or a single post. The theme solves that separately by rendering an
 * assigned Elementor page in those positions.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Elementor {

	const CATEGORY = 'acreage-farms';

	/** Minimum Elementor version we rely on. */
	const MIN_ELEMENTOR = '3.5.0';

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ) );
	}

	public function add_category( $manager ) {
		$manager->add_category( self::CATEGORY, array(
			'title' => __( 'Farms', 'acreage' ),
			'icon'  => 'eicon-nerd',
		) );
	}

	public function styles() {
		wp_enqueue_style(
			'acreage-listings-widgets',
			ACREAGE_CORE_URL . 'assets/css/widgets.css',
			array(),
			ACREAGE_CORE_VERSION
		);

		/*
		 * The grid script drives the category tabs and Load more. It is registered
		 * rather than enqueued outright and only printed when a widget on the page
		 * actually asks for it — most pages have no grid, and they should not pay
		 * for one. The Farm Grid widget calls wp_enqueue_script() at render time.
		 */
		wp_register_script(
			'acreage-nav',
			ACREAGE_CORE_URL . 'assets/js/nav.js',
			array(),
			ACREAGE_CORE_VERSION,
			true
		);

		wp_register_script(
			'acreage-grid',
			ACREAGE_CORE_URL . 'assets/js/grid.js',
			array(),
			ACREAGE_CORE_VERSION,
			true
		);

		/*
		 * The live filter. Enqueued by the Farm Filters panel, which is the only
		 * thing that can drive it — and which is on one page of the site.
		 */
		wp_register_script(
			'acreage-filters',
			ACREAGE_CORE_URL . 'assets/js/filters.js',
			array(),
			ACREAGE_CORE_VERSION,
			true
		);
	}

	public function register_widgets( $manager ) {
		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '<' ) ) {
			return;
		}

		$dir = ACREAGE_CORE_DIR . 'includes/elementor/widgets/';

		require_once $dir . 'class-acreage-widget-base.php';

		$widgets = array(
			'nav'            => 'Acreage_Widget_Nav',
			'farm-grid'      => 'Acreage_Widget_Farm_Grid',
			'farm-search'    => 'Acreage_Widget_Farm_Search',
			'farm-filters'   => 'Acreage_Widget_Farm_Filters',
			'province-tiles' => 'Acreage_Widget_Province_Tiles',
			'category-cards' => 'Acreage_Widget_Category_Cards',
			'farm-details'   => 'Acreage_Widget_Farm_Details',
			'enquiry-form'   => 'Acreage_Widget_Enquiry_Form',
		);

		foreach ( $widgets as $file => $class ) {
			require_once $dir . 'class-acreage-widget-' . $file . '.php';

			if ( class_exists( $class ) ) {
				$manager->register( new $class() );
			}
		}
	}
}
