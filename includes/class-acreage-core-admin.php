<?php
/**
 * Admin conveniences: a useful farms list, and the same self-diagnosing update
 * notice the theme carries, so a failed update lookup says why instead of
 * silently doing nothing.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Admin {

	public function __construct() {
		add_filter( 'manage_listing_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_listing_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-listing_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'admin_notices', array( $this, 'update_notice' ) );

		// Making the next farm easy to add.
		add_filter( 'post_row_actions', array( $this, 'duplicate_link' ), 10, 2 );
		add_action( 'admin_post_acreage_duplicate', array( $this, 'duplicate' ) );
		add_action( 'restrict_manage_posts', array( $this, 'list_filters' ) );
		add_action( 'add_meta_boxes', array( $this, 'checklist_box' ) );
	}

	public function columns( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'title' === $key ) {
				$out['acreage_hectares'] = __( 'Extent', 'acreage' );
				$out['acreage_price']    = __( 'Price', 'acreage' );
			}
		}

		return $out;
	}

	public function column( $column, $post_id ) {
		if ( 'acreage_hectares' === $column ) {
			$hectares = (float) get_post_meta( $post_id, 'acreage_hectares', true );
			echo $hectares > 0
				? esc_html( number_format_i18n( $hectares ) . ' ha' )
				: '<span aria-hidden="true">—</span>';
			return;
		}

		if ( 'acreage_price' === $column ) {
			$price = (float) get_post_meta( $post_id, 'acreage_price', true );
			echo $price > 0
				? esc_html( 'R' . number_format_i18n( $price ) )
				: esc_html__( 'On application', 'acreage' );
		}
	}

	public function sortable( $columns ) {
		$columns['acreage_hectares'] = 'acreage_hectares';
		$columns['acreage_price']    = 'acreage_price';

		return $columns;
	}

	/** Diagnostic notice on the Plugins screen. */
	public function update_notice() {
		$screen = get_current_screen();

		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$updater = isset( $GLOBALS['acreage_core_updater'] ) ? $GLOBALS['acreage_core_updater'] : null;

		if ( ! $updater ) {
			return;
		}

		$url   = wp_nonce_url( admin_url( 'plugins.php?acreage-listings-check=1' ), 'acreage-listings-check' );
		$lines = array(
			sprintf(
				/* translators: 1: version, 2: plugin folder name. */
				__( 'Installed: <strong>v%1$s</strong> in folder <code>%2$s</code>', 'acreage' ),
				esc_html( ACREAGE_CORE_VERSION ),
				esc_html( $updater->slug() )
			),
		);

		$class   = 'notice-info';
		$release = $updater->get_release();

		if ( isset( $release['error'] ) ) {
			$class   = 'notice-warning';
			$lines[] = sprintf(
				/* translators: 1: owner/repo, 2: reason. */
				__( 'GitHub <code>%1$s</code>: <strong>%2$s</strong>', 'acreage' ),
				esc_html( ACREAGE_CORE_GITHUB_REPO ),
				esc_html( $release['error'] )
			);
		} else {
			$newer   = version_compare( $release['version'], ACREAGE_CORE_VERSION, '>' );
			$lines[] = sprintf(
				/* translators: 1: owner/repo, 2: version, 3: state. */
				__( 'GitHub <code>%1$s</code>: latest release <strong>v%2$s</strong> — %3$s', 'acreage' ),
				esc_html( ACREAGE_CORE_GITHUB_REPO ),
				esc_html( $release['version'] ),
				$newer ? esc_html__( 'update available', 'acreage' ) : esc_html__( 'up to date', 'acreage' )
			);

			if ( empty( $release['asset'] ) ) {
				$class   = 'notice-warning';
				$lines[] = esc_html__( 'That release has no attached .zip — falling back to the GitHub source archive.', 'acreage' );
			}
		}

		printf(
			'<div class="notice %1$s"><p><strong>%2$s</strong></p><p>%3$s</p><p><a class="button" href="%4$s">%5$s</a></p></div>',
			esc_attr( $class ),
			esc_html__( 'Acreage Core updater', 'acreage' ),
			wp_kses( implode( '<br>', $lines ), array( 'strong' => array(), 'code' => array(), 'br' => array() ) ),
			esc_url( $url ),
			esc_html__( 'Check for updates now', 'acreage' )
		);
	}
	/* ------------------------------------------------- duplicate a farm */

	/**
	 * Most farms are close relatives of one already on the site — same province,
	 * same category, same boilerplate on land claims. Copying one and editing the
	 * differences is far quicker than starting from an empty screen.
	 */
	public function duplicate_link( $actions, $post ) {
		if ( Acreage_Core_Post_Types::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_posts' ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=acreage_duplicate&post=' . $post->ID ),
			'acreage_duplicate_' . $post->ID
		);

		$actions['acreage_duplicate'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Duplicate', 'acreage' )
		);

		return $actions;
	}

	public function duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( 'acreage_duplicate_' . $post_id );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate farms.', 'acreage' ) );
		}

		$original = get_post( $post_id );

		if ( ! $original || Acreage_Core_Post_Types::POST_TYPE !== $original->post_type ) {
			wp_die( esc_html__( 'That farm could not be found.', 'acreage' ) );
		}

		$copy_id = wp_insert_post( array(
			'post_type'    => Acreage_Core_Post_Types::POST_TYPE,
			/* translators: %s: the original farm name. */
			'post_title'   => sprintf( __( '%s (copy)', 'acreage' ), $original->post_title ),
			'post_content' => $original->post_content,
			'post_excerpt' => $original->post_excerpt,
			// Always a draft: a half-edited copy must never appear on the site.
			'post_status'  => 'draft',
		), true );

		if ( is_wp_error( $copy_id ) || ! $copy_id ) {
			wp_die( esc_html__( 'The farm could not be duplicated.', 'acreage' ) );
		}

		foreach ( get_object_taxonomies( Acreage_Core_Post_Types::POST_TYPE ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $copy_id, $terms, $taxonomy );
			}
		}

		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( '_edit_lock' === $key || '_edit_last' === $key ) {
				continue;
			}
			foreach ( $values as $value ) {
				update_post_meta( $copy_id, $key, maybe_unserialize( $value ) );
			}
		}

		wp_safe_redirect( get_edit_post_link( $copy_id, 'raw' ) );
		exit;
	}

	/* ----------------------------------------------------- list filters */

	/** Province, category and status dropdowns above the farms list. */
	public function list_filters() {
		global $typenow;

		if ( Acreage_Core_Post_Types::POST_TYPE !== $typenow ) {
			return;
		}

		$filters = array(
			'listing_category' => __( 'All kinds', 'acreage' ),
			'province'         => __( 'All provinces', 'acreage' ),
			'status'           => __( 'All statuses', 'acreage' ),
		);

		foreach ( $filters as $taxonomy => $any ) {
			$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

			if ( ! $terms || is_wp_error( $terms ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin list filtering.
			$current = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';

			printf( '<select name="%s">', esc_attr( $taxonomy ) );
			printf( '<option value="">%s</option>', esc_html( $any ) );

			foreach ( $terms as $term ) {
				printf(
					'<option value="%1$s" %2$s>%3$s (%4$d)</option>',
					esc_attr( $term->slug ),
					selected( $current, $term->slug, false ),
					esc_html( $term->name ),
					(int) $term->count
				);
			}

			echo '</select>';
		}
	}

	/* ------------------------------------------------------- checklist */

	public function checklist_box() {
		add_meta_box(
			'acreage-listing-checklist',
			__( 'Before this goes live', 'acreage' ),
			array( $this, 'render_checklist' ),
			Acreage_Core_Post_Types::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * What is still missing, in plain words. Nothing here blocks publishing —
	 * the client knows his own inventory better than a checklist does.
	 */
	public function render_checklist( $post ) {
		$gallery = Acreage_Core_Query::gallery_ids( $post->ID );

		$checks = array(
			array( __( 'Main photograph', 'acreage' ), has_post_thumbnail( $post ) ),
			array( __( 'Price', 'acreage' ), (float) get_post_meta( $post->ID, 'acreage_price', true ) > 0 ),
			array( __( 'Size in hectares', 'acreage' ), (float) get_post_meta( $post->ID, 'acreage_hectares', true ) > 0 ),
			array( __( 'Province', 'acreage' ), (bool) wp_get_object_terms( $post->ID, 'province', array( 'fields' => 'ids' ) ) ),
			array( __( 'Game or cattle', 'acreage' ), (bool) wp_get_object_terms( $post->ID, 'listing_category', array( 'fields' => 'ids' ) ) ),
			array( __( 'Description', 'acreage' ), '' !== trim( wp_strip_all_tags( $post->post_content ) ) ),
			array( __( 'Gallery photographs', 'acreage' ), count( $gallery ) > 0 ),
		);

		$missing = 0;

		echo '<ul class="acreage-checklist">';
		foreach ( $checks as $check ) {
			list( $label, $done ) = $check;
			if ( ! $done ) {
				$missing++;
			}
			printf(
				'<li class="acreage-checklist__item %1$s"><span aria-hidden="true">%2$s</span> %3$s</li>',
				$done ? 'is-done' : 'is-missing',
				$done ? '&#10003;' : '&middot;',
				esc_html( $label )
			);
		}
		echo '</ul>';

		if ( $missing ) {
			printf(
				'<p class="acreage-checklist__note">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of missing items. */
						_n( '%d item still to add. You can publish anyway.', '%d items still to add. You can publish anyway.', $missing, 'acreage' ),
						$missing
					)
				)
			);
		} else {
			printf( '<p class="acreage-checklist__note acreage-checklist__note--ok">%s</p>', esc_html__( 'Everything is filled in.', 'acreage' ) );
		}
	}
}