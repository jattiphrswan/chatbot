<?php
/**
 * Anthropic Claude HTTP Client for Messages API.
 *
 * @package SkyFish\GeminiChat\Providers
 */

namespace SkyFish\GeminiChat\Providers;

use SkyFish\GeminiChat\Admin\SettingsService;
use WP_Error;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClaudeClient
 *
 * Communicates with the Anthropic Messages API (POST https://api.anthropic.com/v1/messages)
 * using WordPress-native HTTP transport (wp_remote_post).
 */
class ClaudeClient {

	public const MESSAGES_ENDPOINT  = 'https://api.anthropic.com/v1/messages';
	public const ENDPOINT           = self::MESSAGES_ENDPOINT;
	public const ANTHROPIC_VERSION  = '2023-06-01';
	public const DEFAULT_TIMEOUT    = 30;
	public const DEFAULT_MAX_TOKENS = 1024;

	/**
	 * Optional API key override (for testing or direct injection).
	 */
	private ?string $api_key;

	/**
	 * Constructor.
	 *
	 * @param string|null $api_key Optional API key override.
	 */
	public function __construct( ?string $api_key = null ) {
		$this->api_key = $api_key;
	}

	/**
	 * Resolves the plaintext API key securely server-side.
	 *
	 * Priority: Injected key -> Environment variable -> wp-config constant -> Encrypted DB.
	 *
	 * @return string Plaintext key or empty string.
	 */
	public function get_api_key(): string {
		if ( ! empty( $this->api_key ) ) {
			return $this->api_key;
		}
		return SettingsService::get_provider_api_key( 'claude' );
	}

	/**
	 * Creates a response interaction with the Anthropic Messages API.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Normalized message array.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function create_response( array $messages, array $options = [] ): ProviderResponse {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			throw ProviderException::not_configured(
				'claude',
				__( 'Anthropic Claude API key is missing or not configured on the server.', 'gemini-chat-assistant' )
			);
		}

		$payload  = $this->build_payload( $messages, $options );
		$http_res = $this->execute_http_request( $payload, $api_key, $options );

		return $this->parse_response_body(
			$http_res['body'],
			$http_res['status_code'],
			$http_res['request_id'],
			$payload['model']
		);
	}

	/**
	 * Executes a minimal connection test to the Anthropic Messages API.
	 *
	 * Uses max_tokens = 1 to verify credentials and model availability
	 * at near-zero token cost.
	 *
	 * @return bool
	 * @throws ProviderException
	 */
	public function test_connection(): bool {
		$response = $this->create_response(
			[
				[
					'role'    => 'user',
					'content' => 'ping',
				],
			],
			[
				'max_tokens' => 1,
				'timeout'    => 15,
			]
		);

		return ! empty( $response->get_content() );
	}

	/**
	 * Normalizes conversation history into Anthropic Messages API input array.
	 *
	 * Only accepts 'user' and 'assistant' roles with non-empty string content.
	 *
	 * @param array<int, array{role?: string, content?: string}> $messages Raw conversation messages.
	 * @return array<int, array{role: string, content: string}> Sanitized input messages.
	 */
	public function normalize_input_messages( array $messages ): array {
		$normalized = [];

		foreach ( $messages as $msg ) {
			if ( ! is_array( $msg ) ) {
				continue;
			}
			$role = strtolower( trim( (string) ( $msg['role'] ?? 'user' ) ) );
			if ( ! in_array( $role, [ 'user', 'assistant' ], true ) ) {
				continue;
			}
			$content = trim( (string) ( $msg['content'] ?? '' ) );
			if ( '' === $content ) {
				continue;
			}

			$normalized[] = [
				'role'    => $role,
				'content' => $content,
			];
		}

		return $normalized;
	}

	/**
	 * Constructs the Anthropic Messages API request payload array.
	 *
	 * Maps system instructions to top-level 'system' parameter.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Input messages.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return array<string, mixed>
	 * @throws ProviderException If input messages or model are missing.
	 */
	public function build_payload( array $messages, array $options = [] ): array {
		$model = ! empty( $options['model'] )
			? sanitize_text_field( $options['model'] )
			: SettingsService::get_provider_model( 'claude' );

		if ( empty( $model ) ) {
			throw ProviderException::configuration_error(
				'claude',
				__( 'Anthropic Claude model is not configured.', 'gemini-chat-assistant' )
			);
		}

		$input_messages = $this->normalize_input_messages( $messages );
		if ( empty( $input_messages ) ) {
			throw ProviderException::invalid_request(
				'claude',
				__( 'Claude input messages array cannot be empty.', 'gemini-chat-assistant' )
			);
		}

		// Anthropic requires max_tokens on every Messages request.
		$max_tokens = self::DEFAULT_MAX_TOKENS;
		if ( isset( $options['max_tokens'] ) && (int) $options['max_tokens'] > 0 ) {
			$max_tokens = (int) $options['max_tokens'];
		} elseif ( isset( $options['max_output_tokens'] ) && (int) $options['max_output_tokens'] > 0 ) {
			$max_tokens = (int) $options['max_output_tokens'];
		}

		$payload = [
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $input_messages,
		];

		// System prompt maps to Anthropic top-level 'system' field.
		$system = '';
		if ( ! empty( $options['system'] ) ) {
			$system = trim( (string) $options['system'] );
		} elseif ( ! empty( $options['system_instruction'] ) ) {
			$system = trim( (string) $options['system_instruction'] );
		} elseif ( ! empty( $options['instructions'] ) ) {
			$system = trim( (string) $options['instructions'] );
		}

		if ( '' !== $system ) {
			$payload['system'] = $system;
		}

		if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
			$payload['temperature'] = (float) $options['temperature'];
		}

		return $payload;
	}

	/**
	 * Dispatches HTTP POST request to Anthropic Messages API.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 * @param string               $api_key Plaintext API key.
	 * @param array<string, mixed> $options Request options.
	 * @return array{body: string, status_code: int, request_id: ?string}
	 * @throws ProviderException On network or timeout error.
	 */
	protected function execute_http_request( array $payload, string $api_key, array $options = [] ): array {
		$body_json = wp_json_encode( $payload );
		if ( false === $body_json ) {
			throw ProviderException::invalid_request( 'claude', 'Failed to JSON-encode Anthropic request payload.' );
		}

		$timeout = isset( $options['timeout'] ) && (int) $options['timeout'] > 0
			? (int) $options['timeout']
			: self::DEFAULT_TIMEOUT;

		$headers = [
			'x-api-key'         => $api_key,
			'anthropic-version' => self::ANTHROPIC_VERSION,
			'Content-Type'      => 'application/json',
			'Accept'            => 'application/json',
		];

		$args = [
			'method'      => 'POST',
			'timeout'     => $timeout,
			'redirection' => 2,
			'httpversion' => '1.1',
			'headers'     => $headers,
			'body'        => $body_json,
			'data_format' => 'body',
			'sslverify'   => true,
		];

		$response = wp_remote_post( self::MESSAGES_ENDPOINT, $args );

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( false !== stripos( $msg, 'timed out' ) || false !== stripos( $msg, 'timeout' ) ) {
				throw ProviderException::timeout( 'claude', $msg );
			}
			throw ProviderException::provider_unavailable( 'claude', $msg );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );

		$req_id_raw = wp_remote_retrieve_header( $response, 'request-id' );
		if ( empty( $req_id_raw ) ) {
			$req_id_raw = wp_remote_retrieve_header( $response, 'x-request-id' );
		}

		if ( is_array( $req_id_raw ) ) {
			$req_id_raw = reset( $req_id_raw );
		}
		$request_id = is_string( $req_id_raw ) ? sanitize_text_field( $req_id_raw ) : null;

		return [
			'body'        => $body,
			'status_code' => $status_code,
			'request_id'  => $request_id,
		];
	}

	/**
	 * Parses and normalizes the raw HTTP response from Anthropic Messages API.
	 *
	 * @param string      $raw_body          Raw HTTP response body.
	 * @param int         $status_code       HTTP status code.
	 * @param string|null $header_request_id Optional request identifier from headers.
	 * @param string      $requested_model   Model requested.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function parse_response_body(
		string $raw_body,
		int $status_code,
		?string $header_request_id = null,
		string $requested_model = 'claude-3-5-haiku-20241022'
	): ProviderResponse {
		$json = json_decode( $raw_body, true );

		if ( ! is_array( $json ) ) {
			throw ProviderException::invalid_response(
				'claude',
				__( 'Invalid JSON response received from Anthropic API.', 'gemini-chat-assistant' )
			);
		}

		// Handle HTTP error statuses.
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = '';
			if ( isset( $json['error'] ) && is_array( $json['error'] ) ) {
				$error_msg = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : '';
			} elseif ( isset( $json['message'] ) ) {
				$error_msg = (string) $json['message'];
			}
			if ( empty( $error_msg ) ) {
				$error_msg = sprintf( 'Anthropic API error (HTTP %d)', $status_code );
			}
			$error_msg = ProviderException::strip_credentials( $error_msg );

			switch ( $status_code ) {
				case 400:
					throw ProviderException::invalid_request( 'claude', $error_msg );
				case 401:
					throw ProviderException::authentication_failed( 'claude', $error_msg );
				case 403:
					throw ProviderException::authentication_failed( 'claude', $error_msg );
				case 404:
					throw ProviderException::model_unavailable( 'claude', $error_msg );
				case 408:
					throw ProviderException::timeout( 'claude', $error_msg );
				case 413:
					throw ProviderException::invalid_request( 'claude', $error_msg );
				case 429:
					throw ProviderException::rate_limited( 'claude', $error_msg );
				case 500:
				case 502:
				case 503:
				case 504:
				case 529:
					throw ProviderException::provider_unavailable( 'claude', $error_msg );
				default:
					throw new ProviderException( 'claude', ProviderException::TYPE_GENERIC, '', $status_code, $error_msg );
			}
		}

		// Parse all content blocks of type = "text".
		$content_blocks = isset( $json['content'] ) && is_array( $json['content'] ) ? $json['content'] : [];
		$text_parts     = [];

		foreach ( $content_blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$type = $block['type'] ?? '';
			if ( 'text' === $type && isset( $block['text'] ) ) {
				$text_parts[] = (string) $block['text'];
			}
		}

		$assistant_text = trim( implode( '', $text_parts ) );
		if ( '' === $assistant_text ) {
			throw ProviderException::invalid_response(
				'claude',
				__( 'No text content blocks found in Claude response.', 'gemini-chat-assistant' )
			);
		}

		// Extract usage tokens.
		$usage         = isset( $json['usage'] ) && is_array( $json['usage'] ) ? $json['usage'] : [];
		$input_tokens  = absint( $usage['input_tokens'] ?? 0 );
		$output_tokens = absint( $usage['output_tokens'] ?? 0 );
		$total_tokens  = absint( $input_tokens + $output_tokens );

		// Stop reason normalization.
		$raw_stop_reason = isset( $json['stop_reason'] ) ? (string) $json['stop_reason'] : 'end_turn';
		$normalized_stop = match ( $raw_stop_reason ) {
			'end_turn', 'stop_sequence' => 'stop',
			'max_tokens'                => 'length',
			'tool_use'                  => 'tool_calls',
			default                     => sanitize_key( $raw_stop_reason ) ?: 'stop',
		};

		// Capture request ID.
		$response_id = ! empty( $json['id'] ) ? sanitize_text_field( (string) $json['id'] ) : '';
		$request_id  = ! empty( $header_request_id ) ? $header_request_id : $response_id;
		$model       = ! empty( $json['model'] ) ? sanitize_text_field( (string) $json['model'] ) : $requested_model;

		return new ProviderResponse(
			$assistant_text,
			'claude',
			$model,
			$input_tokens,
			$output_tokens,
			$total_tokens,
			$normalized_stop,
			$request_id ?: null,
			[
				'id'          => $response_id,
				'stop_reason' => $raw_stop_reason,
				'request_id'  => $request_id,
			]
		);
	}
}
