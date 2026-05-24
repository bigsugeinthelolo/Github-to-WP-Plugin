<?php
/**
 * Plugin Name: GitHub to WP Deployer & Auto-Updater
 * Plugin URI:  https://github.com/your-username/github-to-wp-updater
 * Description: Installs and automatically updates plugins or themes directly from GitHub repositories (public & private) via webhooks.
 * Version:     1.0.0
 * Author:      Your Name / Agency
 * License:     GPLv2 or later
 * Text Domain: github-to-wp-updater
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants
define( 'GH_WP_UPDATER_VERSION', '1.0.0' );

// Support running as a mu-plugin where the main bootstrap file is placed directly
// in wp-content/mu-plugins/ and the rest of the plugin files reside in a subdirectory.
$gh_wp_updater_base_path = plugin_dir_path( __FILE__ );
$gh_wp_updater_base_url  = plugin_dir_url( __FILE__ );

if ( ! file_exists( $gh_wp_updater_base_path . 'includes/class-helper.php' ) ) {
	if ( file_exists( $gh_wp_updater_base_path . 'Github-to-WP-Plugin/includes/class-helper.php' ) ) {
		$gh_wp_updater_base_path .= 'Github-to-WP-Plugin/';
		$gh_wp_updater_base_url  .= 'Github-to-WP-Plugin/';
	} elseif ( file_exists( $gh_wp_updater_base_path . 'github-to-wp-updater/includes/class-helper.php' ) ) {
		$gh_wp_updater_base_path .= 'github-to-wp-updater/';
		$gh_wp_updater_base_url  .= 'github-to-wp-updater/';
	}
}

// Verify that the files exist before proceeding to load them
if ( ! file_exists( $gh_wp_updater_base_path . 'includes/class-helper.php' ) ) {
	// Files are missing. Avoid Fatal Error / Critical Site Error.
	// Hook into admin notices to inform the administrator if we are in admin.
	if ( is_admin() ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'GitHub to WP Deployer & Auto-Updater error: The core plugin files are missing. Please ensure the "includes" and "assets" directories are placed in the same folder as the plugin loader.', 'github-to-wp-updater' );
			echo '</p></div>';
		} );
	}
	return;
}

define( 'GH_WP_UPDATER_PATH', $gh_wp_updater_base_path );
define( 'GH_WP_UPDATER_URL', $gh_wp_updater_base_url );
define( 'GH_WP_UPDATER_BASENAME', plugin_basename( __FILE__ ) );

// Include required classes
require_once GH_WP_UPDATER_PATH . 'includes/class-helper.php';
require_once GH_WP_UPDATER_PATH . 'includes/class-logger.php';
require_once GH_WP_UPDATER_PATH . 'includes/class-engine.php';
require_once GH_WP_UPDATER_PATH . 'includes/class-webhook.php';
require_once GH_WP_UPDATER_PATH . 'includes/class-settings.php';

/**
 * Initialize the plugin
 */
function gh_wp_updater_init() {
	// Initialize main logic
	GH_WP_Updater_Webhook::get_instance();
	GH_WP_Updater_Settings::get_instance();
}
add_action( 'plugins_loaded', 'gh_wp_updater_init' );
