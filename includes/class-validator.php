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

	/**
	 * Validates and sanitizes a visitor full name.
	 *
	 * Supports Unicode characters, trims whitespace, enforces max 100 characters.
	 *
	 * @param mixed $name     Raw name input.
	 * @param bool  $required Whether the field is mandatory.
	 * @return string|WP_Error Sanitized string or WP_Error.
	 */
	public static function validate_name( $name, bool $required = false ) {
		$str = is_string( $name ) ? trim( $name ) : '';

		if ( '' === $str ) {
			if ( $required ) {
				return new WP_Error(
					'FIELD_REQUIRED',
					__( 'Please enter your name.', 'gemini-chat-assistant' ),
					[ 'status' => 400 ]
				);
			}
			return '';
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $str, 'UTF-8' ) : strlen( $str );
		if ( $length > 100 ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Name cannot exceed 100 characters.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		return sanitize_text_field( $str );
	}

	/**
	 * Validates and sanitizes an email address.
	 *
	 * Enforces is_email() and max 254 characters.
	 *
	 * @param mixed $email    Raw email input.
	 * @param bool  $required Whether the field is mandatory.
	 * @return string|WP_Error Sanitized email or WP_Error.
	 */
	public static function validate_email( $email, bool $required = false ) {
		$str = is_string( $email ) ? trim( $email ) : '';

		if ( '' === $str ) {
			if ( $required ) {
				return new WP_Error(
					'FIELD_REQUIRED',
					__( 'Please enter your email address.', 'gemini-chat-assistant' ),
					[ 'status' => 400 ]
				);
			}
			return '';
		}

		if ( strlen( $str ) > 254 || ! is_email( $str ) ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Please enter a valid email address.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		return sanitize_email( $str );
	}

	/**
	 * Validates and sanitizes a phone number.
	 *
	 * Allows international formats (+, digits, spaces, -, (, ), .), rejects scripts and letters.
	 *
	 * @param mixed $phone    Raw phone input.
	 * @param bool  $required Whether the field is mandatory.
	 * @return string|WP_Error Sanitized phone string or WP_Error.
	 */
	public static function validate_phone( $phone, bool $required = false ) {
		$str = is_string( $phone ) ? trim( $phone ) : '';

		if ( '' === $str ) {
			if ( $required ) {
				return new WP_Error(
					'FIELD_REQUIRED',
					__( 'Please enter your phone number.', 'gemini-chat-assistant' ),
					[ 'status' => 400 ]
				);
			}
			return '';
		}

		if ( strlen( $str ) > 50 ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Phone number is too long.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		// Allow +, digits, spaces, -, (, ), .
		if ( ! preg_match( '/^[0-9+\s\-\(\)\.]{6,50}$/', $str ) ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Please enter a valid phone number.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		// Count actual digits to ensure phone-like content
		$digits_count = preg_match_all( '/[0-9]/', $str );
		if ( $digits_count < 6 ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Please enter a valid phone number with at least 6 digits.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		return sanitize_text_field( $str );
	}

	/**
	 * Validates and sanitizes the requirement / message inquiry textarea.
	 *
	 * Enforces max 2000 characters.
	 *
	 * @param mixed $requirement Raw requirement input.
	 * @param bool  $required    Whether the field is mandatory.
	 * @return string|WP_Error Sanitized requirement or WP_Error.
	 */
	public static function validate_requirement( $requirement, bool $required = false ) {
		$str = is_string( $requirement ) ? trim( $requirement ) : '';

		if ( '' === $str ) {
			if ( $required ) {
				return new WP_Error(
					'FIELD_REQUIRED',
					__( 'Please describe how we can help you.', 'gemini-chat-assistant' ),
					[ 'status' => 400 ]
				);
			}
			return '';
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $str, 'UTF-8' ) : strlen( $str );
		if ( $length > 2000 ) {
			return new WP_Error(
				'INVALID_INPUT',
				__( 'Requirement cannot exceed 2000 characters.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		return sanitize_textarea_field( $str );
	}

	/**
	 * Authoritatively validates pre-chat submission payload against configured settings.
	 *
	 * @param array<string, mixed> $payload  Inbound raw pre-chat data.
	 * @param array<string, mixed> $settings Active plugin settings.
	 * @return array<string, string>|WP_Error Sanitized fields array or WP_Error with field errors.
	 */
	public static function validate_prechat( array $payload, array $settings ) {
		$field_errors = [];
		$clean_data   = [
			'name'        => null,
			'email'       => null,
			'phone'       => null,
			'requirement' => null,
		];

		// 1. Name field
		if ( ! empty( $settings['collect_name'] ) ) {
			$is_required = ! empty( $settings['require_name'] );
			$val         = self::validate_name( $payload['name'] ?? '', $is_required );
			if ( is_wp_error( $val ) ) {
				$field_errors['name'] = $val->get_error_message();
			} else {
				$clean_data['name'] = '' !== $val ? $val : null;
			}
		}

		// 2. Email field
		if ( ! empty( $settings['collect_email'] ) ) {
			$is_required = ! empty( $settings['require_email'] );
			$val         = self::validate_email( $payload['email'] ?? '', $is_required );
			if ( is_wp_error( $val ) ) {
				$field_errors['email'] = $val->get_error_message();
			} else {
				$clean_data['email'] = '' !== $val ? $val : null;
			}
		}

		// 3. Phone field
		if ( ! empty( $settings['collect_phone'] ) ) {
			$is_required = ! empty( $settings['require_phone'] );
			$val         = self::validate_phone( $payload['phone'] ?? '', $is_required );
			if ( is_wp_error( $val ) ) {
				$field_errors['phone'] = $val->get_error_message();
			} else {
				$clean_data['phone'] = '' !== $val ? $val : null;
			}
		}

		// 4. Requirement field
		if ( ! empty( $settings['collect_requirement'] ) ) {
			$is_required = ! empty( $settings['require_requirement'] );
			$val         = self::validate_requirement( $payload['requirement'] ?? '', $is_required );
			if ( is_wp_error( $val ) ) {
				$field_errors['requirement'] = $val->get_error_message();
			} else {
				$clean_data['requirement'] = '' !== $val ? $val : null;
			}
		}

		if ( ! empty( $field_errors ) ) {
			return new WP_Error(
				'PRECHAT_VALIDATION_FAILED',
				__( 'Please correct the highlighted fields.', 'gemini-chat-assistant' ),
				[
					'status' => 400,
					'fields' => $field_errors,
				]
			);
		}

		return $clean_data;
	}
}
