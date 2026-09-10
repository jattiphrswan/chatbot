<?php
/**
 * SecretStore Service for Gemini Chat Assistant.
 *
 * Provides dedicated encrypted storage, retrieval, deletion, and masking
 * for provider API keys and sensitive credentials.
 *
 * @package SkyFish\GeminiChat\Security
 */

namespace SkyFish\GeminiChat\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecretStore
 *
 * Secure storage vault for provider API secrets.
 */
class SecretStore {

	/**
	 * WordPress option key where encrypted provider credentials reside.
	 */
	public const CREDENTIALS_OPTION_KEY = 'gca_provider_credentials';

	/**
	 * Stores an API key encrypted for a specific provider.
	 *
	 * @param string $provider Provider identifier ('gemini', 'openai', 'claude').
	 * @param string $key      Raw plaintext API key.
	 * @return bool True if successfully stored, false otherwise.
	 */
	public static function store( string $provider, string $key ): bool {
		$provider = sanitize_key( $provider );
		$key      = trim( $key );

		if ( empty( $provider ) || empty( $key ) ) {
			return false;
		}

		$credentials = get_option( self::CREDENTIALS_OPTION_KEY, [] );
		if ( ! is_array( $credentials ) ) {
			$credentials = [];
		}

		$credentials[ $provider ] = self::encrypt( $key );
		return update_option( self::CREDENTIALS_OPTION_KEY, $credentials, 'no' );
	}

	/**
	 * Retrieves and decrypts the stored API key for a specific provider.
	 *
	 * @param string $provider Provider identifier.
	 * @return string Decrypted plaintext key or empty string if not found.
	 */
	public static function retrieve( string $provider ): string {
		$provider = sanitize_key( $provider );
		if ( empty( $provider ) ) {
			return '';
		}

		$credentials = get_option( self::CREDENTIALS_OPTION_KEY, [] );
		if ( ! is_array( $credentials ) || empty( $credentials[ $provider ] ) ) {
			return '';
		}

		return self::decrypt( (string) $credentials[ $provider ] );
	}

	/**
	 * Explicitly removes a stored credential for a provider.
	 *
	 * @param string $provider Provider identifier.
	 * @return bool True if successfully removed or was not present.
	 */
	public static function delete( string $provider ): bool {
		$provider = sanitize_key( $provider );
		if ( empty( $provider ) ) {
			return false;
		}

		$credentials = get_option( self::CREDENTIALS_OPTION_KEY, [] );
		if ( ! is_array( $credentials ) ) {
			$credentials = [];
		}

		if ( isset( $credentials[ $provider ] ) ) {
			unset( $credentials[ $provider ] );
			return update_option( self::CREDENTIALS_OPTION_KEY, $credentials, 'no' );
		}

		return true;
	}

	/**
	 * Checks if a stored credential exists in the database for a provider.
	 *
	 * @param string $provider Provider identifier.
	 * @return bool True if credential is stored.
	 */
	public static function has( string $provider ): bool {
		$provider = sanitize_key( $provider );
		$credentials = get_option( self::CREDENTIALS_OPTION_KEY, [] );
		return is_array( $credentials ) && ! empty( $credentials[ $provider ] );
	}

	/**
	 * Masks an API key for safe UI display or placeholders.
	 *
	 * Shows key prefix and trailing characters while concealing the sensitive core.
	 * e.g., AIzaSyD1234567890abcdef -> AIza••••••••cdef
	 * Short keys (<= 8 chars) are replaced with fixed bullets.
	 *
	 * @param string $key Plaintext API key.
	 * @return string Masked key representation.
	 */
	public static function mask( string $key ): string {
		$key = trim( $key );
		if ( empty( $key ) ) {
			return '';
		}

		$len = strlen( $key );
		if ( $len <= 8 ) {
			return '••••••••';
		}

		// Keep first 4 and last 4 characters, with 8 bullets in between.
		$prefix = substr( $key, 0, 4 );
		$suffix = substr( $key, -4 );

		return $prefix . '••••••••' . $suffix;
	}

	/**
	 * Retrieves the masked representation of a stored credential.
	 *
	 * @param string $provider Provider identifier.
	 * @return string Masked key or empty string.
	 */
	public static function get_masked( string $provider ): string {
		$key = self::retrieve( $provider );
		return ! empty( $key ) ? self::mask( $key ) : '';
	}

	/**
	 * Encrypts a plaintext string.
	 *
	 * Primary: OpenSSL AES-256-CBC with WordPress AUTH_KEY salt.
	 * Fallback: XOR obfuscation with AUTH_KEY salt if OpenSSL is unavailable.
	 *
	 * @param string $plaintext Data to encrypt.
	 * @return string Encrypted or obfuscated payload with identifier prefix.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( empty( $plaintext ) ) {
			return '';
		}

		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'gca_fallback_salt_key_18';

		if ( function_exists( 'openssl_encrypt' ) ) {
			$key = hash( 'sha256', $salt, true );
			$iv  = openssl_random_pseudo_bytes( 16 );
			$enc = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			if ( false !== $enc ) {
				return 'enc:' . base64_encode( $iv . $enc );
			}
		}

		// Fallback XOR obfuscation if OpenSSL is not compiled.
		$out  = '';
		$len  = strlen( $plaintext );
		$slen = strlen( $salt );
		for ( $i = 0; $i < $len; $i++ ) {
			$out .= chr( ord( $plaintext[ $i ] ) ^ ord( $salt[ $i % $slen ] ) );
		}

		return 'obf:' . base64_encode( $out );
	}

	/**
	 * Decrypts an encrypted or obfuscated payload.
	 *
	 * Supports both 'enc:' (AES-256-CBC) and legacy/fallback 'obf:' prefixes.
	 *
	 * @param string $encoded Encoded payload string.
	 * @return string Decrypted plaintext or empty string on failure.
	 */
	public static function decrypt( string $encoded ): string {
		if ( empty( $encoded ) ) {
			return '';
		}

		$salt = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'gca_fallback_salt_key_18';

		// AES-256-CBC format.
		if ( 0 === strpos( $encoded, 'enc:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $encoded, 4 ) );
			if ( strlen( $raw ) > 16 ) {
				$iv  = substr( $raw, 0, 16 );
				$enc = substr( $raw, 16 );
				$key = hash( 'sha256', $salt, true );
				$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
				if ( false !== $dec ) {
					return $dec;
				}
			}
		}

		// Obfuscation format.
		if ( 0 === strpos( $encoded, 'obf:' ) ) {
			$raw  = base64_decode( substr( $encoded, 4 ) );
			$out  = '';
			$len  = strlen( $raw );
			$slen = strlen( $salt );
			for ( $i = 0; $i < $len; $i++ ) {
				$out .= chr( ord( $raw[ $i ] ) ^ ord( $salt[ $i % $slen ] ) );
			}
			return $out;
		}

		return '';
	}

	/**
	 * Tests whether strong encryption (OpenSSL AES-256-CBC) is available in current PHP environment.
	 *
	 * @return bool True if OpenSSL is functional.
	 */
	public static function is_encryption_available(): bool {
		return function_exists( 'openssl_encrypt' ) && function_exists( 'openssl_decrypt' );
	}
}
