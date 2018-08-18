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

/**
 * Interface API_Interface
 */
interface API_Interface {
	/**
	 * Read the remote file and parse headers.
	 *
	 * @access public
	 *
	 * @param string $file Filename.
	 *
	 * @return mixed
	 */
	public function get_remote_info( $file);

	/**
	 * Get remote info for tags.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_remote_tag();

	/**
	 * Read the remote CHANGES.md file.
	 *
	 * @access public
	 *
	 * @param string $changes Changelog filename.
	 *
	 * @return mixed
	 */
	public function get_remote_changes( $changes);

	/**
	 * Read and parse remote readme.txt.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_remote_readme();

	/**
	 * Read the repository meta from API.
	 *
	 * @access public
	 *
	 * @return mixed
	 */
	public function get_repo_meta();

	/**
	 * Create array of branches and download links as array.
	 *
	 * @access public
	 *
	 * @return bool
	 */
	public function get_remote_branches();

	/**
	 * Construct $this->type->download_link using Repository Contents API.
	 *
	 * @access public
	 *
	 * @param bool $rollback      For theme rollback. Defaults to false.
	 * @param bool $branch_switch For direct branch switching. Defaults to false.
	 *
	 * @return string URL for download link.
	 */
	public function construct_download_link( $rollback = false, $branch_switch = false);

	/**
	 * Create endpoints.
	 *
	 * @access public
	 *
	 * @param GitHub_API|Bitbucket_API|Bitbucket_Server_API|GitLab_API $git
	 * @param string                                                   $endpoint
	 *
	 * @return string $endpoint
	 */
	public function add_endpoints( $git, $endpoint);

	/**
	 * Parse API response call and return only array of tag numbers.
	 *
	 * @access public
	 *
	 * @param \stdClass|array $response API response.
	 *
	 * @return array|\stdClass Array of tag numbers, object is error.
	 */
	public function parse_tag_response( $response);

	/**
	 * Parse API response and return array of meta variables.
	 *
	 * @access public
	 *
	 * @param \stdClass|array $response API response.
	 *
	 * @return array|\stdClass Array of meta variables.
	 */
	public function parse_meta_response( $response);

	/**
	 * Parse API response and return array with changelog.
	 *
	 * @access public
	 *
	 * @param \stdClass|array $response API response.
	 *
	 * @return array|\stdClass $arr Array of changes in base64, object if error.
	 */
	public function parse_changelog_response( $response);

	/**
	 * Add values for individual repo add_setting_field().
	 *
	 * @return mixed
	 */
	public function add_repo_setting_field();

	/**
	 * Add settings for each API.
	 *
	 * @param array $auth_required
	 *
	 * @return mixed
	 */
	public function add_settings( $auth_required);

	/**
	 * Add remote install settings fields.
	 *
	 * @param $type
	 */
	public function add_install_settings_fields( $type);

	/**
	 *  Add remote install feature, create endpoint.
	 *
	 * @param $headers
	 * @param $install
	 *
	 * @return mixed $install
	 */
	public function remote_install( $headers, $install);
}
