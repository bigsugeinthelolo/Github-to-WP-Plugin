<?php
/**
 * Helper class for GitHub to WP Deployer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GH_WP_Updater_Helper {

	/**
	 * Encryption method
	 */
	private static $cipher = 'aes-256-cbc';

	/**
	 * Get the secret key for encryption/decryption.
	 * Combines WordPress salts to create a secure site-specific key.
	 */
	private static function get_secret_key() {
		$key = '';
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$key .= SECURE_AUTH_KEY;
		}
		if ( defined( 'AUTH_SALT' ) ) {
			$key .= AUTH_SALT;
		}
		
		// Fallback if keys are not defined
		if ( empty( $key ) ) {
			$key = 'gh_wp_updater_fallback_salt_key_123456';
		}
		
		return hash( 'sha256', $key );
	}

	/**
	 * Encrypt a string safely using AES-256-CBC
	 *
	 * @param string $data Plain text data
	 * @return string Encrypted base64 string
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$key = self::get_secret_key();
		$iv_length = openssl_cipher_iv_length( self::$cipher );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );
		
		if ( $encrypted === false ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a string safely using AES-256-CBC
	 *
	 * @param string $data Encrypted base64 string
	 * @return string Plain text data
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$data = base64_decode( $data );
		if ( $data === false ) {
			return '';
		}

		$key = self::get_secret_key();
		$iv_length = openssl_cipher_iv_length( self::$cipher );
		
		if ( strlen( $data ) < $iv_length ) {
			return '';
		}

		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv );
		
		if ( $decrypted === false ) {
			return '';
		}

		return $decrypted;
	}

	/**
	 * Get the WP_Filesystem instance, initializing it if necessary
	 *
	 * @return WP_Filesystem_Base|false WP_Filesystem object or false on failure
	 */
	public static function get_filesystem() {
		global $wp_filesystem;
		
		require_once ABSPATH . 'wp-admin/includes/file.php';
		
		if ( empty( $wp_filesystem ) ) {
			// Try direct access method first
			if ( ! function_exists( 'WP_Filesystem' ) || ! WP_Filesystem() ) {
				return false;
			}
		}
		
		return $wp_filesystem;
	}
}
