<?php
/**
 * Enquiry Form — the mockup's Form widget, which is Elementor Pro.
 *
 * Carries the farm name into the subject line, which is the specific thing the
 * brief asks for. Enquiries are the only conversion on this site, so the form
 * does the unglamorous things properly: honeypot, nonce, rate limit, and a
 * Reply-To set to the sender so the client can just hit reply.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Enquiry_Form extends Acreage_Widget_Base {

	public function get_name() {
		return 'acreage-enquiry-form';
	}

	public function get_title() {
		return __( 'Enquiry Form', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Form', 'acreage' ),
		) );

		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Ask about this farm', 'acreage' ),
		) );

		$this->add_control( 'to', array(
			'label'       => __( 'Send enquiries to', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => get_option( 'admin_email' ),
			'description' => __( 'Leave as-is to use the site’s admin address.', 'acreage' ),
		) );

		$this->add_control( 'subject_prefix', array(
			'label'       => __( 'Subject line starts with', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Enquiry —', 'acreage' ),
			'description' => __( 'The farm’s name is added after this automatically.', 'acreage' ),
		) );

		$this->add_control( 'button_text', array(
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Send enquiry', 'acreage' ),
		) );

		$this->add_control( 'success_text', array(
			'label'   => __( 'After sending', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXTAREA,
			'rows'    => 3,
			'default' => __( 'Thank you — your enquiry is on its way. You will hear back directly from the owner.', 'acreage' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'button_bg', array(
			'label'     => __( 'Button colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-form__submit' => 'background:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/** The farm this form is about, if any. */
	private function subject_post() {
		return is_singular( Acreage_Core_Post_Types::POST_TYPE ) ? get_queried_object_id() : 0;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$post_id  = $this->subject_post();
		$farm     = $post_id ? get_the_title( $post_id ) : '';

		// Resolve the routing here, then sign it, so the handler can tell the
		// owner's settings apart from whatever a browser chooses to send.
		$to = sanitize_email( $settings['to'] );

		if ( ! is_email( $to ) ) {
			$to = Acreage_Core_Enquiry::default_recipient();
		}

		$prefix = sanitize_text_field( $settings['subject_prefix'] );

		$sent     = isset( $_GET['acreage-sent'] ) && '1' === $_GET['acreage-sent']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed   = isset( $_GET['acreage-sent'] ) && '0' === $_GET['acreage-sent']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="acreage-w-form">
			<?php if ( $settings['heading'] ) : ?>
				<h2 class="acreage-w-form__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>

			<?php if ( $sent ) : ?>
				<p class="acreage-w-form__ok"><?php echo esc_html( $settings['success_text'] ); ?></p>
			<?php else : ?>

				<?php if ( $failed ) : ?>
					<p class="acreage-w-form__error">
						<?php esc_html_e( 'That did not send. Check the email address and try again, or phone instead.', 'acreage' ); ?>
					</p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acreage-w-form__form">
					<input type="hidden" name="action" value="acreage_enquiry">
					<input type="hidden" name="acreage_listing" value="<?php echo esc_attr( $post_id ); ?>">
					<input type="hidden" name="acreage_to" value="<?php echo esc_attr( $to ); ?>">
					<input type="hidden" name="acreage_prefix" value="<?php echo esc_attr( $prefix ); ?>">
					<input type="hidden" name="acreage_sig" value="<?php echo esc_attr( Acreage_Core_Enquiry::sign( $to, $prefix ) ); ?>">
					<input type="hidden" name="acreage_return" value="<?php echo esc_url( get_permalink() ); ?>">
					<?php wp_nonce_field( Acreage_Core_Enquiry::NONCE, Acreage_Core_Enquiry::NONCE ); ?>

					<?php if ( $farm ) : ?>
						<p class="acreage-w-form__about">
							<?php
							printf(
								/* translators: %s: farm name. */
								esc_html__( 'About %s', 'acreage' ),
								'<strong>' . esc_html( $farm ) . '</strong>'
							);
							?>
						</p>
					<?php endif; ?>

					<p class="acreage-w-form__field">
						<label for="acreage-name-<?php echo esc_attr( $this->get_id() ); ?>"><?php esc_html_e( 'Your name', 'acreage' ); ?></label>
						<input type="text" name="acreage_name" id="acreage-name-<?php echo esc_attr( $this->get_id() ); ?>" required>
					</p>

					<p class="acreage-w-form__field">
						<label for="acreage-email-<?php echo esc_attr( $this->get_id() ); ?>"><?php esc_html_e( 'Email', 'acreage' ); ?></label>
						<input type="email" name="acreage_email" id="acreage-email-<?php echo esc_attr( $this->get_id() ); ?>" required>
					</p>

					<p class="acreage-w-form__field">
						<label for="acreage-phone-<?php echo esc_attr( $this->get_id() ); ?>"><?php esc_html_e( 'Phone', 'acreage' ); ?></label>
						<input type="tel" name="acreage_phone" id="acreage-phone-<?php echo esc_attr( $this->get_id() ); ?>">
					</p>

					<p class="acreage-w-form__field">
						<label for="acreage-message-<?php echo esc_attr( $this->get_id() ); ?>"><?php esc_html_e( 'Message', 'acreage' ); ?></label>
						<textarea name="acreage_message" id="acreage-message-<?php echo esc_attr( $this->get_id() ); ?>" rows="5" required></textarea>
					</p>

					<?php // Honeypot: a real person never fills this in. ?>
					<p class="acreage-w-form__trap" aria-hidden="true">
						<label><?php esc_html_e( 'Leave this empty', 'acreage' ); ?>
							<input type="text" name="acreage_website" tabindex="-1" autocomplete="off">
						</label>
					</p>

					<p>
						<button class="acreage-w-form__submit" type="submit"><?php echo esc_html( $settings['button_text'] ); ?></button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

}
