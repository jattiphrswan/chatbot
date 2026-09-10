<?php
/**
 * Google Gemini Interactions API Client.
 *
 * @package SkyFish\GeminiChat
 */

namespace SkyFish\GeminiChat;

use SkyFish\GeminiChat\Admin\SettingsService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeminiClient
 *
 * Communicates with the Google Gemini Interactions API (v1).
 */
class GeminiClient {

	/**
	 * Production Google Gemini API Base URL (v1beta).
	 */
	public const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

	/**
	 * Legacy endpoint alias for backward compatibility.
	 */
	public const API_ENDPOINT = self::API_BASE;

	/**
	 * Default fallback model for interactions.
	 */
	public const DEFAULT_MODEL = 'gemini-2.5-flash';

	/**
	 * Default HTTP request timeout in seconds.
	 */
	public const DEFAULT_TIMEOUT = 30;

	/**
	 * Settings service instance.
	 *
	 * @var SettingsService|null
	 */
	private ?SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param SettingsService|null $settings_service Optional settings service dependency.
	 */
	public function __construct( ?SettingsService $settings_service = null ) {
		$this->settings_service = $settings_service;
	}

	/**
	 * Checks whether the Gemini API key is configured in the environment or server constant.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$key = $this->get_api_key();
		return ! empty( $key );
	}

	/**
	 * Resolves the Gemini API key server-side from environment or wp-config.php constant.
	 *
	 * API key is NEVER read from the database options or exposed to the client.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		// Priority 1: SettingsService resolution (covers env, constant, and encrypted db fallback).
		$resolved = SettingsService::get_provider_api_key( 'gemini' );
		if ( ! empty( $resolved ) ) {
			return $resolved;
		}

		// Direct environment variable fallback.
		$env_key = getenv( 'GEMINI_API_KEY' );
		if ( false !== $env_key && '' !== trim( (string) $env_key ) ) {
			return trim( (string) $env_key );
		}

		if ( isset( $_ENV['GEMINI_API_KEY'] ) && '' !== trim( (string) $_ENV['GEMINI_API_KEY'] ) ) {
			return trim( (string) $_ENV['GEMINI_API_KEY'] );
		}

		if ( isset( $_SERVER['GEMINI_API_KEY'] ) && '' !== trim( (string) $_SERVER['GEMINI_API_KEY'] ) ) {
			return trim( (string) $_SERVER['GEMINI_API_KEY'] );
		}

		// Direct Server-side constant fallback.
		if ( defined( 'GCA_GEMINI_API_KEY' ) && '' !== trim( (string) GCA_GEMINI_API_KEY ) ) {
			return trim( (string) GCA_GEMINI_API_KEY );
		}

		return '';
	}

	/**
	 * Returns the configured model name or default.
	 *
	 * @param string|null $override_model Optional model override.
	 * @return string
	 */
	public function get_model( ?string $override_model = null ): string {
		if ( ! empty( $override_model ) ) {
			return $override_model;
		}

		$settings = $this->settings_service ?? SettingsService::get_instance();
		$model    = $settings->get( 'model', self::DEFAULT_MODEL );

		return ! empty( $model ) ? (string) $model : self::DEFAULT_MODEL;
	}

	/**
	 * Returns the configured system instruction or default.
	 *
	 * @param string|null $override_system Optional instruction override.
	 * @return string
	 */
	public function get_system_instruction( ?string $override_system = null ): string {
		if ( null !== $override_system ) {
			return $override_system;
		}

		$settings = $this->settings_service ?? SettingsService::get_instance();
		$sys_inst = $settings->get( 'system_instruction', '' );

		return is_string( $sys_inst ) ? trim( $sys_inst ) : '';
	}

	/**
	 * Executes a lightweight connection test to Google Gemini API.
	 *
	 * Sends a minimal prompt ('ping') with maxOutputTokens: 1 to verify credentials
	 * and model availability with minimal token consumption and no cost surprise.
	 *
	 * @param string|null $override_model Optional model override to test.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( ?string $override_model = null ) {
		$result = $this->create_interaction(
			'ping',
			null,
			[
				'model'             => $this->get_model( $override_model ),
				'generation_config' => [
					'maxOutputTokens' => 1,
				],
				'timeout'           => 15,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return ! empty( $result['text'] ) || isset( $result['id'] );
	}

	/**
	 * Sends a message/input to the Gemini Interactions API.
	 *
	 * @param string      $input                  User text input.
	 * @param string|null $previous_interaction_id Optional interaction ID for continuous multi-turn thread.
	 * @param array       $options                Optional parameters (model, system_instruction, timeout, etc.).
	 * @return array|WP_Error Normalized interaction response array on success, WP_Error on failure.
	 */
	public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
		$trimmed_input = trim( $input );
		if ( '' === $trimmed_input ) {
			return new WP_Error(
				'GCA_GEMINI_INVALID_REQUEST',
				__( 'Input prompt cannot be empty.', 'gemini-chat-assistant' ),
				[ 'status' => 400 ]
			);
		}

		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'GCA_GEMINI_NOT_CONFIGURED',
				__( 'Gemini API key is not configured on the server.', 'gemini-chat-assistant' ),
				[ 'status' => 500 ]
			);
		}

		$model              = $this->get_model( $options['model'] ?? null );
		$system_instruction = $this->get_system_instruction( $options['system_instruction'] ?? null );

		$endpoint = self::API_BASE . '/' . rawurlencode( $model ) . ':generateContent';

		$contents = [];
		if ( ! empty( $options['history'] ) && is_array( $options['history'] ) ) {
			foreach ( $options['history'] as $turn ) {
				$turn_text = $turn['text'] ?? $turn['content'] ?? '';
				if ( empty( $turn['role'] ) || '' === trim( (string) $turn_text ) ) {
					continue;
				}
				$contents[] = [
					'role'  => 'user' === $turn['role'] ? 'user' : 'model',
					'parts' => [ [ 'text' => (string) $turn_text ] ],
				];
			}
		}
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [ 'text' => $trimmed_input ] ],
		];

		$payload = [
			'contents' => $contents,
			'model'    => $model,
		];

		if ( '' !== $system_instruction ) {
			$payload['system_instruction'] = [
				'parts' => [ [ 'text' => $system_instruction ] ],
			];
		}

		if ( ! empty( $previous_interaction_id ) ) {
			$payload['previous_interaction_id'] = $previous_interaction_id;
		}

		// Allow optional generation options if provided.
		if ( isset( $options['generation_config'] ) && is_array( $options['generation_config'] ) ) {
			$payload['generation_config'] = $options['generation_config'];
		}

		$timeout = isset( $options['timeout'] ) && is_numeric( $options['timeout'] )
			? absint( $options['timeout'] )
			: self::DEFAULT_TIMEOUT;

		$request_args = [
			'method'      => 'POST',
			'timeout'     => $timeout,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking'    => true,
			'sslverify'   => true,
			'headers'     => [
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $api_key,
			],
			'body'        => (string) wp_json_encode( $payload ),
		];

		$response = wp_remote_post( $endpoint, $request_args );

		if ( is_wp_error( $response ) ) {
			return $this->handle_transport_error( $response );
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return $this->parse_response( $response_body );
		}

		return $this->handle_http_error( $status_code, $response_body );
	}

	/**
	 * Parses a raw response body from the Gemini Interactions API into normalized format.
	 *
	 * @param array|string $raw_body Raw JSON string or decoded array response.
	 * @return array|WP_Error Normalized array or WP_Error on parsing failure.
	 */
	public function parse_response( $raw_body ) {
		if ( is_array( $raw_body ) ) {
			$data = $raw_body;
		} elseif ( is_string( $raw_body ) ) {
			$data = json_decode( $raw_body, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error(
					'GCA_GEMINI_INVALID_RESPONSE',
					__( 'Failed to decode Gemini API JSON response.', 'gemini-chat-assistant' ),
					[
						'status'     => 502,
						'json_error' => json_last_error_msg(),
					]
				);
			}
		} else {
			return new WP_Error(
				'GCA_GEMINI_INVALID_RESPONSE',
				__( 'Invalid response format received from Gemini API.', 'gemini-chat-assistant' ),
				[ 'status' => 502 ]
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'GCA_GEMINI_INVALID_RESPONSE',
				__( 'Empty or non-array payload returned from Gemini API.', 'gemini-chat-assistant' ),
				[ 'status' => 502 ]
			);
		}

		// Extract interaction ID.
		$interaction_id = '';
		if ( ! empty( $data['id'] ) ) {
			$interaction_id = (string) $data['id'];
		} elseif ( ! empty( $data['interaction_id'] ) ) {
			$interaction_id = (string) $data['interaction_id'];
		} elseif ( ! empty( $data['name'] ) ) {
			$interaction_id = (string) $data['name'];
		}

		// Extract model name.
		$model = ! empty( $data['model'] ) ? (string) $data['model'] : self::DEFAULT_MODEL;

		// Extract text: primary candidates[].content.parts[].text (Gemini v1beta generateContent).
		$text = '';
		if ( isset( $data['candidates'] ) && is_array( $data['candidates'] ) ) {
			$text_parts = [];
			foreach ( $data['candidates'] as $candidate ) {
				foreach ( $candidate['content']['parts'] ?? [] as $part ) {
					if ( isset( $part['text'] ) && '' !== trim( (string) $part['text'] ) ) {
						$text_parts[] = $part['text'];
					}
				}
			}
			$text = implode( "\n", $text_parts );
		}

		// Fallback for legacy Interactions API steps[] or output string.
		if ( '' === trim( $text ) ) {
			if ( isset( $data['steps'] ) && is_array( $data['steps'] ) ) {
				$text = $this->extract_text_from_steps( $data['steps'] );
			} elseif ( isset( $data['output'] ) && is_string( $data['output'] ) ) {
				$text = $data['output'];
			}
		}

		// Extract usage metadata (Google API uses usageMetadata; fallback to usage_metadata or usage).
		$usage = $this->parse_usage( $data['usageMetadata'] ?? $data['usage_metadata'] ?? $data['usage'] ?? [] );

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'GCA_GEMINI_EMPTY_RESPONSE',
				__( 'Gemini API returned an empty text response.', 'gemini-chat-assistant' ),
				[
					'status'         => 502,
					'interaction_id' => $interaction_id,
					'raw'            => $data,
				]
			);
		}

		return [
			'interaction_id' => $interaction_id,
			'model'          => $model,
			'text'           => trim( $text ),
			'usage'          => $usage,
			'raw'            => $data,
		];
	}

	/**
	 * Extracts model response text by traversing the steps[] array of the Interactions API.
	 *
	 * @param array $steps Array of interaction steps.
	 * @return string Extracted text parts concatenated.
	 */
	public function extract_text_from_steps( array $steps ): string {
		$text_parts = [];

		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			// Typical Interactions API step: type === 'model_output' or contains content.
			$type = $step['type'] ?? '';

			if ( 'model_output' === $type || empty( $type ) || isset( $step['content'] ) ) {
				if ( isset( $step['content'] ) ) {
					if ( is_string( $step['content'] ) ) {
						$text_parts[] = $step['content'];
					} elseif ( is_array( $step['content'] ) ) {
						foreach ( $step['content'] as $content_item ) {
							if ( is_array( $content_item ) ) {
								if ( isset( $content_item['text'] ) && is_string( $content_item['text'] ) ) {
									$text_parts[] = $content_item['text'];
								}
							} elseif ( is_string( $content_item ) ) {
								$text_parts[] = $content_item;
							}
						}
					}
				} elseif ( isset( $step['text'] ) && is_string( $step['text'] ) ) {
					$text_parts[] = $step['text'];
				}
			}
		}

		return implode( "\n\n", array_filter( array_map( 'trim', $text_parts ) ) );
	}

	/**
	 * Normalizes usage token counts from raw API response.
	 *
	 * @param array $raw_usage Raw usage array from API response.
	 * @return array Normalized token counts.
	 */
	public function parse_usage( array $raw_usage ): array {
		$input_tokens = 0;
		if ( isset( $raw_usage['promptTokenCount'] ) ) {
			$input_tokens = absint( $raw_usage['promptTokenCount'] );
		} elseif ( isset( $raw_usage['prompt_token_count'] ) ) {
			$input_tokens = absint( $raw_usage['prompt_token_count'] );
		} elseif ( isset( $raw_usage['total_input_tokens'] ) ) {
			$input_tokens = absint( $raw_usage['total_input_tokens'] );
		} elseif ( isset( $raw_usage['input_tokens'] ) ) {
			$input_tokens = absint( $raw_usage['input_tokens'] );
		}

		$output_tokens = 0;
		if ( isset( $raw_usage['candidatesTokenCount'] ) ) {
			$output_tokens = absint( $raw_usage['candidatesTokenCount'] );
		} elseif ( isset( $raw_usage['candidates_token_count'] ) ) {
			$output_tokens = absint( $raw_usage['candidates_token_count'] );
		} elseif ( isset( $raw_usage['total_output_tokens'] ) ) {
			$output_tokens = absint( $raw_usage['total_output_tokens'] );
		} elseif ( isset( $raw_usage['output_tokens'] ) ) {
			$output_tokens = absint( $raw_usage['output_tokens'] );
		}

		$thought_tokens = 0;
		if ( isset( $raw_usage['total_thought_tokens'] ) ) {
			$thought_tokens = absint( $raw_usage['total_thought_tokens'] );
		} elseif ( isset( $raw_usage['thought_tokens'] ) ) {
			$thought_tokens = absint( $raw_usage['thought_tokens'] );
		}

		$total_tokens = 0;
		if ( isset( $raw_usage['totalTokenCount'] ) ) {
			$total_tokens = absint( $raw_usage['totalTokenCount'] );
		} elseif ( isset( $raw_usage['total_token_count'] ) ) {
			$total_tokens = absint( $raw_usage['total_token_count'] );
		} elseif ( isset( $raw_usage['total_tokens'] ) ) {
			$total_tokens = absint( $raw_usage['total_tokens'] );
		} else {
			$total_tokens = $input_tokens + $output_tokens + $thought_tokens;
		}

		return [
			'input_tokens'   => $input_tokens,
			'output_tokens'  => $output_tokens,
			'thought_tokens' => $thought_tokens,
			'total_tokens'   => $total_tokens,
		];
	}

	/**
	 * Handles network/transport errors returned by wp_remote_post().
	 *
	 * @param WP_Error $error WordPress HTTP error.
	 * @return WP_Error
	 */
	private function handle_transport_error( WP_Error $error ): WP_Error {
		$error_message = $error->get_error_message();

		// Detect timeout (e.g. cURL error 28 / Operation timed out).
		if ( false !== stripos( $error_message, 'timed out' ) || false !== stripos( $error_message, 'cURL error 28' ) ) {
			return new WP_Error(
				'GCA_GEMINI_TIMEOUT',
				__( 'The request to Gemini API timed out.', 'gemini-chat-assistant' ),
				[ 'status' => 504 ]
			);
		}

		return new WP_Error(
			'GCA_GEMINI_UNAVAILABLE',
			sprintf(
				/* translators: %s: Transport error message */
				__( 'Network error connecting to Gemini API: %s', 'gemini-chat-assistant' ),
				$error_message
			),
			[ 'status' => 503 ]
		);
	}

	/**
	 * Maps HTTP response status codes and error payloads to normalized WP_Error objects.
	 *
	 * @param int    $status_code   HTTP status code.
	 * @param string $response_body Response body string.
	 * @return WP_Error
	 */
	private function handle_http_error( int $status_code, string $response_body ): WP_Error {
		$error_message = '';
		$decoded       = json_decode( $response_body, true );

		if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) ) {
			$error_message = sanitize_text_field( (string) $decoded['error']['message'] );
		}

		switch ( $status_code ) {
			case 400:
				$code = 'GCA_GEMINI_INVALID_REQUEST';
				$msg  = ! empty( $error_message )
					? $error_message
					: __( 'Invalid request sent to Gemini API.', 'gemini-chat-assistant' );
				break;

			case 401:
			case 403:
				$code = 'GCA_GEMINI_AUTH_ERROR';
				$msg  = ! empty( $error_message )
					? $error_message
					: __( 'Authentication failed with Gemini API. Check your API key.', 'gemini-chat-assistant' );
				break;

			case 404:
				$code = 'GCA_GEMINI_MODEL_UNAVAILABLE';
				$msg  = ! empty( $error_message )
					? $error_message
					: __( 'Configured Gemini model was not found or is unavailable.', 'gemini-chat-assistant' );
				break;

			case 429:
				if ( false !== stripos( $error_message, 'quota' ) ) {
					$code = 'GCA_GEMINI_QUOTA_ERROR';
					$msg  = ! empty( $error_message )
						? $error_message
						: __( 'Gemini API quota exceeded.', 'gemini-chat-assistant' );
				} else {
					$code = 'GCA_GEMINI_RATE_LIMITED';
					$msg  = ! empty( $error_message )
						? $error_message
						: __( 'Gemini API rate limit reached. Please try again later.', 'gemini-chat-assistant' );
				}
				break;

			case 500:
			case 502:
			case 503:
			case 504:
				$code = 'GCA_GEMINI_UNAVAILABLE';
				$msg  = ! empty( $error_message )
					? $error_message
					: __( 'Gemini API service is currently unavailable. Please try again later.', 'gemini-chat-assistant' );
				break;

			default:
				$code = 'GCA_GEMINI_INVALID_RESPONSE';
				$msg  = ! empty( $error_message )
					? $error_message
					: sprintf(
						/* translators: %d: HTTP status code */
						__( 'Gemini API returned unexpected HTTP status %d.', 'gemini-chat-assistant' ),
						$status_code
					);
				break;
		}

		return new WP_Error(
			$code,
			$msg,
			[
				'status'      => $status_code,
				'api_message' => $error_message,
			]
		);
	}
}
