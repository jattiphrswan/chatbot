<?php
/**
 * OpenAI HTTP Client for Responses API.
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
 * Class OpenAIClient
 *
 * Communicates with the OpenAI Responses API (POST https://api.openai.com/v1/responses)
 * using WordPress-native HTTP transport (wp_remote_post).
 */
class OpenAIClient {

	public const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';
	public const ENDPOINT           = self::RESPONSES_ENDPOINT;
	public const DEFAULT_TIMEOUT    = 30;

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
		return SettingsService::get_provider_api_key( 'openai' );
	}

	/**
	 * Creates a response interaction with the OpenAI Responses API.
	 *
	 * Supports both create_response($messages, $options) and legacy create_response($model, $messages, $options).
	 *
	 * @param array<int, array{role: string, content: string}>|string $messages_or_model Normalized message array or model slug.
	 * @param array<string, mixed>|array<int, array{role: string, content: string}> $options_or_messages Options array or message array.
	 * @param array<string, mixed> $options Optional options when model is first param.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function create_response( $messages_or_model, $options_or_messages = [], array $options = [] ): ProviderResponse {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			throw ProviderException::not_configured(
				'openai',
				__( 'OpenAI API key is missing or not configured on the server.', 'gemini-chat-assistant' )
			);
		}

		if ( is_string( $messages_or_model ) ) {
			$options['model'] = $messages_or_model;
			$messages         = is_array( $options_or_messages ) ? $options_or_messages : [];
		} else {
			$messages = is_array( $messages_or_model ) ? $messages_or_model : [];
			$options  = is_array( $options_or_messages ) ? $options_or_messages : [];
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
	 * Executes a minimal connection test to the OpenAI Responses API.
	 *
	 * Uses 1 output token to verify credentials and model availability
	 * at near-zero cost.
	 *
	 * @return bool
	 * @throws ProviderException
	 */
	public function test_connection( string $model = 'gpt-4o-mini' ): bool {
		$response = $this->create_response(
			[
				[
					'role'    => 'user',
					'content' => 'ping',
				],
			],
			[
				'model'             => $model,
				'max_output_tokens' => 1,
				'timeout'           => 15,
			]
		);

		return ! empty( $response->get_text() );
	}

	/**
	 * Normalizes conversation history into OpenAI Responses API input array.
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
	 * Constructs the OpenAI Responses API payload array.
	 *
	 * @param array<int, array{role: string, content: string}> $messages Input messages.
	 * @param array<string, mixed>                            $options  Request options.
	 * @return array<string, mixed>
	 * @throws ProviderException If input messages or model are missing.
	 */
	public function build_payload( array $messages, array $options = [] ): array {
		$model = ! empty( $options['model'] )
			? sanitize_text_field( $options['model'] )
			: SettingsService::get_provider_model( 'openai' );

		if ( empty( $model ) ) {
			throw ProviderException::configuration_error(
				'openai',
				__( 'OpenAI model is not configured.', 'gemini-chat-assistant' )
			);
		}

		$input = $this->normalize_input_messages( $messages );
		if ( empty( $input ) ) {
			throw ProviderException::invalid_request(
				'openai',
				__( 'OpenAI input messages array cannot be empty.', 'gemini-chat-assistant' )
			);
		}

		$payload = [
			'model' => $model,
			'input' => $input,
			'store' => false,
		];

		$instructions = '';
		if ( ! empty( $options['system_instruction'] ) ) {
			$instructions = trim( (string) $options['system_instruction'] );
		} elseif ( ! empty( $options['instructions'] ) ) {
			$instructions = trim( (string) $options['instructions'] );
		}
		if ( '' !== $instructions ) {
			$payload['instructions'] = $instructions;
		}

		if ( isset( $options['max_output_tokens'] ) && (int) $options['max_output_tokens'] > 0 ) {
			$payload['max_output_tokens'] = (int) $options['max_output_tokens'];
		}

		if ( isset( $options['temperature'] ) && is_numeric( $options['temperature'] ) ) {
			$payload['temperature'] = (float) $options['temperature'];
		}

		return $payload;
	}

	/**
	 * Dispatches HTTP POST request to OpenAI Responses API.
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
			throw ProviderException::invalid_request( 'openai', 'Failed to JSON-encode OpenAI request payload.' );
		}

		$timeout = isset( $options['timeout'] ) && (int) $options['timeout'] > 0
			? (int) $options['timeout']
			: self::DEFAULT_TIMEOUT;

		$headers = [
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
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

		$response = wp_remote_post( self::RESPONSES_ENDPOINT, $args );

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			throw new ProviderException( 'openai', 'connection_failed', $msg, 0 );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		$req_id_raw  = wp_remote_retrieve_header( $response, 'x-request-id' );

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
	 * Parses and normalizes the raw HTTP response from OpenAI Responses API.
	 *
	 * @param string      $raw_body          Raw HTTP response body.
	 * @param int         $status_code       HTTP status code.
	 * @param string|null $header_request_id Optional x-request-id header value.
	 * @param string      $requested_model   Model that was requested.
	 * @return ProviderResponse
	 * @throws ProviderException
	 */
	public function parse_response_body(
		string $raw_body,
		int $status_code,
		?string $header_request_id = null,
		string $requested_model = 'gpt-4o-mini'
	): ProviderResponse {
		$json = json_decode( $raw_body, true );

		if ( ! is_array( $json ) ) {
			throw ProviderException::invalid_response(
				'openai',
				__( 'Invalid JSON response received from OpenAI API.', 'gemini-chat-assistant' )
			);
		}

		// Handle HTTP error statuses
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_msg = '';
			if ( isset( $json['error'] ) && is_array( $json['error'] ) ) {
				$error_msg = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : '';
			}
			if ( empty( $error_msg ) ) {
				$error_msg = sprintf( 'OpenAI API error (HTTP %d)', $status_code );
			}
			$error_msg = ProviderException::strip_credentials( $error_msg );

			switch ( $status_code ) {
				case 400:
					throw ProviderException::invalid_request( 'openai', $error_msg );
				case 401:
				case 403:
					throw new ProviderException( 'openai', 'authentication_error', $error_msg, $status_code );
				case 404:
					throw ProviderException::model_unavailable( 'openai', $error_msg );
				case 408:
					throw ProviderException::timeout( 'openai', $error_msg );
				case 429:
					throw ProviderException::rate_limited( 'openai', $error_msg );
				case 500:
					throw new ProviderException( 'openai', 'server_error', $error_msg, 500 );
				case 502:
				case 503:
				case 504:
					throw ProviderException::provider_unavailable( 'openai', $error_msg );
				default:
					throw new ProviderException( 'openai', ProviderException::TYPE_GENERIC, $error_msg, $status_code );
			}
		}

		// Search output items for type = "message", role = "assistant"
		$output_items  = isset( $json['output'] ) && is_array( $json['output'] ) ? $json['output'] : [];
		$text_parts    = [];
		$found_message = false;

		foreach ( $output_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$type = $item['type'] ?? '';
			$role = $item['role'] ?? '';

			if ( 'message' === $type && 'assistant' === $role ) {
				$found_message = true;
				$content_items = isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : [];
				foreach ( $content_items as $content_part ) {
					if ( ! is_array( $content_part ) ) {
						continue;
					}
					if ( 'output_text' === ( $content_part['type'] ?? '' ) && isset( $content_part['text'] ) ) {
						$text_parts[] = (string) $content_part['text'];
					}
				}
			}
		}

		$assistant_text = trim( implode( '', $text_parts ) );
		if ( ! $found_message || '' === $assistant_text ) {
			throw ProviderException::invalid_response(
				'openai',
				__( 'No assistant output message found in OpenAI response.', 'gemini-chat-assistant' )
			);
		}

		// Extract usage tokens
		$usage         = isset( $json['usage'] ) && is_array( $json['usage'] ) ? $json['usage'] : [];
		$input_tokens  = absint( $usage['input_tokens'] ?? 0 );
		$output_tokens = absint( $usage['output_tokens'] ?? 0 );
		$total_tokens  = absint( $usage['total_tokens'] ?? ( $input_tokens + $output_tokens ) );

		// Capture request ID
		$response_id = ! empty( $json['id'] ) ? sanitize_text_field( (string) $json['id'] ) : '';
		$request_id  = ! empty( $header_request_id ) ? $header_request_id : $response_id;
		$model       = ! empty( $json['model'] ) ? sanitize_text_field( (string) $json['model'] ) : $requested_model;
		$status      = isset( $json['status'] ) ? sanitize_key( (string) $json['status'] ) : 'completed';

		return new ProviderResponse(
			$assistant_text,
			'openai',
			$model,
			$input_tokens,
			$output_tokens,
			$total_tokens,
			$status,
			$request_id ?: null,
			[
				'id'         => $response_id,
				'created_at' => absint( $json['created_at'] ?? 0 ),
				'request_id' => $request_id,
			]
		);
	}
}
