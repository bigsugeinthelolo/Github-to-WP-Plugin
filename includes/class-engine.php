<?php
/**
 * Update Engine class for GitHub to WP Deployer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GH_WP_Updater_Engine {

	/**
	 * Perform the installation or update of a repository
	 *
	 * @param array  $repo  Repository configuration details
	 * @return true|WP_Error True on success, WP_Error on failure
	 */
	public static function update_repository( $repo ) {
		// 1. Sanitize input variables
		$type       = isset( $repo['type'] ) ? $repo['type'] : 'plugin';
		$slug       = isset( $repo['slug'] ) ? sanitize_key( $repo['slug'] ) : '';
		$owner_repo = isset( $repo['owner_repo'] ) ? sanitize_text_field( $repo['owner_repo'] ) : '';
		$branch     = isset( $repo['branch'] ) ? sanitize_text_field( $repo['branch'] ) : 'main';
		$token      = isset( $repo['token'] ) ? GH_WP_Updater_Helper::decrypt( $repo['token'] ) : '';

		if ( empty( $slug ) || empty( $owner_repo ) ) {
			return new WP_Error( 'invalid_repo_config', __( 'Invalid repository configuration: missing slug or repo path.', 'github-to-wp-updater' ) );
		}

		// Double-check slug safety
		if ( preg_match( '/\.\./', $slug ) || preg_match( '/[\/\\\]/', $slug ) ) {
			return new WP_Error( 'security_violation', __( 'Directory traversal attempt detected in slug.', 'github-to-wp-updater' ) );
		}

		// 2. Determine target directories
		$parent_dir = ( 'plugin' === $type ) ? WP_PLUGIN_DIR : get_theme_root();
		$target_dir = $parent_dir . '/' . $slug;

		// 3. Initialize WP_Filesystem
		$fs = GH_WP_Updater_Helper::get_filesystem();
		if ( ! $fs ) {
			return new WP_Error( 'fs_init_failed', __( 'Could not initialize WP_Filesystem API.', 'github-to-wp-updater' ) );
		}

		// Verify target parent directory is writable
		if ( ! $fs->is_writable( $parent_dir ) ) {
			return new WP_Error( 'fs_not_writable', sprintf( __( 'Destination directory %s is not writable.', 'github-to-wp-updater' ), $parent_dir ) );
		}

		// 4. Construct GitHub API download URL
		// Example: https://api.github.com/repos/owner/repo/zipball/branch
		$download_url = sprintf( 'https://api.github.com/repos/%s/zipball/%s', $owner_repo, $branch );

		// Set up request arguments
		$args = array(
			'timeout'    => 120, // Give enough time to download large repos
			'user-agent' => 'WordPress-GitHub-WP-Updater/' . GH_WP_UPDATER_VERSION,
			'headers'    => array(
				'Accept' => 'application/vnd.github+json',
			),
		);

		// Add Personal Access Token if provided (for private repos)
		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		// 5. Download the file
		$response = wp_remote_get( $download_url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'download_failed', sprintf( __( 'Failed to connect to GitHub API: %s', 'github-to-wp-updater' ), $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			$err_msg = wp_remote_retrieve_body( $response );
			$decoded = json_decode( $err_msg, true );
			if ( isset( $decoded['message'] ) ) {
				$err_msg = $decoded['message'];
			}
			return new WP_Error( 'download_failed_status', sprintf( __( 'GitHub API returned HTTP status %d: %s', 'github-to-wp-updater' ), $response_code, $err_msg ) );
		}

		$zip_content = wp_remote_retrieve_body( $response );
		if ( empty( $zip_content ) ) {
			return new WP_Error( 'empty_zip', __( 'Downloaded zipball is empty.', 'github-to-wp-updater' ) );
		}

		// 6. Save zip temporarily
		$temp_dir  = get_temp_dir();
		$temp_zip  = $temp_dir . 'github-updater-' . $slug . '-' . time() . '.zip';
		
		if ( ! $fs->put_contents( $temp_zip, $zip_content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'zip_write_failed', __( 'Could not write temporary zip file.', 'github-to-wp-updater' ) );
		}

		// 7. Create unique temp extraction folder
		$extract_dir = $temp_dir . 'github-updater-extract-' . $slug . '-' . time() . '/';
		if ( ! $fs->mkdir( $extract_dir, FS_CHMOD_DIR ) ) {
			$fs->delete( $temp_zip );
			return new WP_Error( 'extract_mkdir_failed', __( 'Could not create temporary directory for extraction.', 'github-to-wp-updater' ) );
		}

		// Ensure unzip function is loaded
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Unzip the file
		$unzip_result = unzip_file( $temp_zip, $extract_dir );
		
		// Clean up the ZIP file immediately
		$fs->delete( $temp_zip );

		if ( is_wp_error( $unzip_result ) ) {
			$fs->delete( $extract_dir, true );
			return new WP_Error( 'unzip_failed', sprintf( __( 'Unzipping failed: %s', 'github-to-wp-updater' ), $unzip_result->get_error_message() ) );
		}

		// 8. Find the root subdirectory in the extracted archive
		// GitHub zipball has a structure: owner-repo-sha/
		$files = $fs->dirlist( $extract_dir );
		if ( empty( $files ) ) {
			$fs->delete( $extract_dir, true );
			return new WP_Error( 'empty_archive', __( 'The zipball archive contains no files.', 'github-to-wp-updater' ) );
		}

		$root_subdir = '';
		foreach ( $files as $name => $details ) {
			if ( 'd' === $details['type'] ) {
				$root_subdir = $extract_dir . $name;
				break;
			}
		}

		if ( empty( $root_subdir ) ) {
			$fs->delete( $extract_dir, true );
			return new WP_Error( 'no_root_dir', __( 'Could not find the extracted root directory from GitHub zipball.', 'github-to-wp-updater' ) );
		}

		// 9. Replace old theme/plugin directory with new one using backup-and-restore mechanism
		$backup_dir = $target_dir . '_old_backup';
		$has_backup = false;

		if ( $fs->exists( $target_dir ) ) {
			// Delete any stale backup folder first
			if ( $fs->exists( $backup_dir ) ) {
				$fs->delete( $backup_dir, true );
			}

			// Rename target to backup folder
			if ( $fs->move( $target_dir, $backup_dir ) ) {
				$has_backup = true;
			} else {
				$fs->delete( $extract_dir, true );
				return new WP_Error( 'backup_failed', sprintf( __( 'Failed to create backup of existing directory: %s', 'github-to-wp-updater' ), $target_dir ) );
			}
		}

		// Move the extracted subdirectory to the target destination
		$move_result = self::move_directory( $root_subdir, $target_dir, $fs );
		
		// Clean up extract wrapper folder
		$fs->delete( $extract_dir, true );

		if ( ! $move_result ) {
			// Restore backup if move fails
			if ( $has_backup ) {
				self::move_directory( $backup_dir, $target_dir, $fs );
			}
			return new WP_Error( 'move_failed', sprintf( __( 'Failed to move files into destination: %s', 'github-to-wp-updater' ), $target_dir ) );
		}

		// Clean up backup folder on success
		if ( $has_backup ) {
			$fs->delete( $backup_dir, true );
		}

		return true;
	}

	/**
	 * Move a directory recursively, with fallback to copy/delete for cross-device filesystems.
	 *
	 * @param string             $from Source directory path
	 * @param string             $to   Target directory path
	 * @param WP_Filesystem_Base $fs   WordPress filesystem object
	 * @return bool True on success, false on failure
	 */
	private static function move_directory( $from, $to, $fs ) {
		// Try using standard move first
		if ( $fs->move( $from, $to, true ) ) {
			return true;
		}

		// Fallback to recursive copy if move failed (likely cross-device)
		if ( self::recursive_copy( $from, $to, $fs ) ) {
			$fs->delete( $from, true );
			return true;
		}

		return false;
	}

	/**
	 * Copy a file or directory recursively.
	 *
	 * @param string             $from Source path
	 * @param string             $to   Target path
	 * @param WP_Filesystem_Base $fs   WordPress filesystem object
	 * @return bool True on success, false on failure
	 */
	private static function recursive_copy( $from, $to, $fs ) {
		if ( $fs->is_file( $from ) ) {
			return $fs->copy( $from, $to, true );
		}

		if ( ! $fs->is_dir( $to ) ) {
			if ( ! $fs->mkdir( $to, FS_CHMOD_DIR ) ) {
				return false;
			}
		}

		$dirlist = $fs->dirlist( $from );
		if ( empty( $dirlist ) ) {
			return true;
		}

		foreach ( $dirlist as $name => $details ) {
			$sub_from = $from . '/' . $name;
			$sub_to   = $to . '/' . $name;

			if ( ! self::recursive_copy( $sub_from, $sub_to, $fs ) ) {
				return false;
			}
		}

		return true;
	}
}
