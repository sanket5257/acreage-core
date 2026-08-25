<?php
/**
 * The add-a-farm form.
 *
 * The brief disqualifies any solution where adding a farm means dragging blocks
 * around a page-builder canvas. So this is one screen of labelled fields, and
 * nothing else. Written against core APIs rather than a field framework, so the
 * client has no second plugin to keep updated.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Fields {

	const NONCE = 'acreage_core_fields';

	/** Plain text and number fields: key => [ label, type, hint ]. */
	public function simple_fields() {
		return array(
			'acreage_price'    => array(
				__( 'Price (Rand)', 'acreage' ),
				'number',
				__( 'Figures only, no spaces or R. "Excludes VAT if applicable" is added automatically.', 'acreage' ),
			),
			'acreage_hectares' => array(
				__( 'Size (hectares)', 'acreage' ),
				'number',
				__( 'The size band is worked out from this, so you never set it twice.', 'acreage' ),
			),
			'acreage_youtube'  => array(
				__( 'YouTube link', 'acreage' ),
				'url',
				__( 'Paste the normal watch link. The video embeds itself.', 'acreage' ),
			),
			'acreage_map'      => array(
				__( 'Map location', 'acreage' ),
				'text',
				__( 'A town, a district, or co-ordinates as "-24.1234, 28.5678". Leave empty and no map is shown.', 'acreage' ),
			),
		);
	}

	/**
	 * How closely the map is drawn.
	 *
	 * DEFAULTS TO THE DISTRICT, NOT THE FARM GATE
	 *
	 * A seller will often not have the exact position published, and the comp
	 * says as much on the page — "exact co-ordinates on request". So the
	 * starting zoom shows the area a buyer needs in order to place the farm in
	 * the country, and going closer than that is a decision the client makes
	 * farm by farm rather than the default doing it for them.
	 */
	const MAP_ZOOM_DEFAULT = 9;
	const MAP_ZOOM_MIN     = 3;
	const MAP_ZOOM_MAX     = 18;

	/** The four labelled sections from the current site. */
	public function section_fields() {
		return array(
			'acreage_improvements' => array(
				__( 'Improvements', 'acreage' ),
				__( 'Houses, sheds, staff quarters, boreholes, fencing.', 'acreage' ),
				'both',
			),
			'acreage_wildlife'     => array(
				__( 'Wildlife &amp; vegetation', 'acreage' ),
				__( 'Species on the farm and the veld type. Hidden on cattle farms.', 'acreage' ),
				'game',
			),
			'acreage_land_claims'  => array(
				__( 'Land claims', 'acreage' ),
				__( 'State the position plainly, even when there is nothing to report.', 'acreage' ),
				'both',
			),
		);
	}

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_boxes' ) );
		add_action( 'save_post_listing', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function assets( $hook ) {
		global $post;

		$is_quick  = isset( $_GET['page'] ) && Acreage_Core_Quick_Add::SLUG === $_GET['page'];
		$is_editor = in_array( $hook, array( 'post.php', 'post-new.php' ), true )
			&& $post && Acreage_Core_Post_Types::POST_TYPE === $post->post_type;

		if ( ! $is_quick && ! $is_editor ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'acreage-listings-admin', ACREAGE_CORE_URL . 'assets/css/admin.css', array(), ACREAGE_CORE_VERSION );
		wp_enqueue_script( 'acreage-listings-admin', ACREAGE_CORE_URL . 'assets/js/admin.js', array( 'jquery' ), ACREAGE_CORE_VERSION, true );

		wp_localize_script( 'acreage-listings-admin', 'acreageListings', array(
			'chooseTitle'  => __( 'Choose photographs', 'acreage' ),
			'chooseButton' => __( 'Use these photographs', 'acreage' ),
			'removeLabel'  => __( 'Remove', 'acreage' ),
		) );
	}

	public function add_boxes() {
		add_meta_box(
			'acreage-listing-facts',
			__( 'Farm details', 'acreage' ),
			array( $this, 'render_facts' ),
			Acreage_Core_Post_Types::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'acreage-listing-sections',
			__( 'Sections', 'acreage' ),
			array( $this, 'render_sections' ),
			Acreage_Core_Post_Types::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'acreage-listing-gallery',
			__( 'Photograph gallery', 'acreage' ),
			array( $this, 'render_gallery' ),
			Acreage_Core_Post_Types::POST_TYPE,
			'normal',
			'default'
		);
	}

	public function render_facts( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="acreage-fields">
			<?php foreach ( $this->simple_fields() as $key => $field ) : ?>
				<?php list( $label, $type, $hint ) = $field; ?>
				<p class="acreage-field">
					<label class="acreage-field__label" for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<input
						class="acreage-field__input"
						type="<?php echo esc_attr( $type ); ?>"
						id="<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $key ); ?>"
						value="<?php echo esc_attr( get_post_meta( $post->ID, $key, true ) ); ?>"
						<?php echo 'number' === $type ? 'min="0" step="1"' : ''; ?>>
					<span class="acreage-field__hint"><?php echo esc_html( $hint ); ?></span>
				</p>
			<?php endforeach; ?>

			<?php
			/*
			 * The two that go with the map location above it. Kept out of
			 * simple_fields() because one is a select and the other a textarea,
			 * and bending that loop to cover three input types would cost more
			 * than writing them out.
			 */
			$acreage_zoom = (int) get_post_meta( $post->ID, 'acreage_map_zoom', true );
			$acreage_zoom = $acreage_zoom ? $acreage_zoom : self::MAP_ZOOM_DEFAULT;
			?>
			<p class="acreage-field">
				<label class="acreage-field__label" for="acreage_map_zoom"><?php esc_html_e( 'How close the map sits', 'acreage' ); ?></label>
				<input
					class="acreage-field__input"
					type="number"
					id="acreage_map_zoom"
					name="acreage_map_zoom"
					min="<?php echo esc_attr( self::MAP_ZOOM_MIN ); ?>"
					max="<?php echo esc_attr( self::MAP_ZOOM_MAX ); ?>"
					step="1"
					value="<?php echo esc_attr( $acreage_zoom ); ?>">
				<span class="acreage-field__hint">
					<?php
					printf(
						/* translators: %d: the default zoom level. */
						esc_html__( '%d shows the district, which is the usual choice. Higher goes closer; 15 and above shows individual roads and buildings.', 'acreage' ),
						(int) self::MAP_ZOOM_DEFAULT
					);
					?>
				</span>
			</p>

			<p class="acreage-field">
				<label class="acreage-field__label" for="acreage_directions"><?php esc_html_e( 'Getting there', 'acreage' ); ?></label>
				<textarea
					class="acreage-field__input"
					id="acreage_directions"
					name="acreage_directions"
					rows="2"><?php echo esc_textarea( get_post_meta( $post->ID, 'acreage_directions', true ) ); ?></textarea>
				<span class="acreage-field__hint">
					<?php esc_html_e( 'A line above the map, in your own words — "Roughly 45 minutes from Vaalwater, three and a half hours from Johannesburg. Exact co-ordinates on request."', 'acreage' ); ?>
				</span>
			</p>

			<p class="acreage-field acreage-field--check">
				<label for="acreage_big_five">
					<input
						type="checkbox"
						id="acreage_big_five"
						name="acreage_big_five"
						value="1"
						<?php checked( '1', get_post_meta( $post->ID, 'acreage_big_five', true ) ); ?>>
					<strong><?php esc_html_e( 'Big Five property', 'acreage' ); ?></strong>
				</label>
				<span class="acreage-field__hint">
					<?php esc_html_e( 'Its own flag, not a status — a farm can be a new listing and a Big Five property at the same time.', 'acreage' ); ?>
				</span>
			</p>

			<p class="acreage-field acreage-field--check">
				<label for="acreage_featured">
					<input
						type="checkbox"
						id="acreage_featured"
						name="acreage_featured"
						value="1"
						<?php checked( '1', get_post_meta( $post->ID, 'acreage_featured', true ) ); ?>>
					<strong><?php esc_html_e( 'Feature this farm', 'acreage' ); ?></strong>
				</label>
				<span class="acreage-field__hint">
					<?php esc_html_e( 'Featured farms fill the highlighted band on the homepage. Tick a handful, not everything — the point of the band is that it is a short list.', 'acreage' ); ?>
				</span>
			</p>
		</div>
		<?php
	}

	public function render_sections( $post ) {
		?>
		<p class="acreage-note">
			<?php esc_html_e( 'The main editor above is the Description. These three follow it on the page.', 'acreage' ); ?>
		</p>

		<?php foreach ( $this->section_fields() as $key => $section ) : ?>
			<?php list( $label, $hint, $applies ) = $section; ?>
			<div class="acreage-section" data-applies="<?php echo esc_attr( $applies ); ?>">
				<h3 class="acreage-section__title"><?php echo esc_html( wp_specialchars_decode( $label ) ); ?></h3>
				<p class="acreage-field__hint"><?php echo esc_html( $hint ); ?></p>
				<?php
				wp_editor(
					get_post_meta( $post->ID, $key, true ),
					$key,
					array(
						'textarea_name' => $key,
						'textarea_rows' => 8,
						'media_buttons' => false,
						'teeny'         => true,
					)
				);
				?>
			</div>
		<?php endforeach; ?>
		<?php
	}

	public function render_gallery( $post ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) get_post_meta( $post->ID, 'acreage_gallery', true ) ) ) );
		?>
		<p class="acreage-field__hint">
			<?php esc_html_e( 'Drag to reorder. The first photograph is not the main one — set that as the featured image.', 'acreage' ); ?>
		</p>

		<ul class="acreage-gallery" id="acreage-gallery">
			<?php foreach ( $ids as $id ) : ?>
				<?php $thumb = wp_get_attachment_image( $id, 'thumbnail' ); ?>
				<?php if ( $thumb ) : ?>
					<li class="acreage-gallery__item" data-id="<?php echo esc_attr( $id ); ?>">
						<?php echo wp_kses_post( $thumb ); ?>
						<button type="button" class="acreage-gallery__remove" aria-label="<?php esc_attr_e( 'Remove photograph', 'acreage' ); ?>">&times;</button>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>

		<input type="hidden" id="acreage_gallery" name="acreage_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
		<button type="button" class="button" id="acreage-gallery-add"><?php esc_html_e( 'Add photographs', 'acreage' ); ?></button>
		<?php
	}

	/* -------------------------------------------------------------- saving */

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Numbers and links.
		foreach ( array( 'acreage_price', 'acreage_hectares' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST[ $key ] ) ) : '';
			$this->put( $post_id, $key, $value );
		}

		$youtube = isset( $_POST['acreage_youtube'] ) ? esc_url_raw( wp_unslash( $_POST['acreage_youtube'] ) ) : '';
		$this->put( $post_id, 'acreage_youtube', $youtube );

		// Where it is. The location is free text because it is handed to a map
		// service as a search — a town, a district or a pair of co-ordinates.
		$map = isset( $_POST['acreage_map'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_map'] ) ) : '';
		$this->put( $post_id, 'acreage_map', $map );

		/*
		 * An emptied zoom box means "whatever you think", not "zoom all the way
		 * out". Clamping a 0 straight into range would answer it with 3, which
		 * is most of southern Africa in one frame.
		 */
		$zoom = isset( $_POST['acreage_map_zoom'] ) ? (int) $_POST['acreage_map_zoom'] : 0;
		$zoom = $zoom > 0 ? $zoom : self::MAP_ZOOM_DEFAULT;
		$zoom = max( self::MAP_ZOOM_MIN, min( self::MAP_ZOOM_MAX, $zoom ) );
		$this->put( $post_id, 'acreage_map_zoom', (string) $zoom );

		$directions = isset( $_POST['acreage_directions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['acreage_directions'] ) ) : '';
		$this->put( $post_id, 'acreage_directions', $directions );

		$this->put( $post_id, 'acreage_big_five', isset( $_POST['acreage_big_five'] ) ? '1' : '' );
		$this->put( $post_id, 'acreage_featured', isset( $_POST['acreage_featured'] ) ? '1' : '' );

		// Rich sections.
		foreach ( array_keys( $this->section_fields() ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? wp_kses_post( wp_unslash( $_POST[ $key ] ) ) : '';
			$this->put( $post_id, $key, $value );
		}

		// Gallery, as an ordered list of attachment IDs.
		$gallery = isset( $_POST['acreage_gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_gallery'] ) ) : '';
		$gallery = implode( ',', array_filter( array_map( 'absint', explode( ',', $gallery ) ) ) );
		$this->put( $post_id, 'acreage_gallery', $gallery );

		self::assign_bands_for( $post_id );
	}

	/** Write, or delete the row entirely when the value is empty. */
	private function put( $post_id, $key, $value ) {
		if ( '' === $value || null === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Derive the size and price bands from the figures.
	 *
	 * The client can still re-cut the band terms themselves; this only decides
	 * which existing band a farm falls into, so nobody has to keep two fields in
	 * agreement by hand.
	 */
	public static function assign_bands_for( $post_id ) {
		$hectares = (float) get_post_meta( $post_id, 'acreage_hectares', true );
		$price    = (float) get_post_meta( $post_id, 'acreage_price', true );

		self::assign_band( $post_id, 'size_band', $hectares, Acreage_Core_Post_Types::size_bands() );
		self::assign_band( $post_id, 'price_band', $price, Acreage_Core_Post_Types::price_bands() );
	}

	private static function assign_band( $post_id, $taxonomy, $value, $bands ) {
		if ( $value <= 0 ) {
			wp_set_object_terms( $post_id, array(), $taxonomy );
			return;
		}

		foreach ( $bands as $band ) {
			list( $ceiling, $name ) = $band;

			if ( null === $ceiling || $value <= $ceiling ) {
				$term = get_term_by( 'name', $name, $taxonomy );

				// The client may have renamed or re-cut the bands. If the expected
				// term is gone, leave whatever they chose alone rather than
				// recreating a band they deliberately deleted.
				if ( $term ) {
					wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy );
				}
				return;
			}
		}
	}
}
