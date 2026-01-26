<?php
/**
 * Encryption utility for securing sensitive data.
 *
 * @package CompetitorKnowledge
 */

declare(strict_types=1);

namespace CompetitorKnowledge\Core;

/**
 * Class Encryption
 *
 * Handles encryption and decryption of sensitive data.
 *
 * @package CompetitorKnowledge\Core
 */
class Encryption {

	/**
	 * Encryption method.
	 */
	private const METHOD = 'AES-256-CBC';

	/**
	 * Get encryption key derived from WordPress salts.
	 *
	 * @return string
	 */
	private static function get_key(): string {
		// Use WordPress salts for key derivation.
		$salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'competitor-knowledge-fallback-salt';
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Encrypt a string.
	 *
	 * @param string $data The data to encrypt.
	 *
	 * @return string The encrypted data (base64 encoded).
	 */
	public static function encrypt( string $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		$key       = self::get_key();
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		if ( false === $iv_length ) {
			return '';
		}
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $data, self::METHOD, $key, 0, $iv );

		// Combine IV and encrypted data, then base64 encode.
		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a string.
	 *
	 * @param string $data The encrypted data (base64 encoded).
	 *
	 * @return string The decrypted data.
	 */
	public static function decrypt( string $data ): string {
		if ( empty( $data ) ) {
			return '';
		}

		// Check if data is encrypted (base64 encoded).
		$decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded ) {
			// Not base64, assume it's plain text (backward compatibility).
			return $data;
		}

		$key       = self::get_key();
		$iv_length = openssl_cipher_iv_length( self::METHOD );
		if ( false === $iv_length ) {
			return $data;
		}

		// Extract IV and encrypted data.
		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::METHOD, $key, 0, $iv );

		// If decryption fails, return original (might be unencrypted).
		return false !== $decrypted ? $decrypted : $data;
	}

	/**
	 * Check if a string is encrypted.
	 *
	 * @param string $data The data to check.
	 *
	 * @return bool
	 */
	public static function is_encrypted( string $data ): bool {
		if ( empty( $data ) ) {
			return false;
		}

		$decoded = base64_decode( $data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false !== $decoded && strlen( $decoded ) > openssl_cipher_iv_length( self::METHOD );
	}
}
