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
define( 'GH_WP_UPDATER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GH_WP_UPDATER_URL', plugin_dir_url( __FILE__ ) );
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
