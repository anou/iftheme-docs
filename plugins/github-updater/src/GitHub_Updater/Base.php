<?php
/**
 * GitHub Updater
 *
 * @package   Fragen\GitHub_Updater
 * @author    Andy Fragen
 * @author    Gary Jones
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 */

namespace Fragen\GitHub_Updater;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Update a WordPress plugin or theme from a Git-based repo.
 *
 * Class    Base
 * @package Fragen\GitHub_Updater
 * @author  Andy Fragen
 * @author  Gary Jones
 */
class Base {

	/**
	 * Store details of all repositories that are installed.
	 *
	 * @var object
	 */
	protected $config;

	/**
	 * Class Object for API.
	 *
	 * @var object
	 */
 	protected $repo_api;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected static $hours;

	/**
	 * Variable for holding transient ids.
	 *
	 * @var array
	 */
	protected static $transients = array();

	/**
	 * Variable for holding extra theme and plugin headers.
	 *
	 * @var array
	 */
	protected static $extra_headers = array();

	/**
	 * Holds the values to be used in the fields callbacks.
	 *
	 * @var array
	 */
	protected static $options;

	/**
	 * Holds the values for remote management settings.
	 *
	 * @var mixed
	 */
	protected static $options_remote;

	/**
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type-repo => $code )
	 */
	protected static $error_code = array();

	/**
	 * Holds git server types.
	 *
	 * @var array
	 */
	protected static $git_servers = array(
		'github'    => 'GitHub',
		'bitbucket' => 'Bitbucket',
		'gitlab'    => 'GitLab',
	);

	/**
	 * Holds extra repo header types.
	 *
	 * @var array
	 */
	protected static $extra_repo_headers = array(
		'branch'     => 'Branch',
		'enterprise' => 'Enterprise',
		'gitlab_ce'  => 'CE',
	);

	/**
	 * Constructor.
	 * Loads options to private static variable.
	 */
	public function __construct() {
		self::$options        = get_site_option( 'github_updater', array() );
		self::$options_remote = get_site_option( 'github_updater_remote_management', array() );
		$this->add_headers();

		/*
		 * Calls in init hook for user capabilities.
		 */
		add_action( 'init', array( &$this, 'init' ) );
		add_action( 'init', array( &$this, 'background_update' ) );
		add_action( 'init', array( &$this, 'token_distribution' ) );

		add_filter( 'http_request_args', array( 'Fragen\\GitHub_Updater\\API', 'http_request_args' ), 10, 2 );
	}

	/**
	 * Instantiate Plugin, Theme, and Settings for proper user capabilities.
	 *
	 * @return bool
	 */
	public function init() {
		global $pagenow;

		// Set $force_meta_update = true on appropriate admin pages.
		$force_meta_update = false;
		$admin_pages  = array(
			'plugins.php', 'plugin-install.php',
			'themes.php', 'theme-install.php',
			'update-core.php', 'update.php',
			'options-general.php', 'settings.php',
		);
		foreach ( array_keys( Settings::$remote_management ) as $key ) {
			// Remote management only needs to be active for admin pages.
			if ( is_admin() && ! empty( self::$options_remote[ $key ] ) ) {
				$admin_pages = array_merge( $admin_pages, array( 'index.php' ) );
			}
		}

		if ( in_array( $pagenow, array_unique( $admin_pages ) ) ) {
			$force_meta_update = true;
		}

		if ( current_user_can( 'update_plugins' ) ) {
			Plugin::$object = Plugin::instance();
			if ( $force_meta_update ) {
				Plugin::$object->get_remote_plugin_meta();
			}
		}
		if ( current_user_can( 'update_themes' ) ) {
			Theme::$object = Theme::instance();
			if ( $force_meta_update ) {
				Theme::$object->get_remote_theme_meta();
			}
		}
		if ( is_admin() &&
		     ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) &&
		     ! apply_filters( 'github_updater_hide_settings', false )
		) {
			new Settings();
		}

		return true;
	}

	/**
	 * Piggyback on built-in update function to get metadata.
	 */
	public function background_update() {
		add_action( 'wp_update_plugins', array( &$this, 'forced_meta_update_plugins' ) );
		add_action( 'wp_update_themes', array( &$this, 'forced_meta_update_themes' ) );
		add_action( 'wp_ajax_nopriv_ithemes_sync_request', array( &$this, 'forced_meta_update_remote_management' ) );
	}

	/**
	 * Performs actual plugin metadata fetching.
	 */
	public function forced_meta_update_plugins() {
		Plugin::$object = Plugin::instance();
		Plugin::$object->get_remote_plugin_meta();
	}

	/**
	 * Performs actual theme metadata fetching.
	 */
	public function forced_meta_update_themes() {
		Theme::$object = Theme::instance();
		Theme::$object->get_remote_theme_meta();
	}

	/**
	 * Calls $this->forced_meta_update_plugins() and $this->forced_meta_update_themes()
	 * for remote management services.
	 */
	public function forced_meta_update_remote_management() {
		$this->forced_meta_update_plugins();
		$this->forced_meta_update_themes();
	}

	/**
	 * Allows developers to use 'github_updater_token_distribution' hook to set GitHub Access Tokens.
	 * Saves results of filter hook to self::$options.
	 *
	 * Hook requires return of single element array.
	 * $key === repo-name and $value === token
	 * e.g.  array( 'repo-name' => 'access_token' );
	 */
	public function token_distribution() {
		$config = apply_filters( 'github_updater_token_distribution', array() );
		if ( ! empty( $config ) && 1 === count( $config ) ) {
			$config        = Settings::sanitize( $config );
			self::$options = array_merge( get_site_option( 'github_updater' ), $config );
			update_site_option( 'github_updater', self::$options );
		}
	}

	/**
	 * Add extra headers via filter hooks.
	 */
	public function add_headers() {
		add_filter( 'extra_plugin_headers', array( &$this, 'add_plugin_headers' ) );
		add_filter( 'extra_theme_headers', array( &$this, 'add_theme_headers' ) );
	}

	/**
	 * Add extra headers to get_plugins().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_plugin_headers( $extra_headers ) {
		$ghu_extra_headers = array(
			'Requires WP'  => 'Requires WP',
			'Requires PHP' => 'Requires PHP',
		);

		foreach ( self::$git_servers as $server ) {
			$ghu_extra_headers[ $server . ' Plugin URI' ] = $server . ' Plugin URI';
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Add extra headers to wp_get_themes().
	 *
	 * @param $extra_headers
	 *
	 * @return array
	 */
	public function add_theme_headers( $extra_headers ) {
		$ghu_extra_headers = array(
			'Requires WP'  => 'Requires WP',
			'Requires PHP' => 'Requires PHP',
		);

		foreach ( self::$git_servers as $server ) {
			$ghu_extra_headers[ $server . ' Theme URI' ] = $server . ' Theme URI';
			foreach ( self::$extra_repo_headers as $header ) {
				$ghu_extra_headers[ $server . ' ' . $header ] = $server . ' ' . $header;
			}
		}

		self::$extra_headers = array_unique( array_merge( self::$extra_headers, $ghu_extra_headers ) );
		$extra_headers       = array_merge( (array) $extra_headers, (array) $ghu_extra_headers );

		return $extra_headers;
	}

	/**
	 * Set default values for plugin/theme.
	 *
	 * @param $type
	 */
	protected function set_defaults( $type ) {
		if ( ! isset( self::$options['branch_switch'] ) ) {
			self::$options['branch_switch']      = null;
		}
		if ( ! isset( self::$options[ $this->$type->repo ] ) ) {
			self::$options[ $this->$type->repo ] = null;
			add_site_option( 'github_updater', self::$options );
		}

		$this->$type->remote_version        = '0.0.0';
		$this->$type->newest_tag            = '0.0.0';
		$this->$type->download_link         = null;
		$this->$type->tags                  = array();
		$this->$type->rollback              = array();
		$this->$type->branches              = array();
		$this->$type->requires              = null;
		$this->$type->tested                = null;
		$this->$type->donate                = null;
		$this->$type->contributors          = array();
		$this->$type->downloaded            = 0;
		$this->$type->last_updated          = null;
		$this->$type->rating                = 0;
		$this->$type->num_ratings           = 0;
		$this->$type->transient             = array();
		$this->$type->repo_meta             = array();
		$this->$type->private               = true;
		$this->$type->watchers              = 0;
		$this->$type->forks                 = 0;
		$this->$type->open_issues           = 0;
		$this->$type->score                 = 0;
		$this->$type->requires_wp_version   = '3.8.0';
		$this->$type->requires_php_version  = '5.3';
	}

	/**
	 * Use upgrader_post_install hook to ensure correct directory name.
	 *
	 * @global $wp_filesystem \WP_Filesystem_Direct
	 * @param $true
	 * @param array $extra_hook
	 * @param array $result
	 *
	 * @return mixed
	 */
	public function upgrader_post_install( $true, $extra_hook, $result ) {
		global $wp_filesystem;
		$slug              = null;
		$is_plugin_active  = false;
		$is_network_active = false;

		if ( ( $this instanceof Plugin && isset( $extra_hook['theme'] ) ) ||
		     ( $this instanceof Plugin && in_array( 'theme', $extra_hook ) ) ||
		     ( $this instanceof Theme && isset( $extra_hook['plugin'] ) ) ||
		     ( $this instanceof Theme && in_array( 'plugin', $extra_hook ) )
		) {
			return $result;
		}

		/*
		 * Use $extra_hook to derive repo, safer.
		 */
		if ( $this instanceof Plugin && isset( $extra_hook['plugin'] ) ) {
			$slug              = dirname( $extra_hook['plugin'] );
			$is_plugin_active  = is_plugin_active( $extra_hook['plugin'] ) ? true : false;
			$is_network_active = is_plugin_active_for_network( $extra_hook['plugin'] ) ? true : false;
		} elseif ( $this instanceof Theme && isset( $extra_hook['theme'] ) ) {
			$slug = $extra_hook['theme'];
		}

		$repo = $this->get_repo_slugs( $slug );

		/*
		 * Not GitHub Updater plugin/theme.
		 */
		if ( ! isset( $_POST['github_updater_repo'] ) && empty( $repo ) ) {
			return $result;
		}

		if ( isset( self::$options['github_updater_install_repo'] ) ) {
			$proper_destination = trailingslashit( $result['local_destination'] ) . self::$options['github_updater_install_repo'];
		} else {
			$proper_destination = $this->config[ $repo['repo'] ]->local_path;
		}

		/*
		 * Extended naming.
		 * Only for plugins and not for 'master' === branch && .org hosted.
		 */
		if ( isset( $extra_hook['plugin'] ) &&
			( defined( 'GITHUB_UPDATER_EXTENDED_NAMING' ) && GITHUB_UPDATER_EXTENDED_NAMING ) &&
		     ( ! $this->config[ $repo['repo'] ]->dot_org ||
		       ( $this->tag && 'master' !== $this->tag ) )
		) {
			$proper_destination = $this->config[ $repo['repo'] ]->local_path_extended;
			printf(
				esc_html__( 'Rename successful using extended name to %1$s', 'github-updater' ) . '&#8230;<br>',
				'<strong>' . $this->config[ $repo['repo'] ]->extended_repo . '</strong>'
			);
		}

		$wp_filesystem->move( $result['destination'], $proper_destination );
		$result['destination']       = $proper_destination;
		$result['clear_destination'] = true;

		/*
		 * Reactivate plugin if active.
		 */
		if ( $is_plugin_active ) {
			activate_plugin( WP_PLUGIN_DIR . '/' . $extra_hook['plugin'], null, $is_network_active );
		}

		return $result;
	}

	/**
	 * Set array with normal and extended repo names.
	 * Fix name even if installed without renaming originally.
	 *
	 * @param $slug
	 *
	 * @return array
	 */
	protected function get_repo_slugs( $slug ) {
		$arr    = array();
		$rename = explode( '-', $slug );
		array_pop( $rename );
		$rename = implode( '-', $rename );

		foreach ( $this->config as $repo ) {
			if ( $slug === $repo->repo ||
			     $slug === $repo->extended_repo ||
			     $rename === $repo->owner . '-' . $repo->repo
			) {
				$arr['repo']          = $repo->repo;
				$arr['extended_repo'] = $repo->extended_repo;
				break;
			}
		}

		return $arr;
	}

	/**
	 * Take remote file contents as string and parse headers.
	 *
	 * @param $contents
	 * @param $type
	 *
	 * @return array
	 */
	protected function get_file_headers( $contents, $type ) {

		$default_plugin_headers = array(
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
		);

		$default_theme_headers = array(
			'Name'        => 'Theme Name',
			'ThemeURI'    => 'Theme URI',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'Version'     => 'Version',
			'Template'    => 'Template',
			'Status'      => 'Status',
			'Tags'        => 'Tags',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
		);

		if ( false !== strpos( $type, 'plugin' ) ) {
			$all_headers = $default_plugin_headers;
		}

		if ( false !== strpos( $type, 'theme' ) ) {
			$all_headers = $default_theme_headers;
		}

		/*
		 * Make sure we catch CR-only line endings.
		 */
		$file_data = str_replace( "\r", "\n", $contents );

		/*
		 * Merge extra headers and default headers.
		 */
		$all_headers = array_merge( self::$extra_headers, (array) $all_headers );
		$all_headers = array_unique( $all_headers );

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		return $all_headers;
	}

	/**
	 * Get filename of changelog and return.
	 *
	 * @param $type
	 *
	 * @return bool or variable
	 */
	protected function get_changelog_filename( $type ) {
		$changelogs  = array( 'CHANGES.md', 'CHANGELOG.md', 'changes.md', 'changelog.md' );
		$changes     = null;
		$local_files = null;

		if ( is_dir( $this->$type->local_path ) ) {
			$local_files = scandir( $this->$type->local_path );
		} elseif ( is_dir( $this->$type->local_path_extended ) ) {
			$local_files = scandir( $this->$type->local_path_extended );
		}

		$changes = array_intersect( (array) $local_files, $changelogs );
		$changes = array_pop( $changes );

		if ( ! empty( $changes ) ) {
			return $changes;
		}

			return false;
	}


	/**
	 * Function to check if plugin or theme object is able to be updated.
	 *
	 * @param $type
	 *
	 * @return bool
	 */
	public function can_update( $type ) {
		global $wp_version;

		$remote_is_newer = version_compare( $type->remote_version, $type->local_version, '>' );
		$wp_version_ok   = version_compare( $wp_version, $type->requires_wp_version,'>=' );
		$php_version_ok  = version_compare( PHP_VERSION, $type->requires_php_version, '>=' );

		return $remote_is_newer && $wp_version_ok && $php_version_ok;
	}

	/**
	 * Parse URI param returning array of parts.
	 *
	 * @param $repo_header
	 *
	 * @return array
	 */
	protected function parse_header_uri( $repo_header ) {
		$header_parts         = parse_url( $repo_header );
		$header['scheme']     = isset( $header_parts['scheme'] ) ? $header_parts['scheme'] : null;
		$header['host']       = isset( $header_parts['host'] ) ? $header_parts['host'] : null;
		$owner_repo           = trim( $header_parts['path'], '/' );  // strip surrounding slashes
		$owner_repo           = str_replace( '.git', '', $owner_repo ); //strip incorrect URI ending
		$header['path']       = $owner_repo;
		$owner_repo           = explode( '/', $owner_repo );
		$header['owner']      = $owner_repo[0];
		$header['repo']       = $owner_repo[1];
		$header['owner_repo'] = isset( $header['owner'] ) ? $header['owner'] . '/' . $header['repo'] : null;
		$header['base_uri']   = str_replace( $header_parts['path'], '', $repo_header );
		$header['uri']        = isset( $header['scheme'] ) ? trim( $repo_header, '/' ) : null;

		$header = Settings::sanitize( $header );

		return $header;
	}

	/**
	 * Create repo parts.
	 *
	 * @param $repo
	 * @param $type
	 *
	 * @return mixed
	 */
	protected function get_repo_parts( $repo, $type ) {
		$arr['bool'] = false;
		$pattern     = '/' . strtolower( $repo ) . '_/';
		$type        = preg_replace( $pattern, '', $type );
		$repo_types  = array(
			'GitHub'    => 'github_' . $type,
			'Bitbucket' => 'bitbucket_'. $type,
			'GitLab'    => 'gitlab_' . $type,
		);
		$repo_base_uris = array(
			'GitHub'    => 'https://github.com/',
			'Bitbucket' => 'https://bitbucket.org/',
			'GitLab'    => 'https://gitlab.com/',
		);

		if ( array_key_exists( $repo, $repo_types ) ) {
			$arr['type']       = $repo_types[ $repo ];
			$arr['git_server'] = strtolower( $repo );
			$arr['base_uri']   = $repo_base_uris[ $repo ];
			$arr['bool']       = true;
			foreach ( self::$extra_repo_headers as $key => $value ) {
				$arr[ $key ] = $repo . ' ' . $value;
			}
		}

		return $arr;
	}

	/**
	 * Used to set_site_transient and checks/stores transient id in array.
	 *
	 * @param $id
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_transient( $id, $response ) {
		$repo      = isset( $this->type ) ? $this->type->repo : 'ghu';
		$transient = 'ghu-' . md5( $repo . $id );
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}
		set_site_transient( $transient, $response, ( self::$hours * HOUR_IN_SECONDS ) );

		return true;
	}

	/**
	 * Returns site_transient and checks/stores transient id in array.
	 *
	 * @param $id
	 *
	 * @return mixed
	 */
	protected function get_transient( $id ) {
		$repo      = isset( $this->type ) ? $this->type->repo : 'ghu';
		$transient = 'ghu-' . md5( $repo . $id );
		if ( ! in_array( $transient, self::$transients, true ) ) {
			self::$transients[] = $transient;
		}

		return get_site_transient( $transient );
	}

	/**
	 * Delete all transients from array of transient ids.
	 *
	 * @param $type
	 *
	 * @return bool|void
	 */
	protected function delete_all_transients( $type ) {
		$transients = get_site_transient( 'ghu-' . $type );
		if ( ! $transients ) {
			return false;
		}

		foreach ( $transients as $transient ) {
			delete_site_transient( $transient );
		}
		delete_site_transient( 'ghu-' . $type );

		return true;
	}

	/**
	 * Create transient of $type transients for force-check.
	 *
	 * @param $type
	 *
	 * @return void|bool
	 */
	protected function make_force_check_transient( $type ) {
		$transient = get_site_transient( 'ghu-' . $type );
		if ( $transient ) {
			return false;
		}
		set_site_transient( 'ghu-' . $type, self::$transients, ( self::$hours * HOUR_IN_SECONDS ) );
		self::$transients = array();

		return true;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 */
	protected function set_file_info( $response ) {
		$this->type->transient            = $response;
		$this->type->remote_version       = strtolower( $response['Version'] );
		$this->type->requires_php_version = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php_version;
		$this->type->requires_wp_version  = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires_wp_version;
	}

	/**
	 * Parse tags and set object data.
	 *
	 * @param $response
	 * @param $repo_type
	 *
	 * @return bool
	 */
	protected function parse_tags( $response, $repo_type ) {
		$tags     = array();
		$rollback = array();
		if ( false !== $response ) {
			switch ( $repo_type['repo'] ) {
				case 'github':
					foreach ( (array) $response as $tag ) {
						if ( isset( $tag->name, $tag->zipball_url ) ) {
							$tags[]                 = $tag->name;
							$rollback[ $tag->name ] = $tag->zipball_url;
						}
					}
					break;
				case 'bitbucket':
					foreach ( (array) $response as $num => $tag ) {
						$download_base = implode( '/', array( $repo_type['base_download'], $this->type->owner, $this->type->repo, 'get/' ) );
						if ( isset( $num ) ) {
							$tags[]           = $num;
							$rollback[ $num ] = $download_base . $num . '.zip';
						}
					}
					break;
				case 'gitlab':
					foreach ( (array) $response as $tag ) {
						$download_link = implode( '/', array( $repo_type['base_download'], $this->type->owner, $this->type->repo, 'repository/archive.zip' ) );
						$download_link = add_query_arg( 'ref', $tag->name, $download_link );
						if ( isset( $tag->name ) ) {
							$tags[] = $tag->name;
							$rollback[ $tag->name ] = $download_link;
						}
					}
					break;
			}

		}
		if ( empty( $tags ) ) {
			return false;
		}

		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag             = null;
		$newest_tag_key         = key( array_slice( $tags, -1, 1, true ) );
		$newest_tag             = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;

		return true;
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	protected function set_readme_info( $response ) {
		$readme = array();
		foreach ( $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections/' . $section ] = $value;
		}
		foreach ( $readme as $key => $value ) {
			$key = explode( '/', $key );
			if ( ! empty( $value ) && 'sections' === $key[0] ) {
				unset( $response['sections'][ $key[1] ] );
			}
		}

		unset( $response['sections']['screenshots'] );
		unset( $response['sections']['installation'] );
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $response['sections'] );
		$this->type->tested       = $response['tested_up_to'];
		$this->type->requires     = $response['requires_at_least'];
		$this->type->donate       = $response['donate_link'];
		$this->type->contributors = $response['contributors'];

		return true;
	}

	/**
	 * Create some sort of rating from 0 to 100 for use in star ratings.
	 * I'm really just making this up, more based upon popularity.
	 *
	 * @param $repo_meta
	 *
	 * @return integer
	 */
	protected function make_rating( $repo_meta ) {
		$watchers    = empty( $repo_meta->watchers ) ? $this->type->watchers : $repo_meta->watchers;
		$forks       = empty( $repo_meta->forks ) ? $this->type->forks : $repo_meta->forks;
		$open_issues = empty( $repo_meta->open_issues ) ? $this->type->open_issues : $repo_meta->open_issues;
		$score       = empty( $repo_meta->score ) ? $this->type->score : $repo_meta->score; //what is this anyway?

		$rating = round( $watchers + ( $forks * 1.5 ) - $open_issues + $score );

		if ( 100 < $rating ) {
			return 100;
		}

		return (integer) $rating;
	}

}
