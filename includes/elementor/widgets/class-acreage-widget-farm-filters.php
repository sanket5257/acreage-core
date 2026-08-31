<?php
/**
 * Farm Filters — the archive sidebar from the "Farms for Sale" mockup.
 *
 * Checkbox lists per axis, submitted as a normal form. Filters read from and
 * write to the URL, so every combination is linkable, exactly as the mockup's
 * build note asks. Counts are shown so a visitor can see where the inventory
 * actually is before clicking.
 *
 * LIVE, BUT STILL A FORM
 *
 * When there is a Farm Grid on the page set to "Whatever the page is filtered
 * to", ticking a box re-draws the farms in place instead of reloading — see
 * assets/js/filters.js. That is an enhancement laid over this form, never a
 * replacement for it: the markup below is a working GET form with an Apply
 * button, the script hides the button only once it has taken over, and every
 * result still has its own URL. Turn JavaScript off and the panel behaves
 * exactly as it did before.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Widget_Farm_Filters extends Acreage_Widget_Base {

	/**
	 * The filter state of this request, worked out lazily and kept.
	 *
	 * @var array|null
	 */
	private $state = null;

	public function get_name() {
		return 'acreage-farm-filters';
	}

	public function get_title() {
		return __( 'Farm Filters', 'acreage' );
	}

	public function get_icon() {
		return 'eicon-filter';
	}

	private function axes() {
		return Acreage_Core_Filters::axes();
	}

	protected function register_controls() {

		$this->start_controls_section( 'content', array(
			'label' => __( 'Filters', 'acreage' ),
		) );

		$this->add_control( 'heading', array(
			'label'   => __( 'Heading', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Filter', 'acreage' ),
		) );

		foreach ( $this->axes() as $taxonomy => $label ) {
			$this->add_control( 'show_' . $taxonomy, array(
				'label'        => $label,
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => in_array( $taxonomy, array( 'species', 'status', 'region' ), true ) ? '' : 'yes',
				'return_value' => 'yes',
			) );
		}

		$this->add_control( 'show_counts', array(
			'label'        => __( 'Show how many farms are in each', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'hide_empty', array(
			'label'        => __( 'Hide options with no farms', 'acreage' ),
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'default'      => 'yes',
			'return_value' => 'yes',
		) );

		$this->add_control( 'submit_text', array(
			'label'   => __( 'Button wording', 'acreage' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Apply filters', 'acreage' ),
		) );

		$this->end_controls_section();

		$this->start_controls_section( 'style', array(
			'label' => __( 'Style', 'acreage' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'accent', array(
			'label'     => __( 'Accent colour', 'acreage' ),
			'type'      => \Elementor\Controls_Manager::COLOR,
			'selectors' => array( '{{WRAPPER}} .acreage-w-filters' => '--acreage-w-accent:{{VALUE}};' ),
		) );

		$this->end_controls_section();
	}

	/**
	 * What the page is currently filtered by.
	 *
	 * Worked out once per render and reused: the panel asks the same question
	 * for every checkbox on every axis, and the answer cannot change mid-render.
	 * Acreage_Core_Filters is what knows how to read it — from the query string,
	 * from a term archive, or from both at once — so a visitor who arrived from
	 * a province tile sees that province ticked and can untick it.
	 *
	 * @return array taxonomy => slugs.
	 */
	private function state() {
		if ( null === $this->state ) {
			$this->state = Acreage_Core_Filters::from_request();
		}

		return $this->state;
	}

	/** The term slugs currently filtering one axis. */
	private function current_terms( $taxonomy ) {
		$state = $this->state();

		return isset( $state[ $taxonomy ] ) ? $state[ $taxonomy ] : array();
	}

	/** The keyword currently searched, if any. */
	private function current_search() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only search.
		return Acreage_Core_Filters::search( $_GET );
	}

	/** The sort currently in force, if any. */
	private function current_sort() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only sorting.
		return Acreage_Core_Filters::sort( $_GET );
	}


	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			$this->editor_notice( __( 'The Acreage Core plugin is not active, so there is nothing to filter.', 'acreage' ) );
			return;
		}

		$state  = $this->state();
		$search = $this->current_search();
		$sort   = $this->current_sort();
		$chips  = Acreage_Core_Filters::chips( $state, $search, $sort );

		/*
		 * The panel always carries its script. It is small, it only ever enhances
		 * the form below, and it does nothing at all on a page that has no
		 * archive grid for it to drive.
		 */
		wp_enqueue_script( 'acreage-filters' );
		?>
		<form class="acreage-w-filters" method="get" action="<?php echo esc_url( $this->archive_url() ); ?>"
			data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			data-archive="<?php echo esc_url( Acreage_Core_Filters::archive_url() ); ?>"
			data-axes="<?php echo esc_attr( implode( ',', Acreage_Core_Filters::taxonomies() ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Acreage_Core_Post_Types::POST_TYPE ); ?>">

			<?php
			/*
			 * A keyword and a sort survive a filter change.
			 *
			 * Without these two lines, applying a filter on top of a search threw
			 * the search away — the panel submitted only its own checkboxes — and
			 * the visitor got the whole inventory back with no explanation. The
			 * chip bar above already offers to remove the search deliberately;
			 * that is the only way it should ever go.
			 */
			?>
			<?php if ( '' !== $search ) : ?>
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
			<?php endif; ?>

			<?php if ( '' !== $sort ) : ?>
				<input type="hidden" name="sort" value="<?php echo esc_attr( $sort ); ?>">
			<?php endif; ?>

			<?php if ( $settings['heading'] ) : ?>
				<h2 class="acreage-w-filters__heading"><?php echo esc_html( $settings['heading'] ); ?></h2>
			<?php endif; ?>

			<?php
			/*
			 * The chip bar is always wrapped, even when empty, because the live
			 * filter replaces its contents. A container that only exists when
			 * something is filtered is a container the script cannot fill on the
			 * click that filters the first thing.
			 */
			?>
			<div class="acreage-w-filters__activewrap">
				<?php
				echo Acreage_Core_Filters::chips_html( $state, $search, $sort ); // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped at source.
				?>
			</div>

			<?php
			/*
			 * Live results say how many farms matched. A visitor who ticks a box
			 * and sees the cards change still deserves to be told the size of what
			 * they are looking at, and a screen reader has nothing else to go on.
			 * Printed empty and hidden; the script is what fills it.
			 */
			?>
			<p class="acreage-w-filters__result" role="status" aria-live="polite" hidden></p>

			<?php
			/*
			 * The groups collapse on a phone.
			 *
			 * Seven filter axes with every term listed is 800px of checkboxes.
			 * On a desktop that is a sidebar beside the farms; on a phone it is
			 * a wall the visitor scrolls past before reaching a single farm —
			 * which is the wrong way round on the page whose entire job is
			 * showing farms.
			 *
			 * Printed OPEN, and closed afterwards by the script only when the
			 * screen is narrow. That order matters and follows the same rule as
			 * the rest of this widget: with no JavaScript the visitor gets the
			 * full working form exactly as before, never a panel that needs a
			 * script to be opened.
			 *
			 * The chip bar above stays outside the disclosure, so what is
			 * currently filtered is readable whether or not it is expanded.
			 */
			?>
			<details class="acreage-w-filters__disclosure" open>
				<summary class="acreage-w-filters__toggle">
					<span><?php esc_html_e( 'Filter farms', 'acreage' ); ?></span>
				</summary>

			<?php
			/*
			 * The checkbox groups are captured rather than printed straight out,
			 * so that the loop can report back which slugs it actually gave a
			 * checkbox to. What it did not is dealt with immediately below.
			 */
			ob_start();
			$rendered = array();

			foreach ( $this->axes() as $taxonomy => $label ) :
				if ( 'yes' !== $settings[ 'show_' . $taxonomy ] || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = get_terms( array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => 'yes' === $settings['hide_empty'],
				) );

				if ( ! $terms || is_wp_error( $terms ) ) {
					continue;
				}

				$chosen = $this->current_terms( $taxonomy );
				?>
				<fieldset class="acreage-w-filters__group">
					<legend class="acreage-w-filters__legend"><?php echo esc_html( $label ); ?></legend>

					<ul class="acreage-w-filters__list">
						<?php
						foreach ( $terms as $term ) :
							$rendered[ $taxonomy ][] = $term->slug;
							?>
							<li>
								<label class="acreage-w-filters__option">
									<input
										type="checkbox"
										name="<?php echo esc_attr( $taxonomy ); ?>[]"
										value="<?php echo esc_attr( $term->slug ); ?>"
										<?php checked( in_array( $term->slug, $chosen, true ) ); ?>>
									<span class="acreage-w-filters__name"><?php echo esc_html( $term->name ); ?></span>
									<?php if ( 'yes' === $settings['show_counts'] ) : ?>
										<span class="acreage-w-filters__count"><?php echo esc_html( number_format_i18n( $term->count ) ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</fieldset>
				<?php
			endforeach;

			$groups = ob_get_clean();

			/*
			 * A filter with no checkbox is carried anyway.
			 *
			 * Species is off by default, and a visitor can still arrive on
			 * ?species=sable from a listing page or a shared link. The chip bar
			 * shows it and offers to remove it, deliberately — but the form used
			 * to submit only its own checkboxes, so the next tick of any other box
			 * threw that filter away without anybody asking. These carry it, for
			 * the submitted form and for the live one alike.
			 */
			foreach ( $state as $taxonomy => $slugs ) {
				foreach ( $slugs as $slug ) {
					if ( isset( $rendered[ $taxonomy ] ) && in_array( $slug, $rendered[ $taxonomy ], true ) ) {
						continue;
					}

					printf(
						'<input type="hidden" name="%s[]" value="%s">',
						esc_attr( $taxonomy ),
						esc_attr( $slug )
					);
				}
			}

			/*
			 * The groups and the actions share a wrapper so that the phone has
			 * one box to cap.
			 *
			 * Opened, this panel is four screens of checkboxes — seventeen of
			 * them on a stocked site — and every one of them sits between the
			 * visitor and the first farm. The wrapper is what the mobile rules
			 * give a max-height and its own scroll to, which leaves the summary
			 * outside it and therefore still on screen: the control that closes
			 * the panel does not scroll away with the thing it closes.
			 *
			 * A wrapper rather than scrolling the <details> itself, because that
			 * would take the summary with it.
			 */
			echo '<div class="acreage-w-filters__panel">';
			echo $groups; // phpcs:ignore WordPress.Security.EscapingOutput.OutputNotEscaped -- escaped as it was built.
			?>

			<div class="acreage-w-filters__actions">
				<button class="acreage-w-filters__submit" type="submit"><?php echo esc_html( $settings['submit_text'] ); ?></button>

				<?php if ( $chips ) : ?>
					<?php // Keeps the sort, for the same reason Clear all does. ?>
					<a class="acreage-w-filters__clear" href="<?php echo esc_url( Acreage_Core_Filters::url( array(), '', $sort ) ); ?>">
						<?php
						printf(
							/* translators: %d: number of filters currently applied. */
							esc_html( _n( 'Clear %d filter', 'Clear %d filters', count( $chips ), 'acreage' ) ),
							count( $chips )
						);
						?>
					</a>
				<?php endif; ?>
			</div>
			</div>
			</details>
		</form>
		<?php
	}
}
