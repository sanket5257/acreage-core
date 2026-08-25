<?php
/**
 * Shared behaviour for the farm widgets.
 */

defined( 'ABSPATH' ) || exit;

abstract class Acreage_Widget_Base extends \Elementor\Widget_Base {

	public function get_categories() {
		return array( Acreage_Core_Elementor::CATEGORY );
	}

	public function get_keywords() {
		return array( 'farm', 'listing', 'property', 'acreage' );
	}

	/** Terms of a taxonomy as slug => name, for a select control. */
	protected function term_options( $taxonomy, $any_label = '' ) {
		$options = array();

		if ( $any_label ) {
			$options[''] = $any_label;
		}

		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->slug ] = $term->name;
			}
		}

		return $options;
	}

	/** Where filter forms and browse links point. */
	protected function archive_url() {
		$url = get_post_type_archive_link( Acreage_Core_Post_Types::POST_TYPE );

		return $url ? $url : home_url( '/' );
	}

	/**
	 * The editor renders widgets with no real query behind them. When a widget
	 * needs listings and none exist, say so in the editor rather than rendering
	 * nothing, which reads as a broken widget.
	 */
	protected function editor_notice( $message ) {
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		printf(
			'<div class="acreage-w-notice">%s</div>',
			esc_html( $message )
		);
	}

	/*
	 * Price and extent are the grid engine's, not copies of it.
	 *
	 * They were copies, and the copies grouped with commas while the engine
	 * grouped with spaces — so the same farm read R28,500,000 on its own page
	 * and R28 500 000 on the card that linked to it. One formatter, and the
	 * "Price on application" wording only has one place to be changed.
	 */

	/** Price formatted the way the trade writes it. */
	protected function price( $post_id ) {
		return Acreage_Core_Grid::price( $post_id );
	}

	/** Extent, or an empty string when it has not been recorded. */
	protected function extent( $post_id ) {
		return Acreage_Core_Grid::extent( $post_id );
	}

	/** First term name of a taxonomy on a post. */
	protected function first_term( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	}
}
