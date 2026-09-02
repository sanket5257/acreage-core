<?php
/**
 * What a keyword search actually looks at.
 *
 * THE PROBLEM WITH THE SEARCH WORDPRESS GIVES YOU
 *
 * Core searches three columns: the title, the excerpt and the post content. On
 * an ordinary blog that is most of the article. On a farm it is the name and the
 * description — and everything a buyer actually types is somewhere else:
 *
 *   "sable"        is a species term
 *   "limpopo"      is a province term
 *   "waterberg"    is a region term, and often the map location as well
 *   "lodge"        is in the Improvements field
 *   "sweetveld"    is in the Wildlife & vegetation field
 *   "cattle"       is the category
 *
 * Every one of those returns nothing from an unmodified WordPress search, and
 * the visitor concludes the site has no such farms rather than that the search
 * is narrow. This class widens it to the whole record: the four written
 * sections, the map location, and the name of every term on every axis the
 * filter panel offers.
 *
 * HOW, WITHOUT WRECKING THE QUERY
 *
 * The obvious implementation JOINs postmeta and the term tables onto the search.
 * That multiplies rows — a farm with ten species becomes ten rows — which then
 * needs DISTINCT, which throws away the relevance ordering and makes found_posts
 * unreliable. So the extra places are matched with IN(SELECT …) subqueries
 * instead: one row per farm, no DISTINCT, and core's own relevance ordering
 * (title matches first) survives untouched.
 *
 * Multi-word searches keep core's meaning: every word must appear SOMEWHERE in
 * the record, not necessarily in the same field. "limpopo sable" finds a
 * Limpopo farm carrying sable, which is the whole point.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Search {

	/** Written fields worth reading. Numbers and the video link are not. */
	public function meta_keys() {
		/**
		 * Filter the meta fields a keyword search looks in.
		 *
		 * @param array $keys Meta keys.
		 */
		return (array) apply_filters( 'acreage_search_meta_keys', array(
			'acreage_improvements',
			'acreage_wildlife',
			'acreage_land_claims',
			'acreage_map',
		) );
	}

	/** Every axis the filter panel offers, so a keyword reaches all of them. */
	public function taxonomies() {
		$taxonomies = class_exists( 'Acreage_Core_Filters' )
			? Acreage_Core_Filters::taxonomies()
			: array( 'listing_category', 'province', 'region', 'species', 'status' );

		/**
		 * Filter the taxonomies a keyword search looks in.
		 *
		 * @param array $taxonomies Taxonomy names.
		 */
		return (array) apply_filters( 'acreage_search_taxonomies', $taxonomies );
	}

	public function __construct() {
		add_filter( 'posts_search', array( $this, 'widen' ), 10, 2 );
	}

	/**
	 * Is this query asking for farms?
	 *
	 * Deliberately narrow. A search that has not named the post type is the
	 * site-wide one — pages, posts and farms together — and rewriting that would
	 * mean every page on the site being matched against farm meta it does not
	 * have. The header search names the type, and so does the grid.
	 *
	 * @param WP_Query $query Query being built.
	 * @return bool
	 */
	private function is_farm_query( $query ) {
		if ( ! post_type_exists( Acreage_Core_Post_Types::POST_TYPE ) ) {
			return false;
		}

		$types = (array) $query->get( 'post_type' );

		return in_array( Acreage_Core_Post_Types::POST_TYPE, $types, true );
	}

	/**
	 * Replace core's search clause with one that reads the whole record.
	 *
	 * @param string   $search The " AND ((post_title LIKE …))" core built.
	 * @param WP_Query $query  Query being built.
	 * @return string
	 */
	public function widen( $search, $query ) {
		global $wpdb;

		/*
		 * NOT a plain is_admin() check.
		 *
		 * admin-ajax.php sets WP_ADMIN, so is_admin() is true for the grid's own
		 * live-filter requests — every refetch after the first paint. Guarding
		 * on it alone meant a search worked on page load and then collapsed to
		 * "No farms match" the moment the visitor touched a filter, because the
		 * AJAX round trip silently fell back to the narrow core search.
		 *
		 * What is actually being kept out is the wp-admin farm list, where the
		 * client searching for a farm to edit wants the title, not every farm
		 * whose Improvements field mentions a borehole.
		 */
		if ( '' === $search || ( is_admin() && ! wp_doing_ajax() ) || ! $query->is_search() || ! $this->is_farm_query( $query ) ) {
			return $search;
		}

		$terms = $query->get( 'search_terms' );

		// A quoted "sentence" search, or a phrase core chose not to split, is
		// one term: the whole string, matched as it stands.
		if ( ! is_array( $terms ) || ! $terms ) {
			$raw   = trim( (string) $query->get( 's' ) );
			$terms = '' === $raw ? array() : array( $raw );
		}

		if ( ! $terms ) {
			return $search;
		}

		/*
		 * Sizes and prices are read off the whole string first, before it is
		 * treated as a list of words. "2 600 ha" is one fact about a farm, not
		 * the three separate words WordPress splits it into — and matched word
		 * by word it finds nothing, because no farm's text contains "2" and
		 * "600" and "ha" as written.
		 */
		$consumed = array();
		$clauses  = $this->measurement_clauses( (string) $query->get( 's' ), $consumed );

		foreach ( $terms as $term ) {
			// A word already accounted for as part of a size or a price must not
			// also have to appear in the text, or "2600 ha" would additionally
			// demand the characters "2600" somewhere in the description.
			$word = strtolower( trim( (string) $term ) );

			if ( in_array( $word, $consumed, true )
				|| in_array( preg_replace( '/[^a-z0-9]+/', '', $word ), $consumed, true ) ) {
				continue;
			}

			$clause = $this->term_clause( (string) $term );

			if ( $clause ) {
				$clauses[] = $clause;
			}
		}

		if ( ! $clauses ) {
			return $search;
		}

		// AND between words, OR between the places each word may appear.
		return ' AND (' . implode( ' AND ', $clauses ) . ') ';
	}

	/* ---------------------------------------------------------- sizes and prices */

	/**
	 * How far either side of a bare number still counts as that number.
	 *
	 * A farm is 2,600 ha and a buyer types "2500" — they are not asking for a
	 * farm of exactly 2,500 hectares, because no such thing exists to ask for.
	 * They are describing the size of place they want. Matching on equality
	 * gives them nothing and reads as a broken search; a tenth either side gives
	 * them the farm they were looking for and nothing absurd.
	 *
	 * An explicit "under" or "over" is a real boundary and is honoured exactly.
	 */
	const TOLERANCE = 0.1;

	/**
	 * Sizes and prices written into the keyword.
	 *
	 * WHAT THIS UNDERSTANDS
	 *
	 *   2600            about 2,600 — hectares or rand, whichever fits
	 *   2 600 ha        about 2,600 hectares (spaces and commas both work)
	 *   1240 hectares   the same
	 *   R33 000 000     about R33 million
	 *   33m / R33m      the same, written the way people say it
	 *   over 2000 ha    2,000 hectares and up
	 *   under R20m      R20 million and down
	 *
	 * Numbers were left out of the searchable fields to begin with, on the
	 * grounds that a plain LIKE over them is worse than useless: "500" as a
	 * substring matches 1,500 and 25,000 and a price of R500,000,000. Read as
	 * quantities rather than as text they are some of the most useful things a
	 * buyer can type, because size and price are the two facts they arrive with.
	 *
	 * @param string $raw      The keyword as typed.
	 * @param array  $consumed Filled with the words used up here.
	 * @return array SQL clauses.
	 */
	private function measurement_clauses( $raw, array &$consumed ) {
		$raw = strtolower( trim( $raw ) );

		if ( '' === $raw || ! preg_match( '/\d/', $raw ) ) {
			return array();
		}

		$number    = '\d[\d\s,]*(?:\.\d+)?';
		$less      = 'under|below|less\s+than|up\s+to|max(?:imum)?|<=?';
		$more      = 'over|above|more\s+than|at\s+least|from|min(?:imum)?|>=?';
		$qualifier = $less . '|' . $more;

		$pattern = '/(?:(' . $qualifier . ')\s*)?(r\s*)?(' . $number . ')\s*(ha\b|hectares?\b|m\b|million\b|k\b)?/i';

		if ( ! preg_match_all( $pattern, $raw, $matches, PREG_SET_ORDER ) ) {
			return array();
		}

		$clauses = array();

		foreach ( $matches as $match ) {
			$value = (float) str_replace( array( ' ', ',' ), '', $match[3] );

			if ( $value <= 0 ) {
				continue;
			}

			$unit = isset( $match[4] ) ? trim( $match[4] ) : '';

			if ( 'm' === $unit || 'million' === $unit ) {
				$value *= 1000000;
			} elseif ( 'k' === $unit ) {
				$value *= 1000;
			}

			/*
			 * Which field a number refers to is usually obvious from how it was
			 * written — "ha" means hectares, an R or a "million" means money.
			 * A bare number is genuinely ambiguous, so it is tried against both
			 * and the tolerance keeps the wrong one from matching: no farm is
			 * priced at two and a half thousand rand.
			 */
			$is_size  = in_array( $unit, array( 'ha', 'hectare', 'hectares' ), true );
			$is_price = ! $is_size && ( '' !== trim( (string) $match[2] ) || in_array( $unit, array( 'm', 'million', 'k' ), true ) );

			$keys = $is_size ? array( 'acreage_hectares' ) : ( $is_price ? array( 'acreage_price' ) : array( 'acreage_hectares', 'acreage_price' ) );

			$operator = '';
			$word     = isset( $match[1] ) ? trim( $match[1] ) : '';

			if ( '' !== $word ) {
				$operator = preg_match( '/^(?:' . $less . ')$/i', $word ) ? '<=' : '>=';
			}

			$parts = array();

			foreach ( $keys as $key ) {
				$parts[] = $this->numeric_clause( $key, $value, $operator );
			}

			$clauses[] = '(' . implode( ' OR ', $parts ) . ')';

			/*
			 * Every word of "over 2 600 ha" is now spoken for.
			 *
			 * Split on punctuation, not just on spaces, because that is what
			 * WordPress does to build its own word list: "2,600 hectares"
			 * reaches us as the three terms 2 / 600 / hectares, and a consumed
			 * list holding the string "2,600" matches none of them — which is
			 * exactly the search that came back empty while "2 600 ha" worked.
			 */
			foreach ( preg_split( '/[^a-z0-9]+/i', trim( $match[0] ) ) as $piece ) {
				if ( '' !== $piece ) {
					$consumed[] = strtolower( $piece );
				}
			}

			// And the number as one run of digits, for a term that arrives whole.
			$consumed[] = preg_replace( '/[^0-9]/', '', $match[3] );
		}

		return $clauses;
	}

	/**
	 * One numeric field against one number.
	 *
	 * @param string $key      Meta key holding the number.
	 * @param float  $value    What was asked for.
	 * @param string $operator '<=', '>=', or '' for "about this much".
	 * @return string Prepared SQL.
	 */
	private function numeric_clause( $key, $value, $operator ) {
		global $wpdb;

		/*
		 * CAST, because meta_value is a text column: compared as strings "9400"
		 * is smaller than "980", and "under 1000 ha" would return the 9,400
		 * hectare farm.
		 */
		$column = "CAST(pm.meta_value AS DECIMAL(20,4))";

		if ( '<=' === $operator || '>=' === $operator ) {
			$test = $wpdb->prepare( "{$column} {$operator} %f", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- operator is one of two literals.
		} else {
			$test = $wpdb->prepare(
				"{$column} BETWEEN %f AND %f", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column is a literal.
				$value * ( 1 - self::TOLERANCE ),
				$value * ( 1 + self::TOLERANCE )
			);
		}

		return $wpdb->prepare(
			"{$wpdb->posts}.ID IN (
				SELECT pm.post_id FROM {$wpdb->postmeta} AS pm
				WHERE pm.meta_key = %s AND pm.meta_value <> '' AND {$test}
			)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $test is already prepared.
			$key
		);
	}

	/**
	 * Everywhere one word is allowed to turn up.
	 *
	 * @param string $term A single search word.
	 * @return string SQL, already prepared, or '' if there is nothing to match.
	 */
	private function term_clause( $term ) {
		global $wpdb;

		$term = trim( $term );

		if ( '' === $term ) {
			return '';
		}

		$like  = '%' . $wpdb->esc_like( $term ) . '%';
		$parts = array();

		$parts[] = $wpdb->prepare(
			"{$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s",
			$like,
			$like,
			$like
		);

		$meta_keys = array_filter( array_map( 'sanitize_key', $this->meta_keys() ) );

		if ( $meta_keys ) {
			$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

			$parts[] = $wpdb->prepare(
				"{$wpdb->posts}.ID IN (
					SELECT post_id FROM {$wpdb->postmeta}
					WHERE meta_key IN ( {$placeholders} ) AND meta_value LIKE %s
				)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
				array_merge( $meta_keys, array( $like ) )
			);
		}

		$taxonomies = array_filter( array_map( 'sanitize_key', $this->taxonomies() ), 'taxonomy_exists' );

		if ( $taxonomies ) {
			$placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

			/*
			 * The slug is matched as well as the name because that is how the
			 * terms are written in a URL a visitor may have pasted back into the
			 * box — "red-hartebeest" should find the same farms as "red
			 * hartebeest", not nothing at all.
			 */
			$parts[] = $wpdb->prepare(
				"{$wpdb->posts}.ID IN (
					SELECT tr.object_id
					FROM {$wpdb->term_relationships} AS tr
					INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
					WHERE tt.taxonomy IN ( {$placeholders} )
					AND ( t.name LIKE %s OR t.slug LIKE %s )
				)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
				array_merge( $taxonomies, array( $like, $like ) )
			);
		}

		return '(' . implode( ' OR ', $parts ) . ')';
	}
}
