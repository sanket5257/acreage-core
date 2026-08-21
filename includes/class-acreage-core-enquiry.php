<?php
/**
 * Enquiry handling.
 *
 * Deliberately separate from the Elementor widget that draws the form. The
 * widget extends an Elementor class, so loading it on a site without Elementor
 * would be a fatal error — and enquiries are the only conversion on this site,
 * so the part that actually sends them must never depend on a page builder.
 *
 * SECURITY NOTE — why the recipient is signed.
 *
 * The form carries its destination address in a hidden field so one page can
 * route to sales and another to a branch office. A hidden field is still a
 * field: anything a browser sends, an attacker can rewrite. The nonce does not
 * help here, because a nonce for a logged-out visitor is public by definition —
 * any bot can load the page and read one.
 *
 * Left unchecked that turns the site into an open mail relay: arbitrary
 * recipient, arbitrary subject, arbitrary body, sent from the client's own mail
 * server until the domain is blacklisted.
 *
 * So the recipient and subject prefix travel with an HMAC derived from the
 * site's salts. The owner's configured values verify; anything else does not,
 * and we send to the admin address instead of where we were told. Forging the
 * signature requires the salts, and an attacker holding those does not need a
 * contact form.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Enquiry {

	const NONCE = 'acreage_enquiry';

	/** Namespace for the recipient signature, so the hash cannot be replayed elsewhere. */
	const SIG_CONTEXT = 'acreage_enquiry_target';

	/** One enquiry per email address per minute. */
	const EMAIL_COOLDOWN = MINUTE_IN_SECONDS;

	/** No more than this many from one IP per window, whatever address is typed. */
	const IP_LIMIT  = 5;
	const IP_WINDOW = 600;

	public function __construct() {
		add_action( 'admin_post_nopriv_acreage_enquiry', array( $this, 'handle' ) );
		add_action( 'admin_post_acreage_enquiry', array( $this, 'handle' ) );
	}

	/**
	 * Signature proving a recipient/prefix pair came from the site's own settings.
	 *
	 * Normalisation happens here, once, so signer and verifier can never disagree
	 * about whitespace or letter case.
	 *
	 * @param string $to     Recipient address.
	 * @param string $prefix Subject prefix.
	 * @return string
	 */
	public static function sign( $to, $prefix ) {
		return wp_hash( self::SIG_CONTEXT . '|' . strtolower( trim( (string) $to ) ) . '|' . trim( (string) $prefix ) );
	}

	/** Where enquiries go when nothing valid was configured. */
	public static function default_recipient() {
		/**
		 * Filter the fallback enquiry recipient.
		 *
		 * Resolved server-side, so this is the safe place to override routing.
		 *
		 * @param string $to Email address.
		 */
		return apply_filters( 'acreage_core_enquiry_recipient', get_option( 'admin_email' ) );
	}

	public function handle() {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE ] ), self::NONCE ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$return = isset( $_POST['acreage_return'] ) ? esc_url_raw( wp_unslash( $_POST['acreage_return'] ) ) : home_url( '/' );

		// Honeypot filled in — behave exactly as if it sent, so a bot learns nothing.
		if ( ! empty( $_POST['acreage_website'] ) ) {
			$this->finish( $return, true );
		}

		$name    = isset( $_POST['acreage_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_name'] ) ) : '';
		$email   = isset( $_POST['acreage_email'] ) ? sanitize_email( wp_unslash( $_POST['acreage_email'] ) ) : '';
		$phone   = isset( $_POST['acreage_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_phone'] ) ) : '';
		$message = isset( $_POST['acreage_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['acreage_message'] ) ) : '';
		$listing = isset( $_POST['acreage_listing'] ) ? absint( $_POST['acreage_listing'] ) : 0;

		if ( ! $name || ! is_email( $email ) || ! $message ) {
			$this->finish( $return, false );
		}

		list( $to, $prefix ) = $this->resolve_target();

		if ( ! $this->within_limits( $email ) ) {
			// Silently accept: telling a flooder they were throttled only helps them tune.
			$this->finish( $return, true );
		}

		$farm    = $listing ? get_the_title( $listing ) : get_bloginfo( 'name' );
		$subject = trim( $prefix . ' ' . $farm );

		$body = array(
			/* translators: %s: farm name. */
			sprintf( __( 'Enquiry about: %s', 'acreage' ), $farm ),
			$listing ? get_permalink( $listing ) : '',
			'',
			/* translators: %s: sender name. */
			sprintf( __( 'Name: %s', 'acreage' ), $name ),
			/* translators: %s: sender email. */
			sprintf( __( 'Email: %s', 'acreage' ), $email ),
			/* translators: %s: sender phone. */
			$phone ? sprintf( __( 'Phone: %s', 'acreage' ), $phone ) : '',
			'',
			$message,
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			sprintf( 'Reply-To: %s <%s>', $this->header_safe( $name ), $email ),
		);

		$sent = wp_mail( $to, $this->header_safe( $subject ), implode( "\n", array_filter( $body, 'strlen' ) ), $headers );

		/**
		 * Fires after an enquiry is attempted, so a CRM or a log can hook in.
		 *
		 * @param bool  $sent    Whether wp_mail() accepted it.
		 * @param array $enquiry The sanitised enquiry.
		 */
		do_action( 'acreage_core_enquiry_sent', $sent, compact( 'name', 'email', 'phone', 'message', 'listing' ) );

		$this->finish( $return, $sent );
	}

	/**
	 * Where this enquiry is allowed to go.
	 *
	 * Returns the submitted pair only if it carries a valid signature. Anything
	 * else — tampered, missing, or signed under older salts — routes to the admin
	 * address with a neutral subject. Mail still gets through; it simply cannot be
	 * aimed by a stranger.
	 *
	 * @return array Recipient address and subject prefix, in that order.
	 */
	private function resolve_target() {
		$to     = isset( $_POST['acreage_to'] ) ? sanitize_email( wp_unslash( $_POST['acreage_to'] ) ) : '';
		$prefix = isset( $_POST['acreage_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_prefix'] ) ) : '';
		$sig    = isset( $_POST['acreage_sig'] ) ? sanitize_text_field( wp_unslash( $_POST['acreage_sig'] ) ) : '';

		$signed = $to && $sig && hash_equals( self::sign( $to, $prefix ), $sig );

		if ( ! $signed || ! is_email( $to ) ) {
			return array( self::default_recipient(), __( 'Enquiry —', 'acreage' ) );
		}

		return array( $to, $prefix );
	}

	/**
	 * Rate limiting on two axes.
	 *
	 * Throttling by email address alone is theatre — a flooder changes one
	 * character. The address limit stops a human double-clicking; the IP limit is
	 * the one that stops a script.
	 *
	 * @param string $email Sender address.
	 * @return bool Whether this send may proceed.
	 */
	private function within_limits( $email ) {
		$email_key = 'acreage_enq_e_' . md5( strtolower( $email ) );

		if ( get_transient( $email_key ) ) {
			return false;
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( $ip ) {
			$ip_key = 'acreage_enq_i_' . md5( $ip );
			$count  = (int) get_transient( $ip_key );

			if ( $count >= self::IP_LIMIT ) {
				return false;
			}

			set_transient( $ip_key, $count + 1, self::IP_WINDOW );
		}

		set_transient( $email_key, 1, self::EMAIL_COOLDOWN );

		return true;
	}

	/**
	 * Strip anything that could begin a new mail header.
	 *
	 * sanitize_text_field() already collapses newlines, so this is a second lock
	 * on the same door — cheap, and it keeps the guarantee beside the code that
	 * builds the headers rather than three functions away.
	 *
	 * @param string $value Untrusted value destined for a header.
	 * @return string
	 */
	private function header_safe( $value ) {
		return trim( preg_replace( '/[\r\n]+/', ' ', (string) $value ) );
	}

	private function finish( $return, $ok ) {
		wp_safe_redirect( add_query_arg( 'acreage-sent', $ok ? '1' : '0', $return ) );
		exit;
	}
}
