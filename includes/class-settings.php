<?php
/**
 * Settings and AJAX Controller class for GitHub to WP Deployer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GH_WP_Updater_Settings {

	/**
	 * Instance of this class
	 */
	private static $instance = null;

	/**
	 * Get class instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX Handlers
		add_action( 'wp_ajax_gh_wp_save_repo', array( $this, 'ajax_save_repo' ) );
		add_action( 'wp_ajax_gh_wp_delete_repo', array( $this, 'ajax_delete_repo' ) );
		add_action( 'wp_ajax_gh_wp_trigger_sync', array( $this, 'ajax_trigger_sync' ) );
		add_action( 'wp_ajax_gh_wp_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_gh_wp_get_logs', array( $this, 'ajax_get_logs' ) );
	}

	/**
	 * Register Admin Menu Page
	 */
	public function register_menu() {
		add_menu_page(
			__( 'GitHub Deployer', 'github-to-wp-updater' ),
			__( 'GitHub Sync', 'github-to-wp-updater' ),
			'manage_options',
			'github-to-wp-updater',
			array( $this, 'render_admin_page' ),
			'dashicons-update-alt',
			80
		);
	}

	/**
	 * Enqueue Styles and Scripts
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_github-to-wp-updater' !== $hook ) {
			return;
		}

		// Enqueue Google Font - Outfit
		wp_enqueue_style( 'google-font-outfit', 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap', array(), null );

		// Plugin Custom CSS
		wp_enqueue_style( 'gh-wp-updater-css', GH_WP_UPDATER_URL . 'assets/css/admin-style.css', array(), GH_WP_UPDATER_VERSION );

		// Plugin Custom JS
		wp_enqueue_script( 'gh-wp-updater-js', GH_WP_UPDATER_URL . 'assets/js/admin-script.js', array( 'jquery' ), GH_WP_UPDATER_VERSION, true );

		// Localize script with necessary data
		wp_localize_script(
			'gh-wp-updater-js',
			'gh_wp_updater',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'security'     => wp_create_nonce( 'gh_wp_updater_nonce' ),
				'webhook_base' => get_rest_url( null, 'github-to-wp-updater/v1/webhook/' ),
				'repos'        => $this->get_repositories(),
				'logs'         => GH_WP_Updater_Logger::get_logs(),
				'strings'      => array(
					'confirm_delete' => __( 'Are you sure you want to delete this repository configuration? This will NOT delete the installed theme/plugin folder.', 'github-to-wp-updater' ),
					'confirm_clear'  => __( 'Are you sure you want to clear the update history logs?', 'github-to-wp-updater' ),
					'success_sync'   => __( 'Repository synchronized successfully.', 'github-to-wp-updater' ),
					'fail_sync'      => __( 'Repository synchronization failed.', 'github-to-wp-updater' ),
				),
			)
		);
	}

	/**
	 * Get all registered repositories
	 */
	private function get_repositories() {
		$repos = get_option( 'gh_wp_updater_repos', array() );
		return is_array( $repos ) ? $repos : array();
	}

	/**
	 * Render the Settings Page HTML
	 */
	public function render_admin_page() {
		?>
		<div class="wrap gh-wp-updater-wrapper">
			<div class="gh-wp-header">
				<div class="gh-wp-title-area">
					<div class="gh-wp-logo">
						<span class="dashicons dashicons-update-alt"></span>
					</div>
					<div>
						<h1><?php esc_html_e( 'GitHub to WP Sync', 'github-to-wp-updater' ); ?></h1>
						<p class="description"><?php esc_html_e( 'Auto-update and install themes or plugins directly from private and public GitHub repos.', 'github-to-wp-updater' ); ?></p>
					</div>
				</div>
				<button type="button" class="gh-wp-btn gh-wp-btn-primary" id="gh-wp-add-repo-btn">
					<span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Repository', 'github-to-wp-updater' ); ?>
				</button>
			</div>

			<div class="gh-wp-layout">
				<!-- Repositories Grid Section -->
				<div class="gh-wp-main-content">
					<div class="gh-wp-card-header">
						<h2><?php esc_html_e( 'Monitored Repositories', 'github-to-wp-updater' ); ?></h2>
					</div>
					<div class="gh-wp-repos-grid" id="gh-wp-repos-container">
						<!-- JavaScript will inject repository cards here -->
					</div>
				</div>

				<!-- Logs Sidebar -->
				<div class="gh-wp-sidebar">
					<div class="gh-wp-card-header">
						<h2><?php esc_html_e( 'Update History', 'github-to-wp-updater' ); ?></h2>
						<button type="button" class="gh-wp-btn-text" id="gh-wp-clear-logs-btn">
							<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear History', 'github-to-wp-updater' ); ?>
						</button>
					</div>
					<div class="gh-wp-logs-timeline" id="gh-wp-logs-container">
						<!-- JavaScript will inject log timeline here -->
					</div>
				</div>
			</div>

			<!-- Add/Edit Repository Modal -->
			<div class="gh-wp-modal" id="gh-wp-repo-modal">
				<div class="gh-wp-modal-overlay"></div>
				<div class="gh-wp-modal-container">
					<div class="gh-wp-modal-header">
						<h3 id="gh-wp-modal-title"><?php esc_html_e( 'Add Repository', 'github-to-wp-updater' ); ?></h3>
						<button type="button" class="gh-wp-modal-close">&times;</button>
					</div>
					<form id="gh-wp-repo-form">
						<input type="hidden" id="gh-wp-action-mode" value="add">
						<input type="hidden" id="gh-wp-old-slug" value="">

						<div class="gh-wp-form-group">
							<label for="gh-wp-owner-repo"><?php esc_html_e( 'GitHub Repo Path', 'github-to-wp-updater' ); ?> <span class="required">*</span></label>
							<input type="text" id="gh-wp-owner-repo" required placeholder="owner/repository (e.g. your-username/my-plugin-repo)">
							<p class="gh-wp-help-text"><?php esc_html_e( 'Specify the owner and repository name as it appears on GitHub.', 'github-to-wp-updater' ); ?></p>
						</div>

						<div class="gh-wp-form-row">
							<div class="gh-wp-form-group">
								<label for="gh-wp-type"><?php esc_html_e( 'Type', 'github-to-wp-updater' ); ?></label>
								<select id="gh-wp-type">
									<option value="plugin"><?php esc_html_e( 'Plugin', 'github-to-wp-updater' ); ?></option>
									<option value="theme"><?php esc_html_e( 'Theme', 'github-to-wp-updater' ); ?></option>
								</select>
							</div>
							<div class="gh-wp-form-group">
								<label for="gh-wp-branch"><?php esc_html_e( 'Target Branch', 'github-to-wp-updater' ); ?></label>
								<input type="text" id="gh-wp-branch" value="main" required placeholder="e.g. main">
							</div>
						</div>

						<div class="gh-wp-form-group">
							<label for="gh-wp-slug"><?php esc_html_e( 'Local Directory Slug', 'github-to-wp-updater' ); ?> <span class="required">*</span></label>
							<input type="text" id="gh-wp-slug" required placeholder="e.g. my-plugin-slug">
							<p class="gh-wp-help-text"><?php esc_html_e( 'The directory name where the theme/plugin will be installed inside wp-content.', 'github-to-wp-updater' ); ?></p>
						</div>

						<div class="gh-wp-form-group">
							<label for="gh-wp-token"><?php esc_html_e( 'GitHub Personal Access Token (PAT)', 'github-to-wp-updater' ); ?></label>
							<input type="password" id="gh-wp-token" placeholder="<?php esc_html_e( 'Enter token (leave blank for public repos)', 'github-to-wp-updater' ); ?>">
							<p class="gh-wp-help-text"><?php esc_html_e( 'Required for private repositories. Fine-grained or classic tokens with read access are supported.', 'github-to-wp-updater' ); ?></p>
						</div>

						<div class="gh-wp-modal-footer">
							<button type="button" class="gh-wp-btn gh-wp-btn-secondary gh-wp-modal-close-btn"><?php esc_html_e( 'Cancel', 'github-to-wp-updater' ); ?></button>
							<button type="submit" class="gh-wp-btn gh-wp-btn-primary" id="gh-wp-save-btn"><?php esc_html_e( 'Save Repository', 'github-to-wp-updater' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX Action: Save Repository
	 */
	public function ajax_save_repo() {
		check_ajax_referer( 'gh_wp_updater_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'github-to-wp-updater' ) ) );
		}

		$owner_repo = isset( $_POST['owner_repo'] ) ? sanitize_text_field( $_POST['owner_repo'] ) : '';
		$type       = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'plugin';
		$branch     = isset( $_POST['branch'] ) ? sanitize_text_field( $_POST['branch'] ) : 'main';
		$slug       = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$token      = isset( $_POST['token'] ) ? $_POST['token'] : ''; // Encryption handles sanitization/safety

		$mode     = isset( $_POST['mode'] ) ? sanitize_text_field( $_POST['mode'] ) : 'add';
		$old_slug = isset( $_POST['old_slug'] ) ? sanitize_key( $_POST['old_slug'] ) : '';

		// Validation
		if ( empty( $owner_repo ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Repo path and slug are required fields.', 'github-to-wp-updater' ) ) );
		}

		// Verify repo path format: owner/repo
		if ( ! preg_match( '/^[a-zA-Z0-9-_\.]+\/[a-zA-Z0-9-_\.]+$/', $owner_repo ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid GitHub repository path. Use format: owner/repository', 'github-to-wp-updater' ) ) );
		}

		$repos = $this->get_repositories();

		// Check duplicate slug on new creations, or if slug changes
		if ( ( 'add' === $mode && isset( $repos[ $slug ] ) ) || ( 'edit' === $mode && $old_slug !== $slug && isset( $repos[ $slug ] ) ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Repository with slug "%s" already exists.', 'github-to-wp-updater' ), $slug ) ) );
		}

		// Encrypt token
		$encrypted_token = '';
		if ( ! empty( $token ) ) {
			if ( '●●●●●●●●' === $token && 'edit' === $mode ) {
				// Retain old token
				$encrypted_token = isset( $repos[ $old_slug ]['token'] ) ? $repos[ $old_slug ]['token'] : '';
			} else {
				$encrypted_token = GH_WP_Updater_Helper::encrypt( $token );
			}
		}

		// Webhook secret: keep old one if editing, otherwise generate a new secure secret
		$webhook_secret = '';
		if ( 'edit' === $mode && isset( $repos[ $old_slug ]['webhook_secret'] ) ) {
			$webhook_secret = $repos[ $old_slug ]['webhook_secret'];
		} else {
			$webhook_secret = wp_generate_password( 24, false );
		}

		// If edit mode and slug changed, remove the old entry
		if ( 'edit' === $mode && $old_slug !== $slug ) {
			unset( $repos[ $old_slug ] );
		}

		// Set/Update configuration
		$repos[ $slug ] = array(
			'owner_repo'     => $owner_repo,
			'type'           => $type,
			'branch'         => $branch,
			'slug'           => $slug,
			'token'          => $encrypted_token,
			'webhook_secret' => $webhook_secret,
		);

		update_option( 'gh_wp_updater_repos', $repos );

		wp_send_json_success( array( 'repos' => $repos ) );
	}

	/**
	 * AJAX Action: Delete Repository
	 */
	public function ajax_delete_repo() {
		check_ajax_referer( 'gh_wp_updater_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'github-to-wp-updater' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing repository slug.', 'github-to-wp-updater' ) ) );
		}

		$repos = $this->get_repositories();

		if ( isset( $repos[ $slug ] ) ) {
			unset( $repos[ $slug ] );
			update_option( 'gh_wp_updater_repos', $repos );
			wp_send_json_success( array( 'repos' => $repos ) );
		}

		wp_send_json_error( array( 'message' => __( 'Repository not found.', 'github-to-wp-updater' ) ) );
	}

	/**
	 * AJAX Action: Trigger Sync Manual
	 */
	public function ajax_trigger_sync() {
		check_ajax_referer( 'gh_wp_updater_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'github-to-wp-updater' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing repository slug.', 'github-to-wp-updater' ) ) );
		}

		$repos = $this->get_repositories();

		if ( ! isset( $repos[ $slug ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Repository not configured.', 'github-to-wp-updater' ) ) );
		}

		$repo = $repos[ $slug ];

		try {
			// Fetch latest commit metadata from GitHub to enrich the manual sync logs
			$commit_sha  = 'manual';
			$commit_msg  = __( 'Manual Sync triggered from admin panel', 'github-to-wp-updater' );
			$author      = wp_get_current_user()->display_name;

			$owner_repo = $repo['owner_repo'];
			$branch     = $repo['branch'];
			$token      = GH_WP_Updater_Helper::decrypt( $repo['token'] );

			// Query GitHub API for commit details
			$api_url = sprintf( 'https://api.github.com/repos/%s/commits/%s', $owner_repo, $branch );
			$args    = array(
				'timeout'    => 15,
				'user-agent' => 'WordPress-GitHub-WP-Updater/' . GH_WP_UPDATER_VERSION,
				'headers'    => array(
					'Accept' => 'application/vnd.github+json',
				),
			);
			if ( ! empty( $token ) ) {
				$args['headers']['Authorization'] = 'Bearer ' . $token;
			}

			$commit_res = wp_remote_get( $api_url, $args );
			if ( ! is_wp_error( $commit_res ) && 200 === wp_remote_retrieve_response_code( $commit_res ) ) {
				$body = json_decode( wp_remote_retrieve_body( $commit_res ), true );
				if ( is_array( $body ) ) {
					$commit_sha = isset( $body['sha'] ) ? $body['sha'] : 'manual';
					if ( isset( $body['commit']['message'] ) ) {
						$commit_msg = $body['commit']['message'];
					}
					if ( isset( $body['commit']['author']['name'] ) ) {
						$author = $body['commit']['author']['name'] . ' (via Manual)';
					}
				}
			}

			// Run Sync
			$result = GH_WP_Updater_Engine::update_repository( $repo );

			if ( is_wp_error( $result ) ) {
				GH_WP_Updater_Logger::log(
					$slug,
					$owner_repo,
					$commit_sha,
					$author,
					$commit_msg,
					'failed',
					$result->get_error_message()
				);

				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}

			GH_WP_Updater_Logger::log(
				$slug,
				$owner_repo,
				$commit_sha,
				$author,
				$commit_msg,
				'success'
			);

			wp_send_json_success(
				array(
					'message' => __( 'Synchronized successfully.', 'github-to-wp-updater' ),
					'logs'    => GH_WP_Updater_Logger::get_logs(),
				)
			);
		} catch ( Throwable $e ) {
			wp_send_json_error( array( 'message' => 'PHP Exception: ' . $e->getMessage() ) );
		}
	}

	/**
	 * AJAX Action: Clear Logs
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'gh_wp_updater_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'github-to-wp-updater' ) ) );
		}

		GH_WP_Updater_Logger::clear_logs();
		wp_send_json_success( array( 'logs' => array() ) );
	}

	/**
	 * AJAX Action: Get Logs (refresh)
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'gh_wp_updater_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'github-to-wp-updater' ) ) );
		}

		wp_send_json_success( array( 'logs' => GH_WP_Updater_Logger::get_logs() ) );
	}
}
