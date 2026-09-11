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

if ( ! class_exists( 'SkyFish\\GeminiChat\\Providers\\ModelRegistry' ) && file_exists( __DIR__ . '/Providers/ModelRegistry.php' ) ) {
	require_once __DIR__ . '/Providers/ModelRegistry.php';
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
	public const DEFAULT_MODEL = 'gemini-3.5-flash-lite';

	/**
	 * Default HTTP request timeout in seconds.
	 */
	public const DEFAULT_TIMEOUT = 45;

	/** Safe metadata only: never persist request headers, prompts or raw responses. */
	private array $last_request_diagnostic = [];

	/** Keep frontend evidence separate so a successful admin test cannot replace it. */
	private function record_generation_result( $result, array $options ): void {
		if ( ! isset( $this->last_request_diagnostic['failure_layer'] ) ) {
			$this->last_request_diagnostic['failure_layer'] = ! is_wp_error( $result ) ? 'NONE' : $this->http_failure_layer( (int) ( $this->last_request_diagnostic['http_status'] ?? 0 ) );
		}
		$this->last_request_diagnostic['checked_at'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$this->last_request_diagnostic['generation_result'] = is_wp_error( $result ) ? 'Failed' : 'Passed';
		$this->last_request_diagnostic['final_result'] = is_wp_error( $result ) ? 'FAIL' : 'PASS';
		$this->last_request_diagnostic['error_type'] = is_wp_error( $result ) ? $result->get_error_code() : '';
		$this->last_request_diagnostic['last_error'] = is_wp_error( $result ) ? $this->safe_error_text( $result->get_error_message() ) : '';
		$this->last_request_diagnostic['request_id'] = sanitize_text_field( (string) ( $options['request_id'] ?? '' ) );
		update_option( 'gca_gemini_last_generation', $this->last_request_diagnostic, false );
		if ( empty( $options['diagnostic_test'] ) ) {
			update_option( 'gca_gemini_last_chat', $this->last_request_diagnostic, false );
		}
	}

	/** A transport timeout alone cannot prove a firewall or slow generation. */
	private function transport_layer( WP_Error $error ): string {
		$message = $error->get_error_message();
		if ( preg_match( '/cURL error (5|6)\b|could not resolve|couldn.t resolve/i', $message ) ) {
			return 'DNS';
		}
		if ( preg_match( '/cURL error (35|51|58|60|77)\b|SSL|TLS|certificate/i', $message ) ) {
			return 'TLS';
		}
		return 'NETWORK';
	}

	/** Report timings through the existing admin diagnostic text, without UI changes. */
	private function summarize_diagnostic( array $report ): array {
		$report['last_error'] .= sprintf(
			' Models: %s s; HTTP: %s; WP_Error: %s %s. Generation: %s s; timeout: %s s; thinking: %s; failure layer: %s. %s',
			$report['models_elapsed_seconds'] ?? 'not tested',
			$report['models_http_status'] ?? 'not received',
			$report['models_wp_error_code'] ?? 'none',
			$report['models_wp_error_message'] ?? '',
			$report['generation_elapsed_seconds'] ?? 'not tested',
			$report['generation_timeout_seconds'] ?? 45,
			$report['thinking_level'] ?? 'not tested',
			$report['failure_layer'] ?? 'NOT TESTED',
			( $report['generation_wp_error_code'] ?? '' ) . ' ' . ( $report['generation_wp_error_message'] ?? '' )
		);
		return $report;
	}

	private function http_failure_layer( int $status ): string {
		return in_array( $status, [ 401, 403 ], true ) ? 'AUTH' : ( 429 === $status ? 'QUOTA' : ( 404 === $status ? 'MODEL' : 'GOOGLE SERVER' ) );
	}

	protected function request_now(): float { return microtime( true ); }
	protected function retry_sleep( float $seconds ): void {
		if ( defined( 'GCA_TESTING_NO_SLEEP' ) && GCA_TESTING_NO_SLEEP ) {
			return;
		}
		usleep( (int) round( $seconds * 1000000 ) );
	}

	private function safe_error_text( string $text ): string {
		$key = $this->get_api_key();
		if ( '' !== $key ) {
			$text = str_replace( [ $key, rawurlencode( $key ) ], '[REDACTED]', $text );
		}
		$text = preg_replace( '/AIza[0-9A-Za-z_-]+|([?&]key=)[^&\s]+/i', '[REDACTED]', $text );
		return sanitize_text_field( (string) $text );
	}

	private function safe_response_details( int $status, string $body, string $model, string $endpoint ): array {
		$data       = json_decode( $body, true );
		$err_code   = $this->safe_error_text( (string) ( $data['error']['code'] ?? '' ) );
		$err_status = $this->safe_error_text( (string) ( $data['error']['status'] ?? '' ) );
		$err_msg    = $this->safe_error_text( (string) ( $data['error']['message'] ?? '' ) );
		return [
			'http_status'              => $status ?: null,
			'google_http_status'       => $status ?: null,
			'google_error_code'        => $err_code,
			'google_error_status'      => $err_status,
			'google_error_message'     => $err_msg,
			'google_api_error_code'    => $err_code,
			'google_api_error_status'  => $err_status,
			'google_api_error_message' => $err_msg,
			'selected_model'           => $model,
			'endpoint'                 => $endpoint,
		];
	}

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

		$model    = SettingsService::get_provider_model( 'gemini' );

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
	 * Sends 'Reply only with OK' through the frontend generation path and requires text.
	 *
	 * @param string|null $override_model Optional model override to test.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function test_connection( ?string $override_model = null ) {
		$diag = $this->run_connection_diagnostic( $override_model );
		if ( ! empty( $diag['success'] ) ) {
			return true;
		}

		$code = ! empty( $diag['error_type'] ) ? 'GCA_GEMINI_' . $diag['error_type'] : 'GCA_GEMINI_TEST_FAILED';
		return new WP_Error( $code, $diag['last_error'] ?? __( 'Connection test failed.', 'gemini-chat-assistant' ), [ 'status' => $diag['http_status'] ?? 502 ] );
	}

	/**
	 * Runs a 3-step diagnostic connection test against Google Gemini API.
	 *
	 * STEP 1: Can WordPress reach generativelanguage.googleapis.com?
	 * STEP 2: Can WordPress call GET /v1beta/models with the configured API key?
	 * STEP 3: Can it make a minimal generation request with the selected model?
	 *
	 * @param string|null $override_model Optional model override.
	 * @return array<string, mixed> Diagnostic report.
	 */
	public function run_connection_diagnostic( ?string $override_model = null ): array {
		$api_key = $this->get_api_key();
		$source  = SettingsService::get( 'provider_gemini_credential_source', 'dashboard' );
		$source_label = ( 'server' === $source )
			? __( 'Server Configuration', 'gemini-chat-assistant' )
			: __( 'WordPress Dashboard', 'gemini-chat-assistant' );

		$model = $this->get_model( $override_model );

		$report = [
			'success'             => false,
			'api_key_configured'  => ! empty( $api_key ),
			'credential_source'   => $source_label,
			'api_reachable'       => 'Not Tested',
			'authentication'      => 'Not Tested',
			'selected_model'      => $model,
			'model_available'     => 'Not Tested',
			'generation_test'     => 'Not Tested',
			'error_type'          => '',
			'last_error'          => '',
			'models_elapsed_seconds' => null,
			'generation_elapsed_seconds' => null,
			'failure_layer' => 'NOT TESTED',
			'models_timeout_seconds' => 20,
			'generation_timeout_seconds' => 45,
		];

		if ( empty( $api_key ) ) {
			$report['error_type'] = 'NOT_CONFIGURED';
			$report['last_error'] = ( 'server' === $source )
				? __( 'Server API key is not configured.', 'gemini-chat-assistant' )
				: __( 'No API key configured. Enter your Google Gemini API key above and click Save All Settings.', 'gemini-chat-assistant' );
			$report = $this->summarize_diagnostic( $report );
		update_option( 'gca_gemini_diagnostics', $report, false );
			return $report;
		}

		$models_started = microtime( true );
		// STEP 2: Call GET /v1beta/models with configured API key.
		$models_url = self::API_BASE;
		$step2      = wp_remote_get( $models_url, [
			'timeout'   => 20,
			'sslverify' => true,
			'headers'   => [
				'x-goog-api-key' => $api_key,
			],
		] );

		$report['models_elapsed_seconds'] = round( microtime( true ) - $models_started, 3 );
		$report['models_http_status'] = is_wp_error( $step2 ) ? null : (int) wp_remote_retrieve_response_code( $step2 );
		$report['models_wp_error_code'] = is_wp_error( $step2 ) ? $this->safe_error_text( $step2->get_error_code() ) : '';
		$report['models_wp_error_message'] = is_wp_error( $step2 ) ? $this->safe_error_text( $step2->get_error_message() ) : '';
		$report['api_reachable'] = is_wp_error( $step2 ) ? 'No' : 'Yes';
		if ( is_wp_error( $step2 ) ) {
			$report['failure_layer'] = $this->transport_layer( $step2 );
			$report['error_type'] = 'NETWORK';
			$report['last_error'] = 'Hosting / network connectivity failure: ' . $report['models_wp_error_message'];
			$report = $this->summarize_diagnostic( $report );
			update_option( 'gca_gemini_diagnostics', $report, false );
			return $report;
		}

		$code2 = (int) wp_remote_retrieve_response_code( $step2 );
		$body2 = wp_remote_retrieve_body( $step2 );

		if ( $code2 < 200 || $code2 >= 300 ) {
			$error = $this->handle_http_error( $code2, $body2 );
			$report = array_merge( $report, $this->safe_response_details( $code2, $body2, $model, $models_url ) );
			$report['authentication'] = in_array( $code2, [ 401, 403 ], true ) ? 'Failed' : 'Not Tested';
			$report['error_type'] = str_replace( 'GCA_GEMINI_', '', $error->get_error_code() );
			$report['last_error'] = $error->get_error_message();
			$report['failure_layer'] = $this->http_failure_layer( $code2 );
			$report = $this->summarize_diagnostic( $report );
			update_option( 'gca_gemini_diagnostics', $report, false );
			return $report;
		}

		$report['authentication'] = 'Passed';

		// Parse models to check model availability & update dynamic cache.
		$discovered_models = [];
		$model_found       = false;
		$json2             = json_decode( $body2, true );
		if ( is_array( $json2 ) && isset( $json2['models'] ) && is_array( $json2['models'] ) ) {
			foreach ( $json2['models'] as $m_item ) {
				$m_id    = str_replace( 'models/', '', (string) ( $m_item['name'] ?? '' ) );
				$methods = $m_item['supportedGenerationMethods'] ?? [];
				if ( is_array( $methods ) && in_array( 'generateContent', $methods, true ) ) {
					$discovered_models[] = [
						'id'                => $m_id,
						'name'              => $m_item['displayName'] ?? $m_id,
						'provider'          => 'gemini',
						'context_window'    => absint( $m_item['inputTokenLimit'] ?? 1048576 ),
						'max_output_tokens' => absint( $m_item['outputTokenLimit'] ?? 8192 ),
						'description'       => (string) ( $m_item['description'] ?? '' ),
						'recommended'       => ( 'gemini-3.5-flash-lite' === $m_id ),
					];
					if ( $m_id === $model ) {
						$model_found = true;
					}
				}
			}
			if ( ! empty( $discovered_models ) ) {
				update_option( 'gca_discovered_models_gemini', $discovered_models, false );
			}
		}

		$report['model_available'] = $model_found ? 'Yes' : ( empty( $discovered_models ) ? 'Not Tested' : 'No' );

		// Use the same transport, payload builder and response validation as frontend chat.
		$result = $this->create_interaction( 'Reply with OK.', null, [
			'model' => $model,
			'system_instruction' => '',
			'diagnostic_test' => true,
			'timeout' => 45,
		] );
		$report = array_merge( $report, $this->last_request_diagnostic );
		$report['generation_test'] = is_wp_error( $result ) ? 'Failed' : 'Passed';
		$report['success'] = ! is_wp_error( $result );
		$report['error_type'] = is_wp_error( $result ) ? str_replace( 'GCA_GEMINI_', '', $result->get_error_code() ) : '';
		if ( isset( $report['connection_error'] ) && 'TIMEOUT' !== $report['error_type'] ) {
			$report['error_type'] = 'NETWORK';
		}
		$report['last_error'] = is_wp_error( $result ) ? $result->get_error_message() : __( 'Connection verified successfully.', 'gemini-chat-assistant' );
		$report = $this->summarize_diagnostic( $report );
			update_option( 'gca_gemini_diagnostics', $report, false );

		return $report;
	}

	/**
	 * Dynamically discovers models supporting generateContent from Google API.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function fetch_available_models() {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'GCA_GEMINI_NOT_CONFIGURED', __( 'API key is not configured.', 'gemini-chat-assistant' ) );
		}

		$url = self::API_BASE;
		$res = wp_remote_get( $url, [
			'timeout'   => 15,
			'sslverify' => true,
			'headers'   => [
				'x-goog-api-key' => $api_key,
			],
		] );

		if ( is_wp_error( $res ) ) {
			return $this->handle_transport_error( $res );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );

		if ( $code < 200 || $code >= 300 ) {
			return $this->handle_http_error( $code, $body );
		}

		$json = json_decode( $body, true );
		if ( ! is_array( $json ) || ! isset( $json['models'] ) || ! is_array( $json['models'] ) ) {
			return new WP_Error( 'GCA_GEMINI_INVALID_RESPONSE', __( 'Invalid models response from Google API.', 'gemini-chat-assistant' ) );
		}

		$discovered = [];
		foreach ( $json['models'] as $m_item ) {
			$m_id    = str_replace( 'models/', '', (string) ( $m_item['name'] ?? '' ) );
			$methods = $m_item['supportedGenerationMethods'] ?? [];
			if ( is_array( $methods ) && in_array( 'generateContent', $methods, true ) ) {
				$discovered[] = [
					'id'                => $m_id,
					'name'              => $m_item['displayName'] ?? $m_id,
					'provider'          => 'gemini',
					'context_window'    => absint( $m_item['inputTokenLimit'] ?? 1048576 ),
					'max_output_tokens' => absint( $m_item['outputTokenLimit'] ?? 8192 ),
					'description'       => (string) ( $m_item['description'] ?? '' ),
					'recommended'       => ( 'gemini-3.5-flash-lite' === $m_id ),
				];
			}
		}

		if ( ! empty( $discovered ) ) {
			update_option( 'gca_discovered_models_gemini', $discovered, false );
		}

		return $discovered;
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
		];

		if ( '' !== $system_instruction ) {
			$payload['systemInstruction'] = [
				'parts' => [ [ 'text' => $system_instruction ] ],
			];
		}

		// generateContent is stateless: conversation memory is sent through contents.

		// Allow optional generation options if provided.
		if ( isset( $options['generation_config'] ) && is_array( $options['generation_config'] ) ) {
			$payload['generationConfig'] = $options['generation_config'];
		}

		$thinking_level = (string) SettingsService::get( 'provider_gemini_thinking_level', 'low' );
		if ( ( str_contains( $model, 'flash' ) || in_array( $model, [ 'gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash-lite', 'gemini-2.5-flash', 'gemini-2.0-flash' ], true ) ) && ! isset( $payload['generationConfig']['thinkingConfig'] ) ) {
			$payload['generationConfig']['thinkingConfig'] = [ 'thinkingLevel' => $thinking_level ];
		}

		$max_output_tokens = absint( SettingsService::get( 'provider_gemini_max_tokens', 1000 ) );
		if ( $max_output_tokens <= 0 ) {
			$max_output_tokens = 1000;
		}
		if ( ! isset( $payload['generationConfig']['maxOutputTokens'] ) ) {
			$payload['generationConfig']['maxOutputTokens'] = $max_output_tokens;
		}

		$timeout = isset( $options['timeout'] ) && is_numeric( $options['timeout'] )
			? absint( $options['timeout'] )
			: self::DEFAULT_TIMEOUT;

		$timeout = max( 1, min( 45, $timeout ) );
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

		$req_id = sanitize_text_field( (string) ( $options['request_id'] ?? '' ) );
		if ( empty( $req_id ) ) {
			$req_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'gca_', true );
		}
		$options['request_id'] = $req_id;

		$this->last_request_diagnostic = $this->safe_response_details( 0, '', $model, $endpoint );
		if ( empty( $options['diagnostic_test'] ) ) {
			update_option( 'gca_gemini_last_chat', array_merge( $this->last_request_diagnostic, [
				'checked_at'                 => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'request_id'                 => $req_id,
				'generation_result'          => 'In progress',
				'generation_timeout_seconds' => $timeout,
				'thinking_level'             => $payload['generationConfig']['thinkingConfig']['thinkingLevel'] ?? 'default',
				'final_request_characters'   => mb_strlen( $request_args['body'], 'UTF-8' ),
			] ), false );
		}

		$generation_started = $this->request_now();
		// Overall request budget: approximately 40-45s lifecycle across all attempts and sleeps.
		$overall_budget = min( 45.0, max( 5.0, (float) ( $options['timeout'] ?? 45.0 ) ) );
		$deadline       = min( $generation_started + $overall_budget, (float) ( $options['deadline'] ?? ( $generation_started + $overall_budget ) ) );
		$primary_model    = $model;
		$fallback         = (string) SettingsService::get( 'provider_gemini_fallback_model', 'gemini-3.8-flash' );
		$fallback_enabled = empty( $options['diagnostic_test'] ) && (bool) SettingsService::get( 'provider_gemini_fallback_enabled', false )
			&& $fallback !== $primary_model && Providers\ModelRegistry::has_model( 'gemini', $fallback );
		$fallback_used    = false;
		$all_unavailable  = true;

		$attempts = [];
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$remaining_budget = $deadline - $this->request_now();
			if ( $remaining_budget < 1.0 ) {
				if ( ! isset( $response ) ) {
					$response = new WP_Error( 'gca_request_deadline', __( 'Chat request deadline exhausted before generation.', 'gemini-chat-assistant' ) );
				}
				break;
			}

			$attempt_timeout = min( (float) $timeout, $remaining_budget );
			$request_args['timeout'] = $attempt_timeout;
			$attempt_num = $attempt + 1;
			$request_args['headers']['X-GCA-Attempt']    = (string) $attempt_num;
			$request_args['headers']['X-GCA-Request-ID'] = $req_id;

			$endpoint = self::API_BASE . '/' . rawurlencode( $model ) . ':generateContent';
			$attempt_started = $this->request_now();
			$response = wp_remote_post( $endpoint, $request_args );
			$attempt_elapsed = $this->request_now() - $attempt_started;

			$is_transport = is_wp_error( $response );
			$status       = $is_transport ? null : (int) wp_remote_retrieve_response_code( $response );
			$resp_body    = $is_transport ? '' : (string) wp_remote_retrieve_body( $response );
			$decoded_json = ( ! $is_transport && '' !== $resp_body ) ? json_decode( $resp_body, true ) : null;

			$wp_err_code = $is_transport ? $this->safe_error_text( $response->get_error_code() ) : '';
			$wp_err_msg  = $is_transport ? $this->safe_error_text( $response->get_error_message() ) : '';
			$g_status    = ( is_array( $decoded_json ) && isset( $decoded_json['error']['status'] ) ) ? $this->safe_error_text( (string) $decoded_json['error']['status'] ) : '';
			$g_code      = ( is_array( $decoded_json ) && isset( $decoded_json['error']['code'] ) ) ? $this->safe_error_text( (string) $decoded_json['error']['code'] ) : '';
			$g_msg       = ( is_array( $decoded_json ) && isset( $decoded_json['error']['message'] ) ) ? $this->safe_error_text( (string) $decoded_json['error']['message'] ) : '';

			if ( $is_transport ) {
				$is_timeout = ( 'gca_request_deadline' === $response->get_error_code() || false !== stripos( $wp_err_msg, 'timed out' ) || false !== stripos( $wp_err_msg, 'cURL error 28' ) );
				$classification = $is_timeout ? 'TRANSPORT_TIMEOUT' : 'TRANSPORT_ERROR';
			} elseif ( $status >= 200 && $status < 300 ) {
				$classification = 'SUCCESS';
			} elseif ( 503 === $status ) {
				$classification = 'UPSTREAM_503';
			} elseif ( 429 === $status ) {
				$classification = ( false !== stripos( $g_msg, 'quota' ) ) ? 'UPSTREAM_429_QUOTA' : 'UPSTREAM_429_RATE_LIMIT';
			} elseif ( 401 === $status || 403 === $status ) {
				$classification = 'UPSTREAM_AUTH_ERROR';
			} elseif ( 404 === $status ) {
				$classification = 'UPSTREAM_MODEL_UNAVAILABLE';
			} elseif ( 504 === $status ) {
				$classification = 'UPSTREAM_504_GATEWAY_TIMEOUT';
			} else {
				$classification = 'UPSTREAM_HTTP_' . $status;
			}

			$attempts[] = [
				'attempt'                  => $attempt_num,
				'request_id'               => $req_id,
				'model'                    => $model,
				'http_status'              => $status,
				'google_http_status'       => $status,
				'elapsed_ms'               => round( $attempt_elapsed * 1000, 3 ),
				'elapsed_seconds'          => round( $attempt_elapsed, 2 ),
				'is_transport_error'       => $is_transport,
				'wp_error_code'            => $wp_err_code,
				'wp_error_message'         => $wp_err_msg,
				'google_api_error_status'  => $g_status,
				'google_api_error_code'    => $g_code,
				'google_api_error_message' => $g_msg,
				'classification'           => $classification,
			];

			// Never automatically retry transport failures (including timeouts).
			if ( $is_transport ) {
				break;
			}

			// Safe thinking fallback: If model rejects thinking configuration with 400 Bad Request,
			// safely omit thinkingConfig and retry immediately without failing the request.
			if ( 400 === $status && false !== stripos( $g_msg, 'thinking' ) && isset( $payload['generationConfig']['thinkingConfig'] ) ) {
				unset( $payload['generationConfig']['thinkingConfig'] );
				$request_args['body'] = (string) wp_json_encode( $payload );
				continue;
			}

			// Only retry transient 503 or 429. Never retry 400, 401, 403, 404, 500, etc.
			if ( ! in_array( $status, [ 503, 429 ], true ) || 2 === $attempt || $fallback_used ) {
				break;
			}

			// For Google 429 RESOURCE_EXHAUSTED: perform at most ONE bounded retry.
			if ( 429 === $status && $attempt >= 1 ) {
				break;
			}

			$all_unavailable = $all_unavailable && ( 503 === $status );

			// Bounded jittered delays:
			// Retry 1: about 750-1250 ms jittered delay (~1.0s to 1.2s)
			// Retry 2: about 1500-2500 ms jittered delay (~2.0s to 2.2s)
			if ( 0 === $attempt ) {
				$delay = 1.0 + ( random_int( 0, 200 ) / 1000 );
			} else {
				$delay = 2.0 + ( random_int( 0, 200 ) / 1000 );
			}

			// Respect Retry-After or body retryDelay for 429
			if ( 429 === $status ) {
				$parsed_delay = null;
				if ( function_exists( 'wp_remote_retrieve_header' ) ) {
					$retry_after = (string) wp_remote_retrieve_header( $response, 'retry-after' );
					if ( '' !== $retry_after ) {
						$parsed_delay = is_numeric( $retry_after ) ? (float) $retry_after : max( 0, ( strtotime( $retry_after ) ?: time() ) - time() );
					}
				}
				// Also inspect body for retryDelay in error.details
				if ( null === $parsed_delay && is_array( $decoded_json ) && ! empty( $decoded_json['error']['details'] ) ) {
					foreach ( $decoded_json['error']['details'] as $detail ) {
						if ( isset( $detail['retryDelay'] ) ) {
							$raw_rd       = (string) $detail['retryDelay'];
							$parsed_delay = (float) rtrim( $raw_rd, 's' );
							break;
						}
					}
				}
				if ( null !== $parsed_delay ) {
					// If Google explicitly supplies a delay > 2.5s, quota cannot be resolved inside current request -> do not retry.
					if ( $parsed_delay > 2.5 ) {
						break;
					}
					$delay = max( $delay, $parsed_delay );
				}
			}

			// Never shorten a server Retry-After or exceed the shared deadline to retry.
			if ( $delay > 3.0 || $this->request_now() + $delay + 1.0 >= $deadline ) {
				break;
			}

			$this->retry_sleep( $delay );

			// If fallback is enabled and persistent 503 occurred, switch to fallback on attempt 3
			if ( 1 === $attempt && $all_unavailable && $fallback_enabled ) {
				$model         = $fallback;
				$fallback_used = true;
				if ( ! isset( $options['generation_config']['thinkingConfig'] ) ) {
					unset( $payload['generationConfig']['thinkingConfig'] );
					if ( str_contains( $model, 'flash' ) || in_array( $model, [ 'gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash-lite' ], true ) ) {
						$payload['generationConfig']['thinkingConfig'] = [ 'thinkingLevel' => $thinking_level ];
					}
					if ( empty( $payload['generationConfig'] ) ) {
						unset( $payload['generationConfig'] );
					}
					$request_args['body'] = (string) wp_json_encode( $payload );
				}
			}
		}

		$this->last_request_diagnostic = $this->safe_response_details( 0, '', $model, $endpoint );

		$last_attempt          = ! empty( $attempts ) ? end( $attempts ) : null;
		$final_status          = $last_attempt ? $last_attempt['http_status'] : null;
		$final_classification  = $last_attempt ? $last_attempt['classification'] : 'UNKNOWN';
		$final_result          = ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) >= 200 && (int) wp_remote_retrieve_response_code( $response ) < 300 ) ? 'PASS' : 'FAIL';

		$formatted_attempts = [];
		foreach ( $attempts as $idx => $att ) {
			$num      = $idx + 1;
			$st       = null !== $att['http_status'] ? (string) $att['http_status'] : ( ! empty( $att['wp_error_code'] ) ? $att['wp_error_code'] : 'TIMEOUT' );
			$time_sec = number_format( (float) ( $att['elapsed_seconds'] ?? ( ( $att['elapsed_ms'] ?? 0 ) / 1000 ) ), 1 ) . 's';
			$formatted_attempts[] = "Attempt {$num}:\n{$att['model']}\n{$st}\n{$time_sec}";
		}
		$formatted_attempts[] = "Final:\n{$final_result}";
		$attempt_breakdown_str = implode( "\n\n", $formatted_attempts );

		$attempt_summary_items = [];
		foreach ( $attempts as $idx => $att ) {
			$num = $idx + 1;
			$st  = null !== $att['http_status'] ? (string) $att['http_status'] : ( ! empty( $att['wp_error_code'] ) ? $att['wp_error_code'] : 'TIMEOUT' );
			$attempt_summary_items[] = sprintf( 'Attempt %d: %s (%s)', $num, $att['model'], $st );
		}
		$attempt_summary_str = implode( '; ', $attempt_summary_items );

		$generation_metadata = [
			'attempt_count'                   => count( $attempts ),
			'provider_attempt_count'          => count( $attempts ),
			'retry_count'                     => max( 0, count( $attempts ) - 1 ),
			'provider_retry_count'            => max( 0, count( $attempts ) - 1 ),
			'primary_model'                   => $primary_model,
			'primary_attempts'                => count( array_filter( $attempts, static fn( $a ) => $a['model'] === $primary_model ) ),
			'attempts'                        => $attempts,
			'attempt_summary'                 => $attempt_summary_str,
			'attempts_breakdown'              => $attempt_breakdown_str,
			'fallback_used'                   => $fallback_used ? 'YES' : 'NO',
			'final_model'                     => $model,
			'final_result'                    => $final_result,
			'final_provider_status'           => null !== $final_status ? (string) $final_status : $final_classification,
			'provider_status'                 => null !== $final_status ? (string) $final_status : $final_classification,
			'provider_quota'                  => ( 429 === $final_status ) ? ( ! empty( $last_attempt['google_api_error_status'] ) ? $last_attempt['google_api_error_status'] : 'RESOURCE_EXHAUSTED' ) : 'Not applicable',
			'provider_retry_after'            => ( 429 === $final_status && isset( $parsed_delay ) && $parsed_delay > 0 ) ? (int) ceil( $parsed_delay ) : 0,
			'model'                           => $model,
			'total_gemini_time'               => round( $this->request_now() - $generation_started, 3 ),
			'total_provider_time'             => round( $this->request_now() - $generation_started, 3 ),
			'generation_elapsed_seconds'      => round( $this->request_now() - $generation_started, 3 ),
			'generation_timeout_seconds'      => $request_args['timeout'],
			'overall_budget_seconds'          => $overall_budget,
			'thinking_level'                  => $payload['generationConfig']['thinkingConfig']['thinkingLevel'] ?? 'default',
			'final_request_characters'        => mb_strlen( $request_args['body'], 'UTF-8' ),
			'system_prompt_characters'        => mb_strlen( $system_instruction, 'UTF-8' ),
			'google_http_status'              => $last_attempt['http_status'] ?? null,
			'is_transport_error'              => ! empty( $last_attempt['is_transport_error'] ) ? 'YES' : 'NO',
			'wp_error_code'                   => $last_attempt['wp_error_code'] ?? '',
			'wp_error_message'                => $last_attempt['wp_error_message'] ?? '',
			'google_api_error_status'         => $last_attempt['google_api_error_status'] ?? '',
			'google_api_error_code'           => $last_attempt['google_api_error_code'] ?? '',
			'google_api_error_message'        => $last_attempt['google_api_error_message'] ?? '',
			'provider_failure_classification' => $last_attempt['classification'] ?? '',
		];
		$this->last_request_diagnostic = array_merge( $this->last_request_diagnostic, $generation_metadata );

		if ( is_wp_error( $response ) ) {
			$error = $this->handle_transport_error( $response );
			$this->last_request_diagnostic['connection_error'] = $error->get_error_message();
			$this->last_request_diagnostic['generation_wp_error_code'] = $this->safe_error_text( $response->get_error_code() );
			$this->last_request_diagnostic['generation_wp_error_message'] = $this->safe_error_text( $response->get_error_message() );
			$this->last_request_diagnostic['failure_layer'] = $this->transport_layer( $response );
			$this->record_generation_result( $error, $options );
			return $error;
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$this->last_request_diagnostic = $this->safe_response_details( $status_code, $response_body, $model, $endpoint );
		$this->last_request_diagnostic = array_merge( $this->last_request_diagnostic, $generation_metadata );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$parse_started = microtime( true );
			$parsed = $this->parse_response( $response_body );
			$this->last_request_diagnostic['response_parsing_ms'] = round( ( microtime( true ) - $parse_started ) * 1000, 3 );
			if ( ! is_wp_error( $parsed ) ) {
				$parsed['model'] = $model;
			}
			$this->record_generation_result( $parsed, $options );
			return $parsed;
		}

		$error = $this->handle_http_error( $status_code, $response_body );
		$this->record_generation_result( $error, $options );
		return $error;
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
		if ( 'gca_request_deadline' === $error->get_error_code() || false !== stripos( $error_message, 'timed out' ) || false !== stripos( $error_message, 'cURL error 28' ) ) {
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
				$this->safe_error_text( $error_message )
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
			$error_message = $this->safe_error_text( (string) $decoded['error']['message'] );
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

			case 504:
				return new WP_Error( 'GCA_GEMINI_TIMEOUT', __( 'The request to Gemini API timed out.', 'gemini-chat-assistant' ), [ 'status' => 504 ] );
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

		if ( in_array( $status_code, [ 500, 503 ], true ) ) {
			$msg = __( 'Google Gemini is temporarily unavailable. Please try again shortly.', 'gemini-chat-assistant' ) . ( '' !== $error_message ? ' ' . $error_message : '' );
		}

		return new WP_Error(
			$code,
			$msg,
			[
				'status'      => $status_code,
				'api_message' => $error_message,
				'api_code' => $this->safe_error_text( (string) ( $decoded['error']['status'] ?? $decoded['error']['code'] ?? '' ) ),
			]
		);
	}
}
