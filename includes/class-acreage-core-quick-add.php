<?php
/**
 * Quick add — one screen, the eight things a farm cannot go live without.
 *
 * The full editor is right for writing up a farm properly. It is the wrong tool
 * for getting sixty of them onto the site, where the job is repetitive and the
 * person doing it wants to type, tab, save, repeat. This screen is that: no
 * meta boxes to scroll past, no editor to load, and "Save and add another"
 * keeps the province and category you just used.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Quick_Add {

	const SLUG  = 'acreage-quick-add';
	const NONCE = 'acreage_quick_add';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_post_acreage_quick_add', array( $this, 'handle' ) );
	}

	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . Acreage_Core_Post_Types::POST_TYPE,
			__( 'Quick add a farm', 'acreage' ),
			__( 'Quick add', 'acreage' ),
			'edit_posts',
			self::SLUG,
			array( $this, 'render' ),
			1
		);
	}

	/** Terms of a taxonomy as id => name. */
	private function terms( $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		$out   = array();

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$out[ $term->term_id ] = $term->name;
			}
		}

		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to add farms.', 'acreage' ) );
		}

		// "Save and add another" carries these forward — the next farm is usually
		// in the same province, and retyping it sixty times is the actual chore.
		$keep_province = isset( $_GET['province'] ) ? absint( $_GET['province'] ) : 0;
		$keep_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		$added         = isset( $_GET['added'] ) ? absint( $_GET['added'] ) : 0;
		$problem       = isset( $_GET['problem'] ) ? sanitize_key( $_GET['problem'] ) : '';
		?>
		<div class="wrap acreage-quick">
			<h1><?php esc_html_e( 'Quick add a farm', 'acreage' ); ?></h1>

			<?php if ( $added ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %s: farm name, linked to its editor. */
							esc_html__( 'Added %s.', 'acreage' ),
							'<a href="' . esc_url( get_edit_post_link( $added ) ) . '"><strong>' . esc_html( get_the_title( $added ) ) . '</strong></a>'
						);
						?>
						<?php esc_html_e( 'Open it to add the gallery, the four sections and the video.', 'acreage' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( 'no-title' === $problem ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'A farm needs a name. Nothing was saved.', 'acreage' ); ?></p></div>
			<?php endif; ?>

			<p class="acreage-quick__lede">
				<?php esc_html_e( 'Enough to get a farm on the site. Everything else can follow in the full editor.', 'acreage' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acreage-quick__form">
				<input type="hidden" name="action" value="acreage_quick_add">
				<?php wp_nonce_field( self::NONCE, self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="acreage_title"><?php esc_html_e( 'Farm name', 'acreage' ); ?> <span class="acreage-req">*</span></label></th>
						<td>
							<input name="acreage_title" id="acreage_title" type="text" class="regular-text" required autofocus>
							<p class="description"><?php esc_html_e( 'For example: Mopani Ridge Reserve', 'acreage' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Kind of farm', 'acreage' ); ?></th>
						<td>
							<?php foreach ( $this->terms( 'listing_category' ) as $id => $name ) : ?>
								<label class="acreage-quick__radio">
									<input type="radio" name="acreage_category" value="<?php echo esc_attr( $id ); ?>" <?php checked( $keep_category, $id ); ?>>
									<?php echo esc_html( $name ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="acreage_province"><?php esc_html_e( 'Province', 'acreage' ); ?></label></th>
						<td>
							<select name="acreage_province" id="acreage_province">
								<option value="0"><?php esc_html_e( '— not set —', 'acreage' ); ?></option>
								<?php foreach ( $this->terms( 'province' ) as $id => $name ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $keep_province, $id ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="acreage_price"><?php esc_html_e( 'Price', 'acreage' ); ?></label></th>
						<td>
							<input name="acreage_price" id="acreage_price" type="number" min="0" step="1" class="regular-text">
							<p class="description"><?php esc_html_e( 'Figures only. The price band is worked out from this, and the VAT line is added automatically.', 'acreage' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="acreage_hectares"><?php esc_html_e( 'Size in hectares', 'acreage' ); ?></label></th>
						<td>
							<input name="acreage_hectares" id="acreage_hectares" type="number" min="0" step="1" class="regular-text">
							<p class="description"><?php esc_html_e( 'The size band follows from this.', 'acreage' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="acreage_status"><?php esc_html_e( 'Status', 'acreage' ); ?></label></th>
						<td>
							<select name="acreage_status" id="acreage_status">
								<option value="0"><?php esc_html_e( '— none —', 'acreage' ); ?></option>
								<?php foreach ( $this->terms( 'status' ) as $id => $name ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
							<label class="acreage-quick__inline">
								<input type="checkbox" name="acreage_big_five" value="1">
								<?php esc_html_e( 'Big Five property', 'acreage' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Main photograph', 'acreage' ); ?></th>
						<td>
							<div class="acreage-quick__photo">
								<div class="acreage-quick__preview" id="acreage-quick-preview"></div>
								<input type="hidden" name="acreage_thumbnail" id="acreage_thumbnail" value="">
								<button type="button" class="button" id="acreage-quick-choose"><?php esc_html_e( 'Choose photograph', 'acreage' ); ?></button>
								<button type="button" class="button-link acreage-quick__clear" id="acreage-quick-clear" hidden><?php esc_html_e( 'Remove', 'acreage' ); ?></button>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="acreage_description"><?php esc_html_e( 'Description', 'acreage' ); ?></label></th>
						<td>
							<textarea name="acreage_description" id="acreage_description" rows="6" class="large-text"></textarea>
							<p class="description"><?php esc_html_e( 'Improvements, wildlife and land claims have their own boxes in the full editor.', 'acreage' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Publish now?', 'acreage' ); ?></th>
						<td>
							<label class="acreage-quick__inline">
								<input type="checkbox" name="acreage_publish" value="1" checked>
								<?php esc_html_e( 'Yes — put it on the site straight away', 'acreage' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Leave unticked to save it as a draft and finish it later.', 'acreage' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit acreage-quick__actions">
					<button type="submit" name="acreage_then" value="another" class="button button-primary button-large">
						<?php esc_html_e( 'Save and add another', 'acreage' ); ?>
					</button>
					<button type="submit" name="acreage_then" value="edit" class="button button-large">
						<?php esc_html_e( 'Save and open full editor', 'acreage' ); ?>
					</button>
					<button type="submit" name="acreage_then" value="list" class="button button-large">
						<?php esc_html_e( 'Save and go to all farms', 'acreage' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/* -------------------------------------------------------------- saving */

	public function handle() {
		check_admin_referer( self::NONCE, self::NONCE );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to add farms.', 'acreage' ) );
		}

		$title = isset( $_POST['acreage_title'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_title'] ) ) : '';

		if ( '' === trim( $title ) ) {
			wp_safe_redirect( $this->page_url( array( 'problem' => 'no-title' ) ) );
			exit;
		}

		$description = isset( $_POST['acreage_description'] ) ? wp_kses_post( wp_unslash( $_POST['acreage_description'] ) ) : '';
		$publish     = ! empty( $_POST['acreage_publish'] );

		$post_id = wp_insert_post( array(
			'post_type'    => Acreage_Core_Post_Types::POST_TYPE,
			'post_title'   => $title,
			'post_content' => $description ? wpautop( $description ) : '',
			'post_status'  => $publish ? 'publish' : 'draft',
		), true );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_safe_redirect( $this->page_url( array( 'problem' => 'save-failed' ) ) );
			exit;
		}

		$category = isset( $_POST['acreage_category'] ) ? absint( $_POST['acreage_category'] ) : 0;
		$province = isset( $_POST['acreage_province'] ) ? absint( $_POST['acreage_province'] ) : 0;
		$status   = isset( $_POST['acreage_status'] ) ? absint( $_POST['acreage_status'] ) : 0;

		if ( $category ) {
			wp_set_object_terms( $post_id, array( $category ), 'listing_category' );
		}
		if ( $province ) {
			wp_set_object_terms( $post_id, array( $province ), 'province' );
		}
		if ( $status ) {
			wp_set_object_terms( $post_id, array( $status ), 'status' );
		}

		foreach ( array( 'acreage_price', 'acreage_hectares' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? preg_replace( '/[^0-9.]/', '', wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' !== $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( ! empty( $_POST['acreage_big_five'] ) ) {
			update_post_meta( $post_id, 'acreage_big_five', '1' );
		}

		$thumbnail = isset( $_POST['acreage_thumbnail'] ) ? absint( $_POST['acreage_thumbnail'] ) : 0;
		if ( $thumbnail ) {
			set_post_thumbnail( $post_id, $thumbnail );
		}

		Acreage_Core_Fields::assign_bands_for( $post_id );

		$then = isset( $_POST['acreage_then'] ) ? sanitize_key( wp_unslash( $_POST['acreage_then'] ) ) : 'another';

		if ( 'edit' === $then ) {
			wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
			exit;
		}

		if ( 'list' === $then ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=' . Acreage_Core_Post_Types::POST_TYPE ) );
			exit;
		}

		wp_safe_redirect( $this->page_url( array(
			'added'    => $post_id,
			'province' => $province,
			'category' => $category,
		) ) );
		exit;
	}

	private function page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array( 'post_type' => Acreage_Core_Post_Types::POST_TYPE, 'page' => self::SLUG ),
				$args
			),
			admin_url( 'edit.php' )
		);
	}
}
