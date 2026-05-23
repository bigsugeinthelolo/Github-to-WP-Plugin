<?php
/**
 * Logger class for GitHub to WP Deployer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GH_WP_Updater_Logger {

	/**
	 * Option key where logs are stored
	 */
	private static $option_key = 'gh_wp_updater_logs';

	/**
	 * Maximum number of logs to retain
	 */
	private static $max_logs = 50;

	/**
	 * Log a sync event
	 *
	 * @param string $repo_slug   Repository Slug (e.g. my-plugin-slug)
	 * @param string $repo_name   Repository Path (e.g. owner/repo)
	 * @param string $commit_sha  Commit hash (short or long)
	 * @param string $author      Commit author
	 * @param string $message     Commit message
	 * @param string $status      Status: 'success' or 'failed'
	 * @param string $error_msg   Error message if failed
	 */
	public static function log( $repo_slug, $repo_name, $commit_sha, $author, $message, $status, $error_msg = '' ) {
		$logs = get_option( self::$option_key, array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$new_log = array(
			'id'          => uniqid( 'log_', true ),
			'timestamp'   => current_time( 'timestamp' ),
			'repo_slug'   => sanitize_text_field( $repo_slug ),
			'repo_name'   => sanitize_text_field( $repo_name ),
			'commit_sha'  => sanitize_text_field( substr( $commit_sha, 0, 7 ) ),
			'commit_long' => sanitize_text_field( $commit_sha ),
			'author'      => sanitize_text_field( $author ),
			'message'     => sanitize_textarea_field( $message ),
			'status'      => sanitize_text_field( $status ),
			'error'       => sanitize_textarea_field( $error_msg ),
		);

		// Prepend to list
		array_unshift( $logs, $new_log );

		// Cap size
		if ( count( $logs ) > self::$max_logs ) {
			$logs = array_slice( $logs, 0, self::$max_logs );
		}

		update_option( self::$option_key, $logs, false );
	}

	/**
	 * Retrieve log history
	 *
	 * @return array List of logs
	 */
	public static function get_logs() {
		$logs = get_option( self::$option_key, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Clear all logs
	 */
	public static function clear_logs() {
		delete_option( self::$option_key );
	}
}
