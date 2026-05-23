<?php
/**
 * Webhook handler class for GitHub to WP Deployer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GH_WP_Updater_Webhook {

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the Webhook REST API route
	 */
	public function register_routes() {
		register_rest_route(
			'github-to-wp-updater/v1',
			'/webhook/(?P<slug>[a-zA-Z0-9-_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true', // Validation happens inside via HMAC signature
			)
		);
	}

	/**
	 * Handle the incoming webhook requests
	 *
	 * @param WP_REST_Request $request The REST request object
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$slug = $request->get_param( 'slug' );

		// Load all repositories
		$repos = get_option( 'gh_wp_updater_repos', array() );
		if ( ! is_array( $repos ) || ! isset( $repos[ $slug ] ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Repository not found in settings.',
				),
				404
			);
		}

		$repo = $repos[ $slug ];

		// Verify signature if secret is defined (it should be!)
		$webhook_secret = isset( $repo['webhook_secret'] ) ? $repo['webhook_secret'] : '';
		if ( ! empty( $webhook_secret ) ) {
			$signature_header = $request->get_header( 'x-hub-signature-256' );
			if ( empty( $signature_header ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Missing X-Hub-Signature-256 header.',
					),
					401
				);
			}

			// Format is: sha256=HEX_SIGNATURE
			$parts = explode( '=', $signature_header, 2 );
			if ( 2 !== count( $parts ) || 'sha256' !== $parts[0] ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Invalid signature format.',
					),
					400
				);
			}

			$payload = $request->get_body();
			$expected_signature = hash_hmac( 'sha256', $payload, $webhook_secret );

			if ( ! hash_equals( $expected_signature, $parts[1] ) ) {
				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Webhook signature verification failed.',
					),
					403
				);
			}
		}

		// Parse the body payload
		$payload_body = $request->get_body();
		$payload_data = json_decode( $payload_body, true );

		// If raw body JSON parsing fails, check if URL-encoded form data with 'payload' parameter
		if ( empty( $payload_data ) ) {
			$form_payload = $request->get_param( 'payload' );
			if ( ! empty( $form_payload ) ) {
				$payload_data = json_decode( $form_payload, true );
			}
		}

		if ( empty( $payload_data ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'error',
					'message' => 'Invalid payload JSON.',
				),
				400
			);
		}

		// Handle Ping event from GitHub
		// GitHub sends a header 'X-GitHub-Event: ping'
		$event = $request->get_header( 'x-github-event' );
		if ( 'ping' === $event ) {
			return new WP_REST_Response(
				array(
					'status'  => 'success',
					'message' => 'Ping event received successfully.',
				),
				200
			);
		}

		// For push events, verify the branch
		if ( 'push' === $event || empty( $event ) ) {
			$ref = isset( $payload_data['ref'] ) ? $payload_data['ref'] : '';
			$configured_branch = isset( $repo['branch'] ) ? $repo['branch'] : 'main';
			$expected_ref = 'refs/heads/' . $configured_branch;

			// If the ref doesn't match our branch, skip but return success (since it's a valid webhook, just not our branch)
			if ( $ref !== $expected_ref ) {
				return new WP_REST_Response(
					array(
						'status'  => 'skipped',
						'message' => sprintf( 'Branch %s does not match configured branch %s. Ignored.', $ref, $expected_ref ),
					),
					200
				);
			}

			// Extract commit details
			$head_commit = isset( $payload_data['head_commit'] ) ? $payload_data['head_commit'] : array();
			$commit_sha  = isset( $head_commit['id'] ) ? $head_commit['id'] : 'unknown';
			$commit_msg  = isset( $head_commit['message'] ) ? $head_commit['message'] : 'Auto-update triggered by webhook push';
			$author      = isset( $head_commit['author']['name'] ) ? $head_commit['author']['name'] : 'GitHub Webhook';

			// Trigger update
			$update_result = GH_WP_Updater_Engine::update_repository( $repo );

			if ( is_wp_error( $update_result ) ) {
				GH_WP_Updater_Logger::log(
					$slug,
					$repo['owner_repo'],
					$commit_sha,
					$author,
					$commit_msg,
					'failed',
					$update_result->get_error_message()
				);

				return new WP_REST_Response(
					array(
						'status'  => 'error',
						'message' => 'Update failed: ' . $update_result->get_error_message(),
					),
					500
				);
			}

			// Log success
			GH_WP_Updater_Logger::log(
				$slug,
				$repo['owner_repo'],
				$commit_sha,
				$author,
				$commit_msg,
				'success'
			);

			return new WP_REST_Response(
				array(
					'status'  => 'success',
					'message' => 'Repository updated and deployed successfully.',
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'status'  => 'ignored',
				'message' => 'Unsupported webhook event: ' . $event,
			),
			200
		);
	}
}
