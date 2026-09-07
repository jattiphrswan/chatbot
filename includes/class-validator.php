<?php
/**
 * REST API & Application Input Validator.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Database\SessionService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Validator
 *
 * Validates, normalizes, and sanitizes inbound REST API payload fields.
 */
class Validator {

	public const DEFAULT_MAX_MESSAGE_LENGTH = 1000;
	public const MIN_SESSION_ID_LENGTH       = 8;
	public const MAX_SESSION_ID_LENGTH       = 128;

	/**
	 * Validates and sanitizes a chat message string.
	 *
	 * @param mixed $message    Raw input message.
	 * @param int   $max_length Maximum allowed message character length.
	 * @return string|WP_Error Sanitized message string or WP_Error on validation failure.
	 */
	public static function validate_message( $message, int $max_length = self::DEFAULT_MAX_MESSAGE_LENGTH ) {
		if ( ! is_string( $message ) ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Message must be a text string.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$trimmed = trim( $message );
		if ( '' === $trimmed ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Please enter a valid message.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$max_len = $max_length > 0 ? $max_length : self::DEFAULT_MAX_MESSAGE_LENGTH;
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $trimmed, 'UTF-8' ) : strlen( $trimmed );

		if ( $length > $max_len ) {
			return new WP_Error(
				'INVALID_INPUT',
				sprintf(
					/* translators: %d: Maximum allowed character length */
					__( 'Message exceeds the maximum allowed length of %d characters.', 'gemini-chat-assistant' ),
					$max_len
				),
				[ 'status' => 400 ]
			);
		}

		// Clean null bytes and control chars while preserving normal UTF-8 text and newlines.
		$sanitized = str_replace( "\0", '', $trimmed );

		return $sanitized;
	}

	/**
	 * Validates and normalizes a browser session identifier.
	 *
	 * Session tokens must be opaque strings between 8 and 128 characters.
	 *
	 * @param mixed $session_id Candidate session token.
	 * @return string|WP_Error Normalized session ID or WP_Error.
	 */
	public static function validate_session_id( $session_id ) {
		if ( ! is_string( $session_id ) ) {
			return new WP_Error(
				'SESSION_INVALID',
				__( 'Session identifier must be a string.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$trimmed = trim( $session_id );
		$len     = strlen( $trimmed );

		if ( $len < self::MIN_SESSION_ID_LENGTH || $len > self::MAX_SESSION_ID_LENGTH ) {
			return new WP_Error(
				'SESSION_INVALID',
				sprintf(
					/* translators: 1: Min length, 2: Max length */
					__( 'Session identifier must be between %1$d and %2$d characters.', 'gemini-chat-assistant' ),
					self::MIN_SESSION_ID_LENGTH,
					self::MAX_SESSION_ID_LENGTH
				),
				[ 'status' => 400 ]
			);
		}

		// Only allow safe characters (alphanumeric, underscore, hyphen).
		if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $trimmed ) ) {
			return new WP_Error(
				'SESSION_INVALID',
				__( 'Session identifier contains invalid characters.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		return $trimmed;
	}

	/**
	 * Validates and sanitizes optional page context metadata.
	 *
	 * @param mixed $context Inbound context parameter.
	 * @return array Sanitized context array.
	 */
	public static function validate_context( $context ): array {
		if ( ! is_array( $context ) ) {
			return [];
		}

		$sanitized = [];

		if ( isset( $context['page_id'] ) ) {
			$sanitized['page_id'] = absint( $context['page_id'] );
		}

		if ( isset( $context['page_title'] ) && is_string( $context['page_title'] ) ) {
			$sanitized['page_title'] = sanitize_text_field( mb_substr( trim( $context['page_title'] ), 0, 255 ) );
		}

		if ( isset( $context['page_url'] ) && is_string( $context['page_url'] ) ) {
			$sanitized['page_url'] = esc_url_raw( trim( $context['page_url'] ) );
		}

		return $sanitized;
	}
}
