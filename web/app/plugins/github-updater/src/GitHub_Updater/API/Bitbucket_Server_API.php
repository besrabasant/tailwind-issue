<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\API;

use Fragen\Singleton;
use Fragen\GitHub_Updater\API;
use Fragen\GitHub_Updater\Readme_Parser;

/*
 * Exit if called directly.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Bitbucket_Server_API
 *
 * Get remote data from a self-hosted Bitbucket Server repo.
 * Assumes an owner == project_key
 *
 * @author  Andy Fragen
 * @author  Bjorn Wijers
 */
class Bitbucket_Server_API extends Bitbucket_API {
	/**
	 * Holds loose class method name.
	 *
	 * @var null
	 */
	private static $method;

	/**
	 * Constructor.
	 *
	 * @param \stdClass $type
	 */
	public function __construct( $type ) {
		parent::__construct( $type );
		$this->add_settings_subtab();
	}

	/**
	 * Read the remote file and parse headers.
	 *
	 * @param string $file Filename.
	 *
	 * @return bool
	 */
	public function get_remote_info( $file ) {
		$response = isset( $this->response[ $file ] ) ? $this->response[ $file ] : false;

		if ( ! $response ) {
			self::$method = 'file';
			$path         = '/1.0/projects/:owner/repos/:repo/browse/' . $file;

			$response = $this->api( $path );

			if ( $response ) {
				$contents = $this->bbserver_recombine_response( $response );
				$response = $this->get_file_headers( $contents, $this->type->type );
				$this->set_repo_cache( $file, $response );
				$this->set_repo_cache( 'repo', $this->type->repo );
			}
		}

		if ( ! is_array( $response ) || $this->validate_response( $response ) ) {
			return false;
		}

		$response['dot_org'] = $this->get_dot_org_data();
		$this->set_file_info( $response );

		return true;
	}

	/**
	 * Get the remote info for tags.
	 *
	 * @return bool
	 */
	public function get_remote_tag() {
		$repo_type = $this->return_repo_type();
		$response  = isset( $this->response['tags'] ) ? $this->response['tags'] : false;

		if ( ! $response ) {
			$response = $this->api( '/1.0/projects/:owner/repos/:repo/tags' );

			if ( ! $response ||
				( isset( $response->size ) && $response->size < 1 ) ||
				isset( $response->errors )
			) {
				$response          = new \stdClass();
				$response->message = 'No tags found';
			}

			if ( $response ) {
				$response = $this->parse_tag_response( $response );
				$this->set_repo_cache( 'tags', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$tags = $this->parse_tags( $response, $repo_type );
		$this->sort_tags( $tags );

		return true;
	}

	/**
	 * Read the remote CHANGES.md file
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return bool
	 */
	public function get_remote_changes( $changes ) {
		$response = isset( $this->response['changes'] ) ? $this->response['changes'] : false;

		/*
		 * Set $response from local file if no update available.
		 */
		if ( ! $response && ! $this->can_update_repo( $this->type ) ) {
			$response = [];
			$content  = $this->get_local_info( $this->type, $changes );
			if ( $content ) {
				$response['changes'] = $content;
				$this->set_repo_cache( 'changes', $response );
			} else {
				$response = false;
			}
		}

		if ( ! $response ) {
			self::$method = 'changes';
			$response     = $this->bbserver_fetch_raw_file( $changes );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No changelog found';
			}

			if ( $response ) {
				$response = wp_remote_retrieve_body( $response );
				$response = $this->parse_changelog_response( $response );
				$this->set_repo_cache( 'changes', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$parser    = new \Parsedown();
		$changelog = $parser->text( $response['changes'] );

		$this->type->sections['changelog'] = $changelog;

		return true;
	}

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @return bool
	 */
	public function get_remote_readme() {
		if ( ! $this->local_file_exists( 'readme.txt' ) ) {
			return false;
		}

		$response = isset( $this->response['readme'] ) ? $this->response['readme'] : false;

		/*
		 * Set $response from local file if no update available.
		 */
		if ( ! $response && ! $this->can_update_repo( $this->type ) ) {
			$response = new \stdClass();
			$content  = $this->get_local_info( $this->type, 'readme.txt' );
			if ( $content ) {
				$response->data = $content;
			} else {
				$response = false;
			}
		}

		if ( ! $response ) {
			self::$method = 'readme';
			$response     = $this->bbserver_fetch_raw_file( 'readme.txt' );

			if ( ! $response ) {
				$response          = new \stdClass();
				$response->message = 'No readme found';
			}

			if ( $response ) {
				$response = wp_remote_retrieve_body( $response );
				$response = $this->parse_readme_response( $response );
			}
		}

		if ( $response && isset( $response->data ) ) {
			$file     = $response->data;
			$parser   = new Readme_Parser( $file );
			$response = $parser->parse_data();
			$this->set_repo_cache( 'readme', $response );
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->set_readme_info( $response );

		return true;
	}

	/**
	 * Read the repository meta from API
	 *
	 * @return bool
	 */
	public function get_repo_meta() {
		$response = isset( $this->response['meta'] ) ? $this->response['meta'] : false;

		if ( ! $response ) {
			self::$method = 'meta';
			$response     = $this->api( '/1.0/projects/:owner/repos/:repo' );

			if ( $response ) {
				$response = $this->parse_meta_response( $response );
				$this->set_repo_cache( 'meta', $response );
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->repo_meta = $response;
		$this->add_meta_repo_object();

		return true;
	}

	/**
	 * Create array of branches and download links as array.
	 *
	 * @return bool
	 */
	public function get_remote_branches() {
		$branches = [];
		$response = isset( $this->response['branches'] ) ? $this->response['branches'] : false;

		if ( $this->exit_no_update( $response, true ) ) {
			return false;
		}

		if ( ! $response ) {
			self::$method = 'branches';
			$response     = $this->api( '/1.0/projects/:owner/repos/:repo/branches' );
			if ( $response && isset( $response->values ) ) {
				foreach ( (array) $response->values as $value ) {
					$branch              = $value->displayId;
					$branches[ $branch ] = $this->construct_download_link( false, $branch );
				}
				$this->type->branches = $branches;
				$this->set_repo_cache( 'branches', $branches );

				return true;
			}
		}

		if ( $this->validate_response( $response ) ) {
			return false;
		}

		$this->type->branches = $response;

		return true;
	}

	/**
	 * Construct $this->type->download_link using Bitbucket Server API.
	 *
	 * Downloads requires the official stash-archive plugin which enables
	 * subdirectory support using the prefix query argument.
	 *
	 * @link https://bitbucket.org/atlassian/stash-archive
	 *
	 * @param boolean $rollback      for theme rollback.
	 * @param boolean $branch_switch for direct branch changing.
	 *
	 * @return string $endpoint
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false ) {
		$download_link_base = $this->get_api_url( '/rest/archive/1.0/projects/:owner/repos/:repo/archive', true );

		self::$method = 'download_link';
		$endpoint     = $this->add_endpoints( $this, '' );

		if ( $branch_switch ) {
			$endpoint = urldecode( add_query_arg( 'at', $branch_switch, $endpoint ) );
		}

		return $download_link_base . $endpoint;
	}

	/**
	 * Create Bitbucket Server API endpoints.
	 *
	 * @param Bitbucket_Server_API|API $git
	 * @param string                   $endpoint
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint ) {
		switch ( self::$method ) {
			case 'meta':
			case 'tags':
			case 'translation':
			case 'branches':
				break;
			case 'file':
			case 'readme':
				$endpoint = add_query_arg( 'at', $git->type->branch, $endpoint );
				break;
			case 'changes':
				$endpoint = add_query_arg(
					[
						'at'  => $git->type->branch,
						'raw' => '',
					], $endpoint
				);
				break;
			case 'download_link':
				/*
				 * Add a prefix query argument to create a subdirectory with the same name
				 * as the repo, e.g. 'my-repo' becomes 'my-repo/'
				 * Required for using stash-archive.
				 */
				$defaults = [
					'prefix' => $git->type->repo . '/',
					'at'     => $git->type->branch,
				];
				$endpoint = add_query_arg( $defaults, $endpoint );
				if ( ! empty( $git->type->tags ) ) {
					$endpoint = urldecode( add_query_arg( 'at', $git->type->newest_tag, $endpoint ) );
				}
				break;
			default:
				break;
		}

		return $endpoint;
	}

	/**
	 * The Bitbucket Server REST API does not support downloading files directly at the moment
	 * therefore we'll use this to construct urls to fetch the raw files ourselves.
	 *
	 * @param string $file filename.
	 *
	 * @return bool|array false upon failure || return wp_safe_remote_get() response array
	 **/
	private function bbserver_fetch_raw_file( $file ) {
		$file         = urlencode( $file );
		$download_url = '/1.0/projects/:owner/repos/:repo/browse/' . $file;
		$download_url = $this->add_endpoints( $this, $download_url );
		$download_url = $this->get_api_url( $download_url );

		$response = wp_safe_remote_get( $download_url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Combines separate text lines from API response into one string with \n line endings.
	 * Code relying on raw text can now parse it.
	 *
	 * @param string|\stdClass|mixed $response
	 *
	 * @return string Combined lines of text returned by API
	 */
	private function bbserver_recombine_response( $response ) {
		$remote_info_file = '';
		$json_decoded     = is_string( $response ) ? json_decode( $response ) : '';
		$response         = empty( $json_decoded ) ? $response : $json_decoded;
		if ( isset( $response->lines ) ) {
			foreach ( (array) $response->lines as $line ) {
				$remote_info_file .= $line->text . "\n";
			}
		}

		return $remote_info_file;
	}

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @param \stdClass|array $response Response from API call.
	 *
	 * @return array $arr Array of meta variables.
	 */
	public function parse_meta_response( $response ) {
		$arr      = [];
		$response = [ $response ];

		array_filter(
			$response, function ( $e ) use ( &$arr ) {
				$arr['private']      = ! $e->public;
				$arr['last_updated'] = null;
				$arr['watchers']     = 0;
				$arr['forks']        = 0;
				$arr['open_issues']  = 0;
			}
		);

		return $arr;
	}

	/**
	 * Parse API response and return array with changelog.
	 *
	 * @param string $response Response from API call.
	 *
	 * @return array $arr Array of changes in base64.
	 */
	public function parse_changelog_response( $response ) {
		return [ 'changes' => $this->bbserver_recombine_response( $response ) ];
	}

	/**
	 * Parse API response and return object with readme body.
	 *
	 * @param string|\stdClass $response
	 *
	 * @return \stdClass $response
	 */
	protected function parse_readme_response( $response ) {
		$content        = $this->bbserver_recombine_response( $response );
		$response       = new \stdClass();
		$response->data = $content;

		return $response;
	}

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @param \stdClass $response Response from API call.
	 *
	 * @return array|\stdClass Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response ) {
		if ( isset( $response->message ) || ! isset( $response->values ) ) {
			return $response;
		}

		$arr = [];
		array_map(
			function ( $e ) use ( &$arr ) {
				$arr[] = $e->displayId;

				return $arr;
			}, (array) $response->values
		);

		return $arr;
	}

	/**
	 * Add settings for Bitbucket Server Username and Password.
	 *
	 * @param array $auth_required
	 *
	 * @return void
	 */
	public function add_settings( $auth_required ) {
		add_settings_section(
			'bitbucket_server_user',
			esc_html__( 'Bitbucket Server Private Settings', 'github-updater' ),
			[ $this, 'print_section_bitbucket_username' ],
			'github_updater_bbserver_install_settings'
		);

		add_settings_field(
			'bitbucket_server_username',
			esc_html__( 'Bitbucket Server Username', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bbserver_install_settings',
			'bitbucket_server_user',
			[ 'id' => 'bitbucket_server_username' ]
		);

		add_settings_field(
			'bitbucket_server_password',
			esc_html__( 'Bitbucket Server Password', 'github-updater' ),
			[ Singleton::get_instance( 'Settings', $this ), 'token_callback_text' ],
			'github_updater_bbserver_install_settings',
			'bitbucket_server_user',
			[
				'id'    => 'bitbucket_server_password',
				'token' => true,
			]
		);

		/*
		 * Show section for private Bitbucket Server repositories.
		 */
		if ( $auth_required['bitbucket_server'] ) {
			add_settings_section(
				'bitbucket_server_id',
				esc_html__( 'Bitbucket Server Private Repositories', 'github-updater' ),
				[ $this, 'print_section_bitbucket_info' ],
				'github_updater_bbserver_install_settings'
			);
		}
	}

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field() {
		$setting_field['page']            = 'github_updater_bbserver_install_settings';
		$setting_field['section']         = 'bitbucket_server_id';
		$setting_field['callback_method'] = [
			Singleton::get_instance( 'Settings', $this ),
			'token_callback_checkbox',
		];

		return $setting_field;
	}

	/**
	 * Add subtab to Settings page.
	 */
	private function add_settings_subtab() {
		add_filter(
			'github_updater_add_settings_subtabs', function ( $subtabs ) {
				return array_merge( $subtabs, [ 'bbserver' => esc_html__( 'Bitbucket Server', 'github-updater' ) ] );
			}
		);
	}

	/**
	 * Add remote install feature, create endpoint.
	 *
	 * @param array $headers
	 * @param array $install
	 *
	 * @return array $install
	 */
	public function remote_install( $headers, $install ) {
		$bitbucket_org = true;

		if ( 'bitbucket.org' === $headers['host'] || empty( $headers['host'] ) ) {
			$base            = 'https://bitbucket.org';
			$headers['host'] = 'bitbucket.org';
		} else {
			$base          = $headers['base_uri'];
			$bitbucket_org = false;
		}

		if ( ! $bitbucket_org ) {
			$install['download_link'] = implode(
				'/', [
					$base,
					'rest/archive/1.0/projects',
					$headers['owner'],
					'repos',
					$headers['repo'],
					'archive',
				]
			);

			$install['download_link'] = add_query_arg(
				[
					'prefix' => $headers['repo'] . '/',
					'at'     => $install['github_updater_branch'],
				], $install['download_link']
			);

			if ( isset( $install['is_private'] ) ) {
				$install['options'][ $install['repo'] ] = 1;
			}
			if ( ! empty( $install['bitbucket_username'] ) ) {
				$install['options']['bitbucket_server_username'] = $install['bitbucket_username'];
			}
			if ( ! empty( $install['bitbucket_password'] ) ) {
				$install['options']['bitbucket_server_password'] = $install['bitbucket_password'];
			}
		}

		return $install;
	}
}
