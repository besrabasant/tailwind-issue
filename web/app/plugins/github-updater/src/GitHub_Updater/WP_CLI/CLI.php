<?php
/**
 * GitHub Updater
 *
 * @author    Andy Fragen
 * @license   GPL-2.0+
 * @link      https://github.com/afragen/github-updater
 * @package   github-updater
 */

namespace Fragen\GitHub_Updater\WP_CLI;

use WP_CLI;
use WP_CLI_Command;
use Fragen\Singleton;

// Add WP-CLI commands.
WP_CLI::add_command( 'github-updater', 'Fragen\\GitHub_Updater\\WP_CLI\\CLI' );

/**
 * Manage GitHub Updater commands.
 *
 * Class GitHub_Updater_CLI
 */
class CLI extends WP_CLI_Command {
	/**
	 * Clear GitHub Updater cache.
	 *
	 * ## OPTIONS
	 *
	 * <delete>
	 * : delete the cache
	 *
	 * ## EXAMPLES
	 *
	 *     wp github-updater cache delete
	 *
	 * @param array $args Array of arguments.
	 *
	 * @subcommand cache
	 */
	public function cache( $args ) {
		list($action) = $args;
		if ( 'delete' === $action ) {
			Singleton::get_instance( 'CLI_Common', $this )->delete_all_cached_data();
			WP_CLI::success( 'GitHub Updater cache has been cleared.' );
		} else {
			WP_CLI::error( sprintf( 'Incorrect command syntax, see %s for proper syntax.', '`wp help github-updater cache`' ) );
		}
		WP_CLI::success( 'WP-Cron is now running.' );
		WP_CLI::runcommand( 'cron event run --due-now' );
	}

	/**
	 * Reset GitHub Updater REST API key.
	 *
	 * ## EXAMPLES
	 *
	 *     wp github-updater reset-api-key
	 *
	 * @subcommand reset-api-key
	 */
	public function reset_api_key() {
		delete_site_option( 'github_updater_api_key' );
		Singleton::get_instance( 'Remote_Management', $this )->ensure_api_key_is_set();
		$api_key = get_site_option( 'github_updater_api_key' );
		$api_url = add_query_arg(
			[
				'action' => 'github-updater-update',
				'key'    => $api_key,
			], admin_url( 'admin-ajax.php' )
		);

		WP_CLI::success( 'GitHub Updater REST API key has been reset.' );
		WP_CLI::success( sprintf( 'The current RESTful endpoint is `%s`', $api_url ) );
	}
}
