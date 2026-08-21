<?php
/**
 * The listing post type and its seven taxonomies.
 *
 * Every filter axis is a taxonomy with editable terms rather than a value
 * hard-coded in PHP, so the client can re-cut the price bands himself when he
 * sees his own distribution — no developer involvement, which is exactly the
 * problem with the current site's "Above R10 Mill" band.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Post_Types {

	const POST_TYPE = 'listing';

	/** Option holding the URL segment listings live under. */
	const SLUG_OPTION = 'acreage_archive_slug';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 6 );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'classic_editor' ), 10, 2 );
	}

	/**
	 * Farms use the classic editor.
	 *
	 * The brief asks that adding a farm be one labelled form. The block editor
	 * pushes every meta box into a collapsed "Meta Boxes" drawer at the foot of
	 * the screen, so the price, the sections and the gallery end up hidden behind
	 * a click — which is precisely the experience we are being paid to avoid.
	 *
	 * This has no bearing on the REST API: listings stay in REST, so the data can
	 * still be read out cleanly. It only changes which editor the admin loads.
	 */
	public function classic_editor( $use_block_editor, $post_type ) {
		if ( self::POST_TYPE === $post_type ) {
			return false;
		}

		return $use_block_editor;
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Farms', 'acreage' ),
			'singular_name'         => __( 'Farm', 'acreage' ),
			'add_new'               => __( 'Add farm', 'acreage' ),
			'add_new_item'          => __( 'Add a farm', 'acreage' ),
			'edit_item'             => __( 'Edit farm', 'acreage' ),
			'new_item'              => __( 'New farm', 'acreage' ),
			'view_item'             => __( 'View farm', 'acreage' ),
			'view_items'            => __( 'View farms', 'acreage' ),
			'search_items'          => __( 'Search farms', 'acreage' ),
			'not_found'             => __( 'No farms yet.', 'acreage' ),
			'not_found_in_trash'    => __( 'No farms in the bin.', 'acreage' ),
			'all_items'             => __( 'All farms', 'acreage' ),
			'menu_name'             => __( 'Farms', 'acreage' ),
			'featured_image'        => __( 'Main photograph', 'acreage' ),
			'set_featured_image'    => __( 'Set main photograph', 'acreage' ),
			'remove_featured_image' => __( 'Remove main photograph', 'acreage' ),
		);

		$slug = self::archive_slug();

		register_post_type( self::POST_TYPE, array(
			'labels'        => $labels,
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-location-alt',
			'menu_position' => 5,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'rewrite'       => array( 'slug' => $slug, 'with_front' => false ),
			'show_in_rest'  => true, // The current site's fatal gap: no clean way to get the data out.
			'rest_base'     => $slug,
		) );
	}

	/**
	 * The URL segment listings live under.
	 *
	 * WHY THIS IS A SETTING AND NOT A CONSTANT
	 *
	 * Every listing URL contains it, so it is not a cosmetic choice: change it on
	 * a live site and every indexed URL 404s until redirects are in place. Africa
	 * Game Farms has years of search history on /game-farms/, so their install
	 * sets this to "game-farms" and keeps every one of those URLs. A new customer
	 * selling plots rather than farms gets the neutral default instead.
	 *
	 * After changing it, permalinks MUST be flushed — Settings > Permalinks, or
	 * deactivate and reactivate the plugin.
	 *
	 * @return string
	 */
	public static function archive_slug() {
		$slug = get_option( self::SLUG_OPTION, '' );

		if ( ! $slug ) {
			$slug = 'properties';
		}

		/**
		 * Filter the listing URL base.
		 *
		 * Lets a site pin the slug in code — useful on a staging copy that must
		 * match production exactly.
		 *
		 * @param string $slug URL segment.
		 */
		return sanitize_title( apply_filters( 'acreage_archive_slug', $slug ) );
	}

	/** The seven filter axes. */
	public function taxonomy_definitions() {
		return array(
			'listing_category' => array(
				'plural'       => __( 'Categories', 'acreage' ),
				'single'       => __( 'Category', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'category-of-farm',
			),
			'province'         => array(
				'plural'       => __( 'Provinces', 'acreage' ),
				'single'       => __( 'Province', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'province',
			),
			'region'           => array(
				'plural'       => __( 'Regions', 'acreage' ),
				'single'       => __( 'Region', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'region',
			),
			'size_band'        => array(
				'plural'       => __( 'Size bands', 'acreage' ),
				'single'       => __( 'Size band', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'size',
			),
			'price_band'       => array(
				'plural'       => __( 'Price bands', 'acreage' ),
				'single'       => __( 'Price band', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'price',
			),
			'status'           => array(
				'plural'       => __( 'Statuses', 'acreage' ),
				'single'       => __( 'Status', 'acreage' ),
				'hierarchical' => true,
				'slug'         => 'status',
			),
			'species'          => array(
				'plural'       => __( 'Species', 'acreage' ),
				'single'       => __( 'Species', 'acreage' ),
				'hierarchical' => false,
				'slug'         => 'species',
			),
		);
	}

	public function register_taxonomies() {
		foreach ( $this->taxonomy_definitions() as $taxonomy => $args ) {
			register_taxonomy( $taxonomy, self::POST_TYPE, array(
				'labels'            => array(
					'name'          => $args['plural'],
					'singular_name' => $args['single'],
					'menu_name'     => $args['plural'],
					'all_items'     => $args['plural'],
					'edit_item'     => sprintf( /* translators: %s: taxonomy name */ __( 'Edit %s', 'acreage' ), $args['single'] ),
					'add_new_item'  => sprintf( /* translators: %s: taxonomy name */ __( 'Add %s', 'acreage' ), $args['single'] ),
					'search_items'  => sprintf( /* translators: %s: taxonomy name */ __( 'Search %s', 'acreage' ), $args['plural'] ),
				),
				'hierarchical'      => $args['hierarchical'],
				'public'            => true,
				'show_admin_column' => in_array( $taxonomy, array( 'listing_category', 'province', 'status' ), true ),
				'show_in_rest'      => true,
				// 'status' collides with a property on the REST Posts Controller, so
				// every taxonomy gets an explicit, prefixed REST base rather than
				// leaving one of them to trip over core.
				'rest_base'         => 'farm-' . str_replace( '_', '-', $taxonomy ),
				'rewrite'           => array( 'slug' => $args['slug'], 'with_front' => false ),
			) );
		}
	}

	/**
	 * Seed the terms on activation.
	 *
	 * Note what is NOT here: "Western Cape" as a category. On the current site it
	 * sits in listing_category beside Game and Cattle, which is the wrong axis and
	 * lets a Western Cape farm drop out of the Game Farms archive entirely. Here
	 * category has exactly two terms and Western Cape is a province like any other.
	 */
	public function seed_terms() {
		$seed = array(
			'listing_category' => array( 'Game farms', 'Cattle farms' ),
			'province'         => array(
				'Limpopo', 'Mpumalanga', 'North West', 'Gauteng', 'Free State',
				'KwaZulu-Natal', 'Eastern Cape', 'Western Cape', 'Northern Cape',
				'Namibia', 'Botswana',
			),
			'size_band'        => array(
				'Up to 100 ha', '100 – 500 ha', '500 – 1 000 ha',
				'1 000 – 2 500 ha', 'Over 2 500 ha',
			),
			'price_band'       => array(
				'Up to R10M', 'R10M – R20M', 'R20M – R35M', 'Over R35M',
			),
			'status'           => array( 'New listing', 'Sold', 'Off market' ),
		);

		foreach ( $seed as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! term_exists( $term, $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}
	}

	/**
	 * Hectare thresholds that map a farm onto a size band.
	 *
	 * @return array[] [ max hectares (null = no ceiling), term name ]
	 */
	public static function size_bands() {
		return array(
			array( 100, 'Up to 100 ha' ),
			array( 500, '100 – 500 ha' ),
			array( 1000, '500 – 1 000 ha' ),
			array( 2500, '1 000 – 2 500 ha' ),
			array( null, 'Over 2 500 ha' ),
		);
	}

	/** Rand thresholds that map a price onto a price band. */
	public static function price_bands() {
		return array(
			array( 10000000, 'Up to R10M' ),
			array( 20000000, 'R10M – R20M' ),
			array( 35000000, 'R20M – R35M' ),
			array( null, 'Over R35M' ),
		);
	}
}
