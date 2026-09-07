<?php
/**
 * Integration Action Argument Validator.
 *
 * @package SkyFish\GeminiChat\Integrations
 */

namespace SkyFish\GeminiChat\Integrations;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionValidator
 *
 * Deterministically validates, casts, and sanitizes input arguments against an Action's input schema.
 * Rejects unknown arguments and enforces types, constraints, and required fields.
 */
class ActionValidator {

	/**
	 * Validates input arguments against a given schema.
	 *
	 * @param array<string, mixed>                 $arguments Raw or provided arguments.
	 * @param array<string, array<string, mixed>>  $schema    Action input schema.
	 * @return array{valid: bool, sanitized: array<string, mixed>, errors: array<string, string>}
	 */
	public static function validate( array $arguments, array $schema ): array {
		$sanitized = [];
		$errors    = [];

		// 1. Check for unknown arguments (strictly disallowed).
		foreach ( array_keys( $arguments ) as $key ) {
			if ( ! isset( $schema[ $key ] ) ) {
				$errors[ $key ] = sprintf( 'Unknown argument "%s" is not permitted.', sanitize_text_field( (string) $key ) );
			}
		}

		if ( ! empty( $errors ) ) {
			return [
				'valid'     => false,
				'sanitized' => [],
				'errors'    => $errors,
			];
		}

		// 2. Validate and sanitize each expected parameter.
		foreach ( $schema as $name => $rules ) {
			$type        = $rules['type'] ?? 'string';
			$required    = ! empty( $rules['required'] );
			$is_provided = array_key_exists( $name, $arguments );

			if ( ! $is_provided ) {
				if ( $required ) {
					$errors[ $name ] = sprintf( 'Required argument "%s" is missing.', $name );
				} elseif ( array_key_exists( 'default', $rules ) ) {
					$sanitized[ $name ] = $rules['default'];
				}
				continue;
			}

			$val = $arguments[ $name ];

			switch ( $type ) {
				case 'string':
					if ( ! is_string( $val ) && ! is_numeric( $val ) ) {
						$errors[ $name ] = sprintf( 'Argument "%s" must be a string.', $name );
						break;
					}
					$clean_str = sanitize_text_field( (string) $val );

					if ( isset( $rules['max_length'] ) && mb_strlen( $clean_str, 'UTF-8' ) > (int) $rules['max_length'] ) {
						$errors[ $name ] = sprintf(
							'Argument "%s" exceeds maximum length of %d characters.',
							$name,
							(int) $rules['max_length']
						);
						break;
					}

					if ( isset( $rules['min_length'] ) && mb_strlen( $clean_str, 'UTF-8' ) < (int) $rules['min_length'] ) {
						$errors[ $name ] = sprintf(
							'Argument "%s" must be at least %d characters.',
							$name,
							(int) $rules['min_length']
						);
						break;
					}

					$sanitized[ $name ] = $clean_str;
					break;

				case 'integer':
					if ( ! is_numeric( $val ) || (int) $val != $val ) {
						$errors[ $name ] = sprintf( 'Argument "%s" must be an integer.', $name );
						break;
					}
					$int_val = (int) $val;

					if ( isset( $rules['min'] ) && $int_val < (int) $rules['min'] ) {
						$errors[ $name ] = sprintf( 'Argument "%s" must be at least %d.', $name, (int) $rules['min'] );
						break;
					}

					if ( isset( $rules['max'] ) && $int_val > (int) $rules['max'] ) {
						$errors[ $name ] = sprintf( 'Argument "%s" cannot exceed %d.', $name, (int) $rules['max'] );
						break;
					}

					$sanitized[ $name ] = $int_val;
					break;

				case 'number':
					if ( ! is_numeric( $val ) ) {
						$errors[ $name ] = sprintf( 'Argument "%s" must be a number.', $name );
						break;
					}
					$num_val = (float) $val;

					if ( isset( $rules['min'] ) && $num_val < (float) $rules['min'] ) {
						$errors[ $name ] = sprintf( 'Argument "%s" must be at least %s.', $name, (string) $rules['min'] );
						break;
					}

					if ( isset( $rules['max'] ) && $num_val > (float) $rules['max'] ) {
						$errors[ $name ] = sprintf( 'Argument "%s" cannot exceed %s.', $name, (string) $rules['max'] );
						break;
					}

					$sanitized[ $name ] = $num_val;
					break;

				case 'boolean':
					$sanitized[ $name ] = filter_var( $val, FILTER_VALIDATE_BOOLEAN );
					break;

				case 'enum':
					$allowed_values = is_array( $rules['enum'] ?? null ) ? $rules['enum'] : [];
					if ( ! in_array( $val, $allowed_values, true ) ) {
						$errors[ $name ] = sprintf(
							'Argument "%s" has invalid value. Allowed: [%s].',
							$name,
							implode( ', ', array_map( 'strval', $allowed_values ) )
						);
						break;
					}
					$sanitized[ $name ] = $val;
					break;

				default:
					$errors[ $name ] = sprintf( 'Unknown type "%s" declared for argument "%s".', $type, $name );
					break;
			}
		}

		return [
			'valid'     => empty( $errors ),
			'sanitized' => $sanitized,
			'errors'    => $errors,
		];
	}
}
