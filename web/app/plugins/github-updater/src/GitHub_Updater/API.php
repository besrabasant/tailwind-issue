<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater;

use Fragen\Singleton;
use Fragen\GitHub_Updater\Traits\GHU_Trait;
use Fragen\GitHub_Updater\Traits\Basic_Auth_Loader;
use Fragen\GitHub_Updater\API\GitHub_API;
use Fragen\GitHub_Updater\API\Bitbucket_API;
use Fragen\GitHub_Updater\API\Bitbucket_Server_API;
use Fragen\GitHub_Updater\API\GitLab_API;
use Fragen\GitHub_Updater\API\Gitea_API;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class API
 */
class API {
	use GHU_Trait, Basic_Auth_Loader;

	/**
	 * Holds HTTP error code from API call.
	 *
	 * @var array ( $this->type-repo => $code )
	 */
	protected static $error_code = [];

	/**
	 * Holds site options.
	 *
	 * @var array $options
	 */
	protected static $options;

	/**
	 * Holds extra headers.
	 *
	 * @var
	 */
	protected static $extra_headers;

	/**
	 * Variable for setting update transient hours.
	 *
	 * @var integer
	 */
	protected $hours = 12;

	/**
	 * Variable to hold all repository remote info.
	 *
	 * @access protected
	 * @var array
	 */
	protected $response = [];

	/**
	 * API constructor.
	 */
	public function __construct() {
		static::$options       = $this->get_class_vars( 'Base', 'options' );
		static::$extra_headers = Singleton::get_instance( 'Base', $this )->add_headers( [] );
	}

	/**
	 * Shiny updates results in the update transient being reset with only the wp.org data.
	 * This catches the response and reloads the transients.
	 *
	 * @uses \Fragen\GitHub_Updater\Base::make_update_transient_current()
	 *
	 * @param mixed  $response HTTP server response.
	 * @param array  $args     HTTP response arguments.
	 * @param string $url      URL of HTTP response.
	 *
	 * @return mixed $response Just a pass through, no manipulation.
	 */
	public static function wp_update_response( $response, $args, $url ) {
		$parsed_url = parse_url( $url );

		if ( 'api.wordpress.org' === $parsed_url['host'] ) {
			if ( isset( $args['body']['plugins'] ) ) {
				Singleton::get_instance( 'Base', new self() )->make_update_transient_current( 'update_plugins' );
			}
			if ( isset( $args['body']['themes'] ) ) {
				Singleton::get_instance( 'Base', new self() )->make_update_transient_current( 'update_themes' );
			}
		}

		return $response;
	}

	/**
	 * Adds custom user agent for GitHub Updater.
	 *
	 * @access public
	 *
	 * @param array  $args Existing HTTP Request arguments.
	 * @param string $url  URL being passed.
	 *
	 * @return array Amended HTTP Request arguments.
	 */
	public static function http_request_args( $args, $url ) {
		$args['sslverify'] = true;
		if ( false === stripos( $args['user-agent'], 'GitHub Updater' ) ) {
			$args['user-agent']   .= '; GitHub Updater - https://github.com/afragen/github-updater';
			$args['wp-rest-cache'] = [ 'tag' => 'github-updater' ];
		}

		return $args;
	}

	/**
	 * Add data in Settings page.
	 *
	 * @param object $git Git API object.
	 */
	public function settings_hook( $git ) {
		add_action(
			'github_updater_add_settings', function ( $auth_required ) use ( $git ) {
				$git->add_settings( $auth_required );
			}
		);
		add_filter( 'github_updater_add_repo_setting_field', [ $this, 'add_setting_field' ], 10, 2 );
	}

	/**
	 * Add data to the setting_field in Settings.
	 *
	 * @param array  $fields
	 * @param array  $repo
	 * @param string $type
	 *
	 * @return array
	 */
	public function add_setting_field( $fields, $repo ) {
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return $this->get_repo_api( $repo->type, $repo )->add_repo_setting_field();
	}

	/**
	 * Get repo's API.
	 *
	 * @param string         $type
	 * @param bool|\stdClass $repo
	 *
	 * @return \Fragen\GitHub_Updater\API\Bitbucket_API|
	 *                                                   \Fragen\GitHub_Updater\API\Bitbucket_Server_API|
	 *                                                   \Fragen\GitHub_Updater\API\Gitea_API|
	 *                                                   \Fragen\GitHub_Updater\API\GitHub_API|
	 *                                                   \Fragen\GitHub_Updater\API\GitLab_API $repo_api
	 */
	public function get_repo_api( $type, $repo = false ) {
		$repo_api = null;
		$repo     = $repo ?: new \stdClass();
		switch ( $type ) {
			case 'github_plugin':
			case 'github_theme':
				$repo_api = new GitHub_API( $repo );
				break;
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				if ( ! empty( $repo->enterprise ) ) {
					$repo_api = new Bitbucket_Server_API( $repo );
				} else {
					$repo_api = new Bitbucket_API( $repo );
				}
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$repo_api = new GitLab_API( $repo );
				break;
			case 'gitea_plugin':
			case 'gitea_theme':
				$repo_api = new Gitea_API( $repo );
				break;
		}

		return $repo_api;
	}

	/**
	 * Add Install settings fields.
	 *
	 * @param object $git Git API from caller.
	 */
	public function add_install_fields( $git ) {
		add_action(
			'github_updater_add_install_settings_fields', function ( $type ) use ( $git ) {
				$git->add_install_settings_fields( $type );
			}
		);
	}

	/**
	 * Take remote file contents as string and parse headers.
	 *
	 * @param $contents
	 * @param $type
	 *
	 * @return array
	 */
	public function get_file_headers( $contents, $type ) {
		$all_headers            = [];
		$default_plugin_headers = [
			'Name'        => 'Plugin Name',
			'PluginURI'   => 'Plugin URI',
			'Version'     => 'Version',
			'Description' => 'Description',
			'Author'      => 'Author',
			'AuthorURI'   => 'Author URI',
			'TextDomain'  => 'Text Domain',
			'DomainPath'  => 'Domain Path',
			'Network'     => 'Network',
		];

		$default_theme_headers = [
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
		];

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
		$all_headers = array_merge( self::$extra_headers, $all_headers );
		$all_headers = array_unique( $all_headers );

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = _cleanup_header_comment( $match[1] );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		// Reduce array to only headers with data.
		$all_headers = array_filter(
			$all_headers,
			function ( $e ) {
				return ! empty( $e );
			}
		);

		return $all_headers;
	}

	/**
	 * Call the API and return a json decoded body.
	 * Create error messages.
	 *
	 * @link http://developer.github.com/v3/
	 *
	 * @param string $url The URL to send the request to.
	 *
	 * @return boolean|\stdClass
	 */
	protected function api( $url ) {
		add_filter( 'http_request_args', [ $this, 'http_request_args' ], 10, 2 );

		$type          = $this->return_repo_type();
		$response      = wp_remote_get( $this->get_api_url( $url ) );
		$code          = (int) wp_remote_retrieve_response_code( $response );
		$allowed_codes = [ 200, 404 ];

		if ( is_wp_error( $response ) ) {
			Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

			return false;
		}
		if ( ! in_array( $code, $allowed_codes, true ) ) {
			static::$error_code = array_merge(
				static::$error_code,
				[
					$this->type->repo => [
						'repo' => $this->type->repo,
						'code' => $code,
						'name' => $this->type->name,
						'git'  => $this->type->type,
					],
				]
			);
			if ( 'github' === $type['repo'] ) {
				GitHub_API::ratelimit_reset( $response, $this->type->repo );
			}
			Singleton::get_instance( 'Messages', $this )->create_error_message( $type['repo'] );

			return false;
		}

		// Gitea doesn't return json encoded raw file.
		if ( $this instanceof Gitea_API ) {
			$body = wp_remote_retrieve_body( $response );
			if ( null === json_decode( $body ) ) {
				return $body;
			}
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Return repo data for API calls.
	 *
	 * @access protected
	 *
	 * @return array
	 */
	protected function return_repo_type() {
		$type        = explode( '_', $this->type->type );
		$arr         = [];
		$arr['type'] = $type[1];

		switch ( $type[0] ) {
			case 'github':
				$arr['repo']          = 'github';
				$arr['base_uri']      = 'https://api.github.com';
				$arr['base_download'] = 'https://github.com';
				break;
			case 'bitbucket':
				$arr['repo'] = 'bitbucket';
				if ( empty( $this->type->enterprise ) ) {
					$arr['base_uri']      = 'https://bitbucket.org/api';
					$arr['base_download'] = 'https://bitbucket.org';
				} else {
					$arr['base_uri']      = $this->type->enterprise_api;
					$arr['base_download'] = $this->type->enterprise;
				}
				break;
			case 'gitlab':
				$arr['repo']          = 'gitlab';
				$arr['base_uri']      = 'https://gitlab.com/api/v4';
				$arr['base_download'] = 'https://gitlab.com';
				break;
			case 'gitea':
				$arr['repo']          = 'gitea';
				$arr['base_uri']      = $this->type->enterprise . '/api/v1';
				$arr['base_download'] = $this->type->enterprise;
		}

		return $arr;
	}

	/**
	 * Return API url.
	 *
	 * @access protected
	 *
	 * @param string      $endpoint      The endpoint to access.
	 * @param bool|string $download_link The plugin or theme download link. Defaults to false.
	 *
	 * @return string $endpoint
	 */
	protected function get_api_url( $endpoint, $download_link = false ) {
		$type     = $this->return_repo_type();
		$segments = [
			'owner'  => $this->type->owner,
			'repo'   => $this->type->repo,
			'branch' => empty( $this->type->branch ) ? 'master' : $this->type->branch,
		];

		foreach ( $segments as $segment => $value ) {
			$endpoint = str_replace( '/:' . $segment, '/' . sanitize_text_field( $value ), $endpoint );
		}

		$repo_api = $this->get_repo_api( $type['repo'] . '_' . $type['type'], $type );
		switch ( $type['repo'] ) {
			case 'github':
				if ( ! $this->type->enterprise && $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				if ( $this->type->enterprise_api ) {
					$type['base_download'] = $this->type->enterprise_api;
					if ( $download_link ) {
						break;
					}
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			case 'gitlab':
				if ( ! $this->type->enterprise && $download_link ) {
					break;
				}
				if ( $this->type->enterprise ) {
					$type['base_download'] = $this->type->enterprise;
					$type['base_uri']      = null;
					if ( $download_link ) {
						break;
					}
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			case 'bitbucket':
				$this->load_authentication_hooks();
				if ( $this->type->enterprise_api ) {
					if ( $download_link ) {
						break;
					}
					$endpoint = $repo_api->add_endpoints( $this, $endpoint );

					return $this->type->enterprise_api . $endpoint;
				}
				break;
			case 'gitea':
				if ( $download_link ) {
					$type['base_download'] = $type['base_uri'];
					break;
				}
				$endpoint = $repo_api->add_endpoints( $this, $endpoint );
				break;
			default:
				break;
		}

		$base = $download_link ? $type['base_download'] : $type['base_uri'];

		return $base . $endpoint;
	}

	/**
	 * Query wp.org for plugin/theme information.
	 * Exit early and false for override dot org active.
	 *
	 * @access protected
	 *
	 * @return bool|int|mixed|string|\WP_Error
	 */
	protected function get_dot_org_data() {
		if ( $this->is_override_dot_org() ) {
			return false;
		}

		$slug     = $this->type->repo;
		$response = isset( $this->response['dot_org'] ) ? $this->response['dot_org'] : false;

		if ( ! $response ) {
			$type     = explode( '_', $this->type->type )[1];
			$url      = 'https://api.wordpress.org/' . $type . 's/info/1.1/';
			$url      = add_query_arg(
				[
					'action'                     => $type . '_information',
					urlencode( 'request[slug]' ) => $slug,
				], $url
			);
			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				Singleton::get_instance( 'Messages', $this )->create_error_message( $response );

				return false;
			}

			$response = json_decode( $response['body'] );
			$response = ! empty( $response ) && ! isset( $response->error ) ? 'in dot org' : 'not in dot org';

			$this->set_repo_cache( 'dot_org', $response );
		}

		return 'in dot org' === $response;
	}

	/**
	 * Add appropriate access token to endpoint.
	 *
	 * @access protected
	 *
	 * @param GitHub_API|GitLab_API $git      Class containing the GitAPI used.
	 * @param string                $endpoint The endpoint being accessed.
	 *
	 * @return string $endpoint
	 */
	protected function add_access_token_endpoint( $git, $endpoint ) {
		// This will return if checking during shiny updates.
		if ( null === static::$options ) {
			return $endpoint;
		}
		$key              = null;
		$token            = null;
		$token_enterprise = null;

		switch ( $git->type->type ) {
			case 'github_plugin':
			case 'github_theme':
				$key              = 'access_token';
				$token            = 'github_access_token';
				$token_enterprise = 'github_enterprise_token';
				break;
			case 'gitlab_plugin':
			case 'gitlab_theme':
				$key              = 'private_token';
				$token            = 'gitlab_access_token';
				$token_enterprise = 'gitlab_enterprise_token';
				break;
			case 'gitea_plugin':
			case 'gitea_theme':
				$key              = 'access_token';
				$token            = 'gitea_access_token';
				$token_enterprise = 'gitea_access_token';
				break;
		}

		// Add hosted access token.
		if ( ! empty( static::$options[ $token ] ) ) {
			$endpoint = add_query_arg( $key, static::$options[ $token ], $endpoint );
		}

		// Add Enterprise access token.
		if ( ! empty( $git->type->enterprise ) &&
			! empty( static::$options[ $token_enterprise ] )
		) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, static::$options[ $token_enterprise ], $endpoint );
		}

		// Add repo access token.
		if ( ! empty( static::$options[ $git->type->repo ] ) ) {
			$endpoint = remove_query_arg( $key, $endpoint );
			$endpoint = add_query_arg( $key, static::$options[ $git->type->repo ], $endpoint );
		}

		return $endpoint;
	}

	/**
	 * Test to exit early if no update available, saves API calls.
	 *
	 * @param $response array|bool
	 * @param $branch   bool
	 *
	 * @return bool
	 */
	protected function exit_no_update( $response, $branch = false ) {
		/**
		 * Filters the return value of exit_no_update.
		 *
		 * @since 6.0.0
		 * @return bool `true` will exit this function early, default will not.
		 */
		if ( apply_filters( 'ghu_always_fetch_update', false ) ) {
			return false;
		}

		if ( $branch ) {
			return empty( static::$options['branch_switch'] );
		}

		return ! isset( $_POST['ghu_refresh_cache'] ) && ! $response && ! $this->can_update_repo( $this->type );
	}

	/**
	 * Validate wp_remote_get response.
	 *
	 * @access protected
	 *
	 * @param \stdClass $response The response.
	 *
	 * @return bool true if invalid
	 */
	protected function validate_response( $response ) {
		return empty( $response ) || isset( $response->message );
	}

	/**
	 * Check if a local file for the repository exists.
	 * Only checks the root directory of the repository.
	 *
	 * @access protected
	 *
	 * @param string $filename The filename to check for.
	 *
	 * @return bool
	 */
	protected function local_file_exists( $filename ) {
		return file_exists( $this->type->local_path . $filename );
	}

	/**
	 * Sort tags and set object data.
	 *
	 * @param array $parsed_tags
	 *
	 * @return bool
	 */
	protected function sort_tags( $parsed_tags ) {
		if ( empty( $parsed_tags ) ) {
			return false;
		}

		list($tags, $rollback) = $parsed_tags;
		usort( $tags, 'version_compare' );
		krsort( $rollback );

		$newest_tag     = array_slice( $tags, -1, 1, true );
		$newest_tag_key = key( $newest_tag );
		$newest_tag     = $tags[ $newest_tag_key ];

		$this->type->newest_tag = $newest_tag;
		$this->type->tags       = $tags;
		$this->type->rollback   = $rollback;

		return true;
	}

	/**
	 * Get local file info if no update available. Save API calls.
	 *
	 * @param $repo
	 * @param $file
	 *
	 * @return null|string
	 */
	protected function get_local_info( $repo, $file ) {
		$response = false;

		if ( isset( $_POST['ghu_refresh_cache'] ) ) {
			return $response;
		}

		if ( is_dir( $repo->local_path ) &&
			file_exists( $repo->local_path . $file )
		) {
			$response = file_get_contents( $repo->local_path . $file );
		}

		switch ( $repo->type ) {
			case 'bitbucket_plugin':
			case 'bitbucket_theme':
				break;
			default:
				$response = base64_encode( $response );
				break;
		}

		return $response;
	}

	/**
	 * Set repo object file info.
	 *
	 * @param $response
	 */
	protected function set_file_info( $response ) {
		$this->type->transient      = $response;
		$this->type->remote_version = strtolower( $response['Version'] );
		$this->type->requires_php   = ! empty( $response['Requires PHP'] ) ? $response['Requires PHP'] : $this->type->requires_php;
		$this->type->requires       = ! empty( $response['Requires WP'] ) ? $response['Requires WP'] : $this->type->requires;
		$this->type->dot_org        = $response['dot_org'];
	}

	/**
	 * Add remote data to type object.
	 *
	 * @access protected
	 */
	protected function add_meta_repo_object() {
		$this->type->rating       = $this->make_rating( $this->type->repo_meta );
		$this->type->last_updated = $this->type->repo_meta['last_updated'];
		$this->type->num_ratings  = $this->type->repo_meta['watchers'];
		$this->type->is_private   = $this->type->repo_meta['private'];
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
		$watchers    = empty( $repo_meta['watchers'] ) ? $this->type->watchers : $repo_meta['watchers'];
		$forks       = empty( $repo_meta['forks'] ) ? $this->type->forks : $repo_meta['forks'];
		$open_issues = empty( $repo_meta['open_issues'] ) ? $this->type->open_issues : $repo_meta['open_issues'];

		$rating = abs( (int) round( $watchers + ( $forks * 1.5 ) - ( $open_issues * 0.1 ) ) );

		if ( 100 < $rating ) {
			return 100;
		}

		return $rating;
	}

	/**
	 * Set data from readme.txt.
	 * Prefer changelog from CHANGES.md.
	 *
	 * @param array $readme Array of parsed readme.txt data.
	 *
	 * @return bool
	 */
	protected function set_readme_info( $readme ) {
		foreach ( (array) $this->type->sections as $section => $value ) {
			if ( 'description' === $section ) {
				continue;
			}
			$readme['sections'][ $section ] = $value;
		}

		$readme['remaining_content'] = ! empty( $readme['remaining_content'] ) ? $readme['remaining_content'] : null;
		if ( empty( $readme['sections']['other_notes'] ) ) {
			unset( $readme['sections']['other_notes'] );
		} else {
			$readme['sections']['other_notes'] .= $readme['remaining_content'];
		}
		unset( $readme['sections']['screenshots'], $readme['sections']['installation'] );
		$readme['sections']       = ! empty( $readme['sections'] ) ? $readme['sections'] : [];
		$this->type->sections     = array_merge( (array) $this->type->sections, (array) $readme['sections'] );
		$this->type->tested       = isset( $readme['tested'] ) ? $readme['tested'] : null;
		$this->type->requires     = isset( $readme['requires'] ) ? $readme['requires'] : null;
		$this->type->requires_php = isset( $readme['requires_php'] ) ? $readme['requires_php'] : null;
		$this->type->donate_link  = isset( $readme['donate_link'] ) ? $readme['donate_link'] : null;
		$this->type->contributors = isset( $readme['contributors'] ) ? $readme['contributors'] : null;

		return true;
	}
}
