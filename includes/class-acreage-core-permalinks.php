<?php
/**
 * The farms archive base, on the screen WordPress already uses for this.
 *
 * WHY HERE AND NOT ON A SETTINGS PAGE OF OUR OWN
 *
 * Settings > Permalinks is where WordPress keeps the tag and category bases, and
 * where WooCommerce keeps the product base. A customer looking for "the URL my
 * farms live under" looks there first, so that is where it goes — one screen, no
 * plugin menu to discover.
 *
 * It also solves the problem that makes changing a URL base dangerous. Rewrite
 * rules are only rebuilt when something asks for it, so a base changed anywhere
 * else leaves the site serving 404s until someone happens to press Save on this
 * exact screen. Saving on this screen IS that press: options-permalink.php calls
 * flush_rewrite_rules() when it is done, so the rules and the setting can never
 * disagree.
 *
 * The one ordering trap is that the post type was registered at init, with the
 * previous base, several hooks before this runs. Flushing at that point would
 * write rules describing the base the customer just replaced. So the type is
 * re-registered here, after the option is saved and before core flushes.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Permalinks {

	/** Carries a rejection across the redirect options-permalink.php does. */
	const ERROR_TRANSIENT = 'acreage_permalink_error';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_field' ) );
		add_action( 'admin_init', array( $this, 'save' ), 5 );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function register_field() {
		add_settings_section(
			'acreage-permalinks',
			__( 'Farm permalinks', 'acreage' ),
			array( $this, 'section' ),
			'permalink'
		);

		add_settings_field(
			Acreage_Core_Post_Types::SLUG_OPTION,
			__( 'Farms archive base', 'acreage' ),
			array( $this, 'field' ),
			'permalink',
			'acreage-permalinks'
		);
	}

	public function section() {
		echo '<p>' . esc_html__( 'The URL segment every farm sits under. Changing it changes the address of the archive and of every farm on it, so anything already linking to the old address will need updating.', 'acreage' ) . '</p>';
	}

	public function field() {
		$stored    = (string) get_option( Acreage_Core_Post_Types::SLUG_OPTION, '' );
		$effective = Acreage_Core_Post_Types::archive_slug();

		// A site can pin the base in code with the acreage_archive_slug filter.
		// When it has, the field must say so rather than accept a value that
		// will be quietly overruled the moment the page reloads.
		$pinned = $effective !== sanitize_title( '' !== $stored ? $stored : 'properties' );

		printf(
			'<input name="%1$s" id="%1$s" type="text" class="regular-text code" value="%2$s" placeholder="%3$s"%4$s>',
			esc_attr( Acreage_Core_Post_Types::SLUG_OPTION ),
			esc_attr( $pinned ? $effective : $stored ),
			esc_attr__( 'properties', 'acreage' ),
			$pinned ? ' disabled' : ''
		);

		echo '<p class="description">';

		if ( $pinned ) {
			printf(
				/* translators: %s: the filter name. */
				esc_html__( 'Set in code through the %s filter, so it cannot be changed here.', 'acreage' ),
				'<code>acreage_archive_slug</code>'
			);
		} else {
			printf(
				/* translators: %s: an example listing URL. */
				esc_html__( 'Farms will read %s. Leave empty for the default.', 'acreage' ),
				'<code>' . esc_html( home_url( '/' . $effective . '/kalahari-duine/' ) ) . '</code>'
			);
		}

		echo '</p>';
	}

	/**
	 * Save the base, then re-register so core flushes the right rules.
	 */
	public function save() {
		global $pagenow;

		if ( 'options-permalink.php' !== $pagenow || ! isset( $_POST[ Acreage_Core_Post_Types::SLUG_OPTION ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'update-permalink' );

		$slug = sanitize_title( wp_unslash( $_POST[ Acreage_Core_Post_Types::SLUG_OPTION ] ) );

		if ( $slug === (string) get_option( Acreage_Core_Post_Types::SLUG_OPTION, '' ) ) {
			return;
		}

		if ( '' !== $slug && $this->collides_with_page( $slug ) ) {
			set_transient(
				self::ERROR_TRANSIENT,
				sprintf(
					/* translators: %s: the URL base that was rejected. */
					__( 'The farms archive base was not changed: a page already uses “%s”. An archive and a page cannot share a URL — the archive wins and the page becomes unreachable. Rename the page first, or choose another base.', 'acreage' ),
					$slug
				),
				60
			);

			return;
		}

		update_option( Acreage_Core_Post_Types::SLUG_OPTION, $slug );

		// init has already registered the type under the previous base. Register
		// it again so the rules core is about to write describe the new one.
		$types = new Acreage_Core_Post_Types();
		$types->register_post_type();
		$types->register_taxonomies();
	}

	/**
	 * Refuse a base that would swallow an existing page.
	 *
	 * A post type archive and a page can claim the same URL, and the archive
	 * wins. The page is not deleted, warned about or redirected — it simply
	 * stops being reachable at its own permalink, which the customer discovers
	 * weeks later when someone asks why the About page 404s.
	 *
	 * Layout slot pages are the deliberate exception. They are fragments of
	 * other pages rather than destinations — noindexed, kept out of every
	 * listing — so an archive standing on one costs nothing.
	 *
	 * @param string $slug Proposed base.
	 * @return bool
	 */
	private function collides_with_page( $slug ) {
		$page = get_page_by_path( $slug );

		if ( ! $page || 'publish' !== $page->post_status ) {
			return false;
		}

		if ( class_exists( 'Acreage_Elementor_Layout' ) ) {
			$slots = array_map( 'absint', array_values( Acreage_Elementor_Layout::settings() ) );

			if ( in_array( (int) $page->ID, $slots, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Say why the base was not saved.
	 *
	 * options-permalink.php redirects after saving, so the complaint has to
	 * survive one request. A transient does; a notice printed here would be
	 * thrown away before anyone saw it.
	 */
	public function notice() {
		$message = get_transient( self::ERROR_TRANSIENT );

		if ( ! $message ) {
			return;
		}

		delete_transient( self::ERROR_TRANSIENT );

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}
}
