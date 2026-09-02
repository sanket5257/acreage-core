<?php
/**
 * The species chips, and the Wikipedia card behind them.
 *
 * WHY THE LOOKUP IS DONE HERE AND NOT IN THE BROWSER
 *
 * Wikipedia's summary endpoint allows cross-origin calls, so the obvious build
 * is a fetch() straight from the visitor's browser. Three things make that the
 * wrong build for this site:
 *
 *   1. Nothing is cached. A page listing fourteen species, opened by two
 *      hundred visitors, is two thousand eight hundred calls to Wikipedia for
 *      fourteen paragraphs of text that change perhaps once a year.
 *   2. The client cannot correct a miss. The term is "Sable"; the article is
 *      "Sable antelope"; the browser has no way to be told that, and the chip
 *      simply shows nothing with no clue why. Going through the server lets a
 *      per-term override live beside the term itself, on the screen where the
 *      client already edits it.
 *   3. Wikipedia asks callers to identify themselves. A browser cannot set its
 *      own User-Agent; PHP can, and does.
 *
 * So the server fetches once, keeps the answer for a fortnight, and every
 * visitor after the first is served from the site's own cache.
 *
 * LICENCE
 *
 * Wikipedia text is CC BY-SA. The card therefore credits Wikipedia and links to
 * the article it quotes — that credit is not decoration and must not be removed.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Species {

	/** The taxonomy this all hangs off. */
	const TAXONOMY = 'species';

	/** Term meta: the Wikipedia article to read, when the name alone won't do. */
	const META_ARTICLE = 'acreage_species_wikipedia';

	/** Term meta: do not show a card for this one at all. */
	const META_OFF = 'acreage_species_no_card';

	/** Cache prefix. Keyed on the article, so editing the override refreshes it. */
	const CACHE_PREFIX = 'acreage_wiki_';

	/** A good answer is worth keeping; a bad one is worth retrying sooner. */
	const CACHE_HIT  = 1209600; // 14 days.
	const CACHE_MISS = 3600;    // 1 hour.

	/** Wikipedia in the site's language, falling back to English. */
	const DEFAULT_LANG = 'en';

	/** Longer than this and the card stops being a glance. */
	const EXTRACT_WORDS = 46;

	public function __construct() {
		add_action( 'wp_ajax_acreage_species', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_acreage_species', array( $this, 'ajax' ) );

		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'add_field' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'edit_field' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save' ) );
	}

	/* ---------------------------------------------------------- the article */

	/**
	 * Which Wikipedia article describes this term.
	 *
	 * The client may paste a whole URL rather than a title, because that is
	 * what you have in your hand after checking the article is the right one.
	 * Both are accepted; a URL is reduced to its last path segment.
	 *
	 * @param WP_Term|int $term Species term.
	 * @return string Article title, or '' when this term is opted out.
	 */
	public static function article( $term ) {
		$term = get_term( $term, self::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		if ( get_term_meta( $term->term_id, self::META_OFF, true ) ) {
			return '';
		}

		$override = trim( (string) get_term_meta( $term->term_id, self::META_ARTICLE, true ) );

		if ( '' === $override ) {
			return self::known( $term->name );
		}

		if ( preg_match( '#^https?://#i', $override ) ) {
			$path     = (string) wp_parse_url( $override, PHP_URL_PATH );
			$override = rawurldecode( basename( $path ) );
		}

		return str_replace( '_', ' ', $override );
	}

	/**
	 * What a game farmer calls an animal, and what Wikipedia files it under.
	 *
	 * WHY THIS LIST EXISTS
	 *
	 * The names on a South African game list are the names people use, and half
	 * of them are not the names of articles. "Buffalo" is a city in New York.
	 * "Eland", "Bushbuck" and "Roan" are disambiguation pages. "Sable" is a
	 * heraldic tincture. Left to look themselves up, a third of a typical
	 * species list would quietly show no card at all, and the client would have
	 * to go and find each article by hand before the feature did anything.
	 *
	 * So the common game of the region is mapped once, here, and a site works
	 * out of the box. The per-term field still overrides this, and the filter
	 * lets a customer selling elsewhere replace the lot.
	 *
	 * The colour variants a breeder sells — golden wildebeest, black impala —
	 * have no article of their own and are pointed at the animal they are a
	 * variant of, which is the honest answer rather than no answer.
	 */
	public static function known( $name ) {
		$map = array(
			'buffalo'            => 'African buffalo',
			'cape buffalo'       => 'African buffalo',
			'bushbuck'           => 'Cape bushbuck',
			'eland'              => 'Common eland',
			'livingstone eland'  => 'Common eland',
			'hippo'              => 'Hippopotamus',
			'kudu'               => 'Greater kudu',
			'roan'               => 'Roan antelope',
			'sable'              => 'Sable antelope',
			'rhino'              => 'Rhinoceros',
			'white rhino'        => 'White rhinoceros',
			'black rhino'        => 'Black rhinoceros',
			'wildebeest'         => 'Wildebeest',
			'golden wildebeest'  => 'Blue wildebeest',
			'king wildebeest'    => 'Blue wildebeest',
			'black impala'       => 'Impala',
			'saddleback impala'  => 'Impala',
			'copper springbok'   => 'Springbok',
			'black springbok'    => 'Springbok',
			'white springbok'    => 'Springbok',
			'warthog'            => 'Common warthog',
			'zebra'              => 'Plains zebra',
			"burchell's zebra"   => 'Plains zebra',
			'burchells zebra'    => 'Plains zebra',
			'mountain zebra'     => 'Mountain zebra',
			'giraffe'            => 'Giraffe',
			'red hartebeest'     => 'Red hartebeest',
			'hartebeest'         => 'Hartebeest',
			'reedbuck'           => 'Southern reedbuck',
			'mountain reedbuck'  => 'Mountain reedbuck',
			'rhebok'             => 'Grey rhebok',
			'grey rhebok'        => 'Grey rhebok',
			'grysbok'            => 'Cape grysbok',
			'duiker'             => 'Duiker',
			'blue duiker'        => 'Blue duiker',
			'grey duiker'        => 'Common duiker',
			'common duiker'      => 'Common duiker',
			'jackal'             => 'Black-backed jackal',
			'hyena'              => 'Brown hyena',
			'brown hyena'        => 'Brown hyena',
			'spotted hyena'      => 'Spotted hyena',
			'lynx'               => 'Caracal',
			'rooikat'            => 'Caracal',
			'ostrich'            => 'Common ostrich',
			'crocodile'          => 'Nile crocodile',
			'baboon'             => 'Chacma baboon',
			'monkey'             => 'Vervet monkey',
			'vervet monkey'      => 'Vervet monkey',
			'porcupine'          => 'Cape porcupine',
			'elephant'           => 'African bush elephant',
			'blesbok'            => 'Blesbok',
			'oryx'               => 'Gemsbok',
			'nyala'              => 'Lowland nyala',
			'bush pig'           => 'Bushpig',
		);

		/**
		 * Filter the name-to-article map.
		 *
		 * @param array  $map  Lowercased species name => Wikipedia article.
		 * @param string $name The name being looked up.
		 */
		$map = (array) apply_filters( 'acreage_species_wikipedia_articles', $map, $name );

		$key = strtolower( trim( $name ) );

		return isset( $map[ $key ] ) ? $map[ $key ] : $name;
	}

	/** Wikipedia edition to read, from the site's own language. */
	private static function language() {
		$code = strtolower( (string) apply_filters( 'acreage_species_wikipedia_language', substr( get_locale(), 0, 2 ) ) );

		return preg_match( '/^[a-z]{2,3}$/', $code ) ? $code : self::DEFAULT_LANG;
	}

	/* ------------------------------------------------------------ the fetch */

	/**
	 * The card's contents for one species.
	 *
	 * @param int $term_id Species term.
	 * @return array|false Card data, or false when there is nothing to show.
	 */
	public static function summary( $term_id ) {
		$article = self::article( $term_id );

		if ( '' === $article ) {
			return false;
		}

		$lang = self::language();
		$key  = self::CACHE_PREFIX . md5( $lang . '|' . $article );
		$hit  = get_transient( $key );

		/*
		 * A miss is cached as the string 'none'. A cached false is
		 * indistinguishable from "nothing cached", so storing false would send
		 * the site back to Wikipedia on every single hover over a species that
		 * has no article — the one case where the traffic is pure waste.
		 */
		if ( 'none' === $hit ) {
			return false;
		}

		if ( is_array( $hit ) ) {
			return $hit;
		}

		$card = self::fetch( $article, $lang );

		set_transient( $key, $card ? $card : 'none', $card ? self::CACHE_HIT : self::CACHE_MISS );

		return $card;
	}

	/**
	 * Ask Wikipedia. Called once per species per fortnight, never per visitor.
	 *
	 * @param string $article Article title.
	 * @param string $lang    Wikipedia edition.
	 * @return array|false
	 */
	private static function fetch( $article, $lang ) {
		$url = sprintf(
			'https://%s.wikipedia.org/api/rest_v1/page/summary/%s?redirect=true',
			rawurlencode( $lang ),
			rawurlencode( str_replace( ' ', '_', $article ) )
		);

		$response = wp_remote_get( $url, array(
			'timeout'    => 6,
			'user-agent' => 'AcreageCore/' . ACREAGE_CORE_VERSION . ' (' . home_url( '/' ) . ')',
			'headers'    => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['extract'] ) ) {
			return false;
		}

		/*
		 * A disambiguation page is a list of links, not a description — "Sable
		 * may refer to: a mammal, a heraldic tincture, a river in Ontario". Show
		 * nothing and let the client point the term at the right article, which
		 * is exactly what the override field is for.
		 */
		if ( isset( $data['type'] ) && 'standard' !== $data['type'] ) {
			return false;
		}

		$image  = '';
		$width  = 0;
		$height = 0;

		if ( ! empty( $data['thumbnail']['source'] ) ) {
			$image  = $data['thumbnail']['source'];
			$width  = isset( $data['thumbnail']['width'] ) ? (int) $data['thumbnail']['width'] : 0;
			$height = isset( $data['thumbnail']['height'] ) ? (int) $data['thumbnail']['height'] : 0;
		} elseif ( ! empty( $data['originalimage']['source'] ) ) {
			$image = $data['originalimage']['source'];
		}

		// Only Wikimedia's own image host, whatever the payload claims — the
		// card puts this straight into an <img src>, so it is not taken on trust.
		if ( $image && ! preg_match( '#^https://upload\.wikimedia\.org/#', $image ) ) {
			$image  = '';
			$width  = 0;
			$height = 0;
		}

		$link = '';
		if ( ! empty( $data['content_urls']['desktop']['page'] ) ) {
			$link = $data['content_urls']['desktop']['page'];
		}

		if ( $link && ! preg_match( '#^https://[a-z-]{2,12}\.(m\.)?wikipedia\.org/#', $link ) ) {
			$link = '';
		}

		return array(
			'title'   => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : $article ),
			'extract' => wp_trim_words( sanitize_text_field( $data['extract'] ), self::EXTRACT_WORDS, '…' ),
			'image'   => esc_url_raw( $image ),
			'width'   => $width,
			'height'  => $height,
			'url'     => esc_url_raw( $link ),
		);
	}

	/** Forget what we know about a term, so the next hover fetches it again. */
	public static function forget( $term_id ) {
		$article = self::article( $term_id );

		if ( '' === $article ) {
			return;
		}

		foreach ( array_unique( array( self::language(), self::DEFAULT_LANG ) ) as $lang ) {
			delete_transient( self::CACHE_PREFIX . md5( $lang . '|' . $article ) );
		}
	}

	/* ------------------------------------------------------------- the AJAX */

	/**
	 * NO NONCE, DELIBERATELY
	 *
	 * This returns a public paragraph about a public animal, keyed on a term id
	 * that is already printed in the page's HTML. There is nothing here to
	 * forge. A nonce would buy no security and would cost the one thing that
	 * matters on a brochure site: a page served from a cache carries a nonce
	 * minted hours ago, and every hover on it would fail with no way for the
	 * visitor to tell why. The request cannot reach Wikipedia with anything the
	 * visitor chose either — the term must already exist in this taxonomy, and
	 * the article comes from the term, never from the request.
	 */
	public function ajax() {
		$term_id = isset( $_GET['term'] ) ? absint( $_GET['term'] ) : 0;
		$term    = $term_id ? get_term( $term_id, self::TAXONOMY ) : null;

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'No such species.', 'acreage' ) ), 404 );
		}

		$card = self::summary( $term_id );

		/*
		 * "There is no card for this one" is a normal answer, not a failure, so
		 * it goes back as a success with nothing in it. The browser remembers
		 * that and stops asking for the rest of the visit.
		 */
		if ( ! $card ) {
			wp_send_json_success( array( 'card' => null ) );
		}

		$card['name'] = $term->name;

		wp_send_json_success( array( 'card' => $card ) );
	}

	/* ------------------------------------------------------------ the admin */

	/** The article field, on the Add-species form. */
	public function add_field() {
		?>
		<div class="form-field">
			<label for="acreage-species-wikipedia"><?php esc_html_e( 'Wikipedia article', 'acreage' ); ?></label>
			<input type="text" name="<?php echo esc_attr( self::META_ARTICLE ); ?>" id="acreage-species-wikipedia" value="" />
			<p><?php echo esc_html( $this->hint() ); ?></p>
		</div>
		<?php
	}

	/** The same field, plus the off switch, on the Edit-species screen. */
	public function edit_field( $term ) {
		$article = (string) get_term_meta( $term->term_id, self::META_ARTICLE, true );
		$off     = (bool) get_term_meta( $term->term_id, self::META_OFF, true );
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="acreage-species-wikipedia"><?php esc_html_e( 'Wikipedia article', 'acreage' ); ?></label>
			</th>
			<td>
				<input type="text" name="<?php echo esc_attr( self::META_ARTICLE ); ?>" id="acreage-species-wikipedia" value="<?php echo esc_attr( $article ); ?>" />
				<p class="description"><?php echo esc_html( $this->hint() ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Hover card', 'acreage' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::META_OFF ); ?>" value="1" <?php checked( $off ); ?> />
					<?php esc_html_e( 'Do not show a card for this species.', 'acreage' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	private function hint() {
		return __( 'Leave this empty and the name above is looked up as it stands. Fill it in when the name alone finds the wrong page — "Sable" needs "Sable antelope". A pasted Wikipedia link works too.', 'acreage' );
	}

	/**
	 * Save both fields.
	 *
	 * Runs on created_ and edited_, by which point WordPress has already checked
	 * the nonce on the term screen. The capability is checked again here because
	 * these hooks also fire from wp_insert_term() calls made elsewhere.
	 */
	public function save( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		if ( isset( $_POST[ self::META_ARTICLE ] ) ) {
			$article = sanitize_text_field( wp_unslash( $_POST[ self::META_ARTICLE ] ) );

			/*
			 * Cleared under the OLD article, before the new one is saved. The
			 * cache key is the article, so clearing afterwards would delete the
			 * key for the corrected name — which was never cached — and leave
			 * the wrong answer sitting there for another fortnight.
			 */
			self::forget( $term_id );

			if ( '' === $article ) {
				delete_term_meta( $term_id, self::META_ARTICLE );
			} else {
				update_term_meta( $term_id, self::META_ARTICLE, $article );
			}
		}

		if ( empty( $_POST[ self::META_OFF ] ) ) {
			delete_term_meta( $term_id, self::META_OFF );
		} else {
			update_term_meta( $term_id, self::META_OFF, 1 );
		}
	}
}
