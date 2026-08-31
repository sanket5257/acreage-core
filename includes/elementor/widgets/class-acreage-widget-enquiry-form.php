<?php
/**
 * Enquiry Form — the mockup's Form widget, which is Elementor Pro.
 *
 * Carries the farm name into the subject line, which is the specific thing the
 * brief asks for. Enquiries are the only conversion on this site, so the form
 * does the unglamorous things properly: honeypot, nonce, rate limit, and a
 * Reply-To set to the sender so the client can just hit reply.
 *
 * USING A FORM PLUGIN INSTEAD
 *
 * The built-in form is the default because it makes the theme work on a site
 * with no form plugin at all. Plenty of agencies already run Contact Form 7,
 * WPForms or Gravity Forms and want their existing notifications, entry log
 * and spam service, so the widget will render any form plugin's shortcode in
 * place of its own markup.
 *
 * Nothing here knows what a shortcode expands to, which is the point: no
 * per-plugin code path to maintain, and a plugin released next year works on
 * the day it ships. Contact Form 7 gets its forms listed by name purely as a
 * convenience, because it is the one nearly everybody already has.
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

		$this->add_control( 'form_source', array(
			'label'       => __( 'Form', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SELECT,
			'default'     => 'builtin',
			'options'     => self::source_options(),
			'description' => __( 'The built-in form needs no plugin. Pick one of your own forms to use that instead.', 'acreage' ),
		) );

		$this->add_control( 'form_shortcode', array(
			'label'       => __( 'Shortcode', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 2,
			'placeholder' => '[wpforms id="123"]',
			'description' => __( 'Paste the shortcode your form plugin gives you — WPForms, Gravity Forms, Fluent Forms, Forminator, anything.', 'acreage' ),
			'condition'   => array( 'form_source' => 'shortcode' ),
		) );

		$this->add_control( 'to', array(
			'condition'   => array( 'form_source' => 'builtin' ),
			'label'       => __( 'Send enquiries to', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => get_option( 'admin_email' ),
			'description' => __( 'Leave as-is to use the site’s admin address.', 'acreage' ),
		) );

		$this->add_control( 'subject_prefix', array(
			'condition'   => array( 'form_source' => 'builtin' ),
			'label'       => __( 'Subject line starts with', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => __( 'Enquiry —', 'acreage' ),
			'description' => __( 'The farm’s name is added after this automatically.', 'acreage' ),
		) );

		/*
		 * The two extra fields, both off by default.
		 *
		 * WHY OFF, AND WHY NOT ALWAYS ON
		 *
		 * On a farm page the subject is the farm — the form already prints
		 * "About <farm name>" above the fields and the enquiry carries the
		 * listing ID. Asking a buyer to type a subject there is a field that can
		 * only be answered wrong, and every extra field on a conversion form
		 * costs enquiries.
		 *
		 * On the contact page there is no farm, so the message arrives as
		 * "Enquiry — Africa Game Farms" and the owner has to open it to learn
		 * what it is about. That is the page these are for, and the kit switches
		 * them on there.
		 */
		$this->add_control( 'show_subject', array(
			'condition'    => array( 'form_source' => 'builtin' ),
			'label'        => __( 'Ask for a subject', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => __( 'Show', 'acreage' ),
			'label_off'    => __( 'Hide', 'acreage' ),
			'default'      => '',
			'description'  => __( 'For a general contact page. On a farm page the farm is the subject already.', 'acreage' ),
		) );

		$this->add_control( 'show_regarding', array(
			'condition'   => array( 'form_source' => 'builtin' ),
			'label'       => __( 'Ask what it is regarding', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::SWITCHER,
			'label_on'    => __( 'Show', 'acreage' ),
			'label_off'   => __( 'Hide', 'acreage' ),
			'default'     => '',
			'description' => __( 'A dropdown, so general questions can be told apart from farm enquiries at a glance.', 'acreage' ),
		) );

		$this->add_control( 'regarding_label', array(
			'condition' => array( 'form_source' => 'builtin', 'show_regarding' => 'yes' ),
			'label'     => __( 'Dropdown label', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::TEXT,
			'default'   => __( 'Regarding', 'acreage' ),
		) );

		/*
		 * A textarea of lines rather than a repeater: the customer is writing a
		 * list of five short phrases, and a repeater makes that five panels to
		 * open. The value is only ever echoed back into the email, never used to
		 * route anything, so there is nothing here worth the extra interface.
		 */
		$this->add_control( 'regarding_options', array(
			'condition'   => array( 'form_source' => 'builtin', 'show_regarding' => 'yes' ),
			'label'       => __( 'Dropdown choices', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXTAREA,
			'rows'        => 6,
			'default'     => implode(
				"\n",
				array(
					__( 'General query', 'acreage' ),
					__( 'About a game farm', 'acreage' ),
					__( 'About a cattle farm', 'acreage' ),
					__( 'Selling a farm', 'acreage' ),
					__( 'Something about the website', 'acreage' ),
					__( 'Other', 'acreage' ),
				)
			),
			'description' => __( 'One per line. The first is what the dropdown opens on.', 'acreage' ),
		) );

		/*
		 * Which choice the dropdown opens on.
		 *
		 * The point of this is the "Sell your farm" page, where the visitor has
		 * already said what they want by being on that page at all. Making them
		 * pick "Selling a farm" out of a list they have to read first is asking
		 * them to answer a question they have already answered.
		 *
		 * Matched against the list above rather than pushed in as a new option:
		 * a typo here should leave the dropdown on its first choice, not add a
		 * seventh one nobody meant to offer.
		 */
		$this->add_control( 'regarding_default', array(
			'condition'   => array( 'form_source' => 'builtin', 'show_regarding' => 'yes' ),
			'label'       => __( 'Opens on', 'acreage' ),
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => '',
			'description' => __( 'Type one of the choices above to have the dropdown start there. Leave empty for the first one.', 'acreage' ),
		) );

		$this->add_control( 'button_text', array(
			'condition' => array( 'form_source' => 'builtin' ),
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Send enquiry', 'acreage' ),
		) );

		$this->add_control( 'success_text', array(
			'condition' => array( 'form_source' => 'builtin' ),
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
			/*
			 * Sets the button VARIABLE, not the background.
			 *
			 * Elementor writes a control's selectors into a per-page stylesheet
			 * at very high specificity — .elementor-1516 .elementor-element-11bc242
			 * .acreage-w-search__submit — which beat the theme's own :hover rule
			 * and left this button with no hover state at all. Feeding the
			 * contract instead means a per-instance colour still flows through
			 * everything else, hover and border included.
			 */
			'label'     => __( 'Button colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}}' => '--acreage-btn-bg:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * The choices in the Form dropdown.
	 *
	 * Contact Form 7 stores each form as a post of type wpcf7_contact_form, so
	 * they can be listed by name without loading any of its code. Guarded on the
	 * post type rather than on a class or a constant: the post type is what we
	 * actually read, and checking the thing you use is the check that cannot go
	 * stale when the plugin renames its internals.
	 *
	 * @return array value => label
	 */
	/**
	 * The "Regarding" choices, parsed from the textarea.
	 *
	 * Blank lines are dropped rather than becoming an empty option, and stray
	 * carriage returns are handled because a customer pasting from Word or from
	 * Notepad on Windows sends \r\n.
	 *
	 * @param string $raw One choice per line.
	 * @return string[]
	 */
	private static function choices( $raw ) {
		$lines = preg_split( '/\R/', (string) $raw );
		$out   = array();

		foreach ( (array) $lines as $line ) {
			$line = sanitize_text_field( $line );

			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return $out;
	}

	private static function source_options() {
		$options = array(
			'builtin' => __( 'Built-in enquiry form', 'acreage' ),
		);

		if ( post_type_exists( 'wpcf7_contact_form' ) ) {
			$forms = get_posts( array(
				'post_type'        => 'wpcf7_contact_form',
				'post_status'      => 'any',
				'numberposts'      => 100,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			) );

			foreach ( $forms as $form ) {
				$options[ 'cf7:' . $form->ID ] = sprintf(
					/* translators: %s: the name of a Contact Form 7 form. */
					__( 'Contact Form 7 — %s', 'acreage' ),
					$form->post_title
				);
			}
		}

		$options['shortcode'] = __( 'Another form plugin (paste a shortcode)', 'acreage' );

		/**
		 * Filter the form sources offered by the Enquiry Form widget.
		 *
		 * @param array $options value => label.
		 */
		return apply_filters( 'acreage_core_form_sources', $options );
	}

	/**
	 * The shortcode to render, or '' to use the built-in form.
	 *
	 * A source of "cf7:12" whose form has since been deleted falls back to the
	 * built-in form rather than printing a raw, unexpanded shortcode at a
	 * visitor — a dead form plugin should cost the site a nicer form, never its
	 * only means of being contacted.
	 *
	 * @param array $settings Widget settings.
	 * @return string
	 */
	private function chosen_shortcode( $settings ) {
		$source = isset( $settings['form_source'] ) ? $settings['form_source'] : 'builtin';

		if ( 'shortcode' === $source ) {
			return trim( (string) ( isset( $settings['form_shortcode'] ) ? $settings['form_shortcode'] : '' ) );
		}

		if ( 0 === strpos( $source, 'cf7:' ) ) {
			/*
			 * Deactivating Contact Form 7 leaves its form rows in the database,
			 * so get_post() still answers and post_type still reads
			 * wpcf7_contact_form. Testing the post alone therefore says "yes,
			 * that form exists" about a plugin that is switched off, and the
			 * page ends up with the shortcode unexpanded. Ask whether CF7 is
			 * running as well.
			 */
			if ( ! post_type_exists( 'wpcf7_contact_form' ) ) {
				return '';
			}

			$id   = absint( substr( $source, 4 ) );
			$form = $id ? get_post( $id ) : null;

			if ( ! $form || 'wpcf7_contact_form' !== $form->post_type ) {
				return '';
			}

			return sprintf( '[contact-form-7 id="%d" title="%s"]', $id, esc_attr( $form->post_title ) );
		}

		return '';
	}

	/** The farm this form is about, if any. */
	private function subject_post() {
		return is_singular( Acreage_Core_Post_Types::POST_TYPE ) ? get_queried_object_id() : 0;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$post_id  = $this->subject_post();
		$farm     = $post_id ? get_the_title( $post_id ) : '';

		$shortcode = $this->chosen_shortcode( $settings );

		if ( '' !== $shortcode && $this->render_plugin_form( $settings, $shortcode ) ) {
			return;
		}

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
		<div class="acreage-w-form" id="acreage-enquire">
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

					<?php if ( 'yes' === $settings['show_subject'] ) : ?>
						<p class="acreage-w-form__field">
							<label for="acreage-subject-<?php echo esc_attr( $this->get_id() ); ?>"><?php esc_html_e( 'Subject', 'acreage' ); ?></label>
							<input type="text" name="acreage_subject" id="acreage-subject-<?php echo esc_attr( $this->get_id() ); ?>">
						</p>
					<?php endif; ?>

					<?php
					$choices = 'yes' === $settings['show_regarding'] ? self::choices( $settings['regarding_options'] ) : array();

					// A dropdown with nothing in it is a dropdown that cannot be
					// answered, so an emptied-out list hides the field entirely.
					if ( $choices ) :
						?>
						<p class="acreage-w-form__field">
							<label for="acreage-regarding-<?php echo esc_attr( $this->get_id() ); ?>">
								<?php echo esc_html( $settings['regarding_label'] ? $settings['regarding_label'] : __( 'Regarding', 'acreage' ) ); ?>
							</label>
							<?php
							// An unrecognised preselection is ignored, so the
							// dropdown falls back to opening on its first choice.
							$preselect = sanitize_text_field( $settings['regarding_default'] );
							$preselect = in_array( $preselect, $choices, true ) ? $preselect : '';
							?>
							<select name="acreage_regarding" id="acreage-regarding-<?php echo esc_attr( $this->get_id() ); ?>">
								<?php foreach ( $choices as $choice ) : ?>
									<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $preselect, $choice ); ?>>
										<?php echo esc_html( $choice ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>
					<?php endif; ?>

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

	/**
	 * Render a form owned by another plugin.
	 *
	 * The wrapper classes are the whole contract with the stylesheet: whatever
	 * markup the plugin emits lands inside .acreage-w-form--plugin, and the
	 * theme's form rules reach it there. Without that the form renders in the
	 * plugin's own default styling and looks pasted on.
	 *
	 * @param array  $settings  Widget settings.
	 * @param string $shortcode Shortcode to expand.
	 * @return bool True when a form was printed; false to fall back to the built-in one.
	 */
	private function render_plugin_form( $settings, $shortcode ) {
		$output = do_shortcode( $shortcode );

		/*
		 * An unregistered shortcode comes back unchanged — the plugin was
		 * deactivated, or the ID was mistyped. Printing "[wpforms id=7]" at a
		 * visitor is bad; printing nothing at all is worse, because enquiries
		 * are the only conversion on the page. Tell whoever is editing what is
		 * wrong, and give the visitor the built-in form meanwhile.
		 */
		if ( trim( $output ) === trim( $shortcode ) ) {
			$this->editor_notice( __( 'That form did not render, so the built-in enquiry form is being shown instead. Check the form plugin is active and the shortcode is correct.', 'acreage' ) );
			return false;
		}
		?>
		<div class="acreage-w-form acreage-w-form--plugin" id="acreage-enquire">
			<?php if ( ! empty( $settings['heading'] ) ) : ?>
				<h2 class="acreage-w-form__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>
			<?php echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output, escaped by the form plugin. ?>
		</div>
		<?php
		return true;
	}

}
