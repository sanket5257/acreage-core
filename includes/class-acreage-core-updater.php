<?php
/**
 * Self-contained GitHub Releases updater for the plugin.
 *
 * Same idea as the theme's updater, but the plugin side of WordPress differs in
 * three ways that matter:
 *
 *  - the transient is keyed by plugin basename ("acreage-core/acreage-core.php")
 *    rather than a folder name;
 *  - the response must be an object, not an array;
 *  - "View details" goes through the plugins_api filter, so we answer that too
 *    rather than letting WordPress ask wordpress.org about a plugin it has
 *    never heard of.
 */

defined( 'ABSPATH' ) || exit;

class Acreage_Core_Updater {

	/** @var string acreage-core/acreage-core.php */
	private $basename;

	/** @var string Folder name — must match the folder inside the release zip. */
	private $slug;

	/** @var string owner/repo */
	private $repo;

	/** @var string */
	private $token;

	/** @var string */
	private $cache_key;

	/** @var int */
	private $cache_ttl = 6 * HOUR_IN_SECONDS;

	public function __construct( $repo ) {
		$this->repo      = trim( $repo, '/ ' );
		$this->basename  = ACREAGE_CORE_BASENAME;
		$this->slug      = dirname( $this->basename );
		$this->token     = defined( 'ACREAGE_CORE_GITHUB_TOKEN' ) ? ACREAGE_CORE_GITHUB_TOKEN : '';
		$this->cache_key = 'acreage_core_release_' . md5( $this->repo );

		add_filter( 'site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
	}

	public function installed_version() {
		return ACREAGE_CORE_VERSION;
	}

	public function slug() {
		return $this->slug;
	}

	/* ---------------------------------------------------------------- API */

	/**
	 * @return array Release data, or array( 'error' => 'why' ).
	 */
	public function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( $this->cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'acreage-listings-updater',
			),
		);
		if ( $this->token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		$response = wp_remote_get( "https://api.github.com/repos/{$this->repo}/releases/latest", $args );

		if ( is_wp_error( $response ) ) {
			return $this->cache_error( $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 404 === $code ) {
			return $this->cache_error( __( 'no published release yet (or the repo is private and no token is set)', 'acreage' ) );
		}
		if ( 403 === $code ) {
			return $this->cache_error( __( 'GitHub API rate limit reached — try again shortly', 'acreage' ) );
		}
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			return $this->cache_error( sprintf( __( 'GitHub returned HTTP %d', 'acreage' ), $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['tag_name'] ) ) {
			return $this->cache_error( __( 'the latest release has no tag name', 'acreage' ) );
		}

		$release = array(
			'version' => ltrim( $body['tag_name'], 'vV' ),
			'package' => $this->pick_package( $body ),
			'url'     => isset( $body['html_url'] ) ? $body['html_url'] : "https://github.com/{$this->repo}",
			'notes'   => isset( $body['body'] ) ? (string) $body['body'] : '',
			'date'    => isset( $body['published_at'] ) ? $body['published_at'] : '',
			'asset'   => false,
		);

		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! empty( $asset['name'] ) && '.zip' === strtolower( substr( $asset['name'], -4 ) ) ) {
					$release['asset'] = true;
					break;
				}
			}
		}

		if ( empty( $release['package'] ) ) {
			return $this->cache_error( __( 'the release has no downloadable package', 'acreage' ) );
		}

		set_site_transient( $this->cache_key, $release, $this->cache_ttl );

		return $release;
	}

	private function cache_error( $message ) {
		$payload = array( 'error' => $message );
		set_site_transient( $this->cache_key, $payload, 15 * MINUTE_IN_SECONDS );
		return $payload;
	}

	private function pick_package( $body ) {
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && '.zip' === strtolower( substr( $asset['name'], -4 ) ) ) {
					return $asset['browser_download_url'];
				}
			}
		}
		return isset( $body['zipball_url'] ) ? $body['zipball_url'] : '';
	}

	/* ------------------------------------------------------------- Filters */

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$release = $this->get_release();

		if ( isset( $release['error'] ) ) {
			return $transient;
		}

		$item = (object) array(
			'id'          => $this->repo,
			'slug'        => $this->slug,
			'plugin'      => $this->basename,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['package'],
			'tested'      => get_bloginfo( 'version' ),
			'icons'       => array(),
			'banners'     => array(),
		);

		if ( version_compare( $release['version'], $this->installed_version(), '>' ) ) {
			$transient->response[ $this->basename ] = $item;
			unset( $transient->no_update[ $this->basename ] );
		} else {
			$transient->no_update[ $this->basename ] = $item;
		}

		return $transient;
	}

	/** Answer the "View details" modal ourselves. */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->get_release();

		if ( isset( $release['error'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Acreage Core',
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => 'Web Team',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'last_updated'  => $release['date'],
			'sections'      => array(
				'description' => __( 'Owns the farm listings — post type, taxonomies, fields and the combined filter. Independent of the active theme.', 'acreage' ),
				'changelog'   => $release['notes'] ? wpautop( wp_kses_post( $release['notes'] ) ) : __( 'See the release on GitHub.', 'acreage' ),
			),
		);
	}

	/**
	 * GitHub zipballs extract to "owner-repo-a1b2c3/". Rename to the plugin's own
	 * folder or the update installs a second copy alongside the first.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $extra = array() ) {
		global $wp_filesystem;

		if ( empty( $extra['plugin'] ) || $extra['plugin'] !== $this->basename ) {
			return $source;
		}
		if ( ! $wp_filesystem || basename( $source ) === $this->slug ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . $this->slug;

		if ( $wp_filesystem->move( $source, $corrected, true ) ) {
			return trailingslashit( $corrected );
		}

		return new WP_Error(
			'acreage_core_rename_failed',
			/* translators: %s: plugin folder name. */
			sprintf( __( 'Could not rename the downloaded plugin folder to "%s".', 'acreage' ), $this->slug )
		);
	}

	/* ------------------------------------------------------------ Cache mgmt */

	public function flush_cache( $upgrader, $options ) {
		if ( isset( $options['type'] ) && 'plugin' === $options['type'] ) {
			delete_site_transient( $this->cache_key );
		}
	}

	public function handle_manual_check() {
		if ( empty( $_GET['acreage-listings-check'] ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'acreage-listings-check' ) ) {
			return;
		}

		delete_site_transient( $this->cache_key );
		$this->get_release( true );
		delete_site_transient( 'update_plugins' );

		wp_safe_redirect( admin_url( 'plugins.php?acreage-listings-checked=1' ) );
		exit;
	}
}
