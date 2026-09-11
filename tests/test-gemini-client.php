<?php
/**
 * Standalone Test Suite for Node N4: Gemini Client.
 *
 * @package SkyFish\GeminiChat\Tests
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'GCA_TESTING_NO_SLEEP' ) ) {
	define( 'GCA_TESTING_NO_SLEEP', true );
}

// Define ABSPATH if running in standalone test mode.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_BASENAME' ) ) {
	define( 'GCA_PLUGIN_BASENAME', 'gemini-chat-assistant/gemini-chat-assistant.php' );
}

// Mock WordPress functions if not available.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

// Mock HTTP functions.
global $mock_http_response;
$mock_http_response = null;

global $last_http_request;
$last_http_request = null;

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = [] ) {
		global $mock_http_response, $last_http_request;
		$last_http_request = [
			'url'  => $url,
			'args' => $args,
		];
		$GLOBALS['retry_requests'][] = $last_http_request;
		if ( isset( $GLOBALS['retry_on_post'] ) ) { ( $GLOBALS['retry_on_post'] )(); }
		if ( ! empty( $GLOBALS['retry_responses'] ) ) {
			return array_shift( $GLOBALS['retry_responses'] );
		}
		return $mock_http_response;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = [] ) {
		global $mock_http_response, $last_http_request;
		if ( isset( $GLOBALS['node24_models_response'] ) && str_contains( $url, '/models' ) ) {
			return $GLOBALS['node24_models_response'];
		}
		$last_http_request = [
			'url'  => $url,
			'args' => $args,
		];
		if ( strpos( $url, 'https://generativelanguage.googleapis.com/' ) === 0 && false === strpos( $url, 'models' ) ) {
			if ( is_wp_error( $mock_http_response ) ) {
				return $mock_http_response;
			}
			return [ 'response' => [ 'code' => 200 ], 'body' => '' ];
		}
		return $mock_http_response;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}
		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return $response['body'];
		}
		return '';
	}
}

// Load GeminiClient.
require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/class-gemini-client.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\GeminiClient;

class GeminiClientTest {

	private int $passed = 0;
	private int $failed = 0;

	private function assert( bool $condition, string $test_name ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$test_name}\n";
		} else {
			$this->failed++;
			echo "[FAIL] {$test_name}\n";
		}
	}

	public function run_all(): bool {
		echo "========================================\n";
		echo "Running Node N4 GeminiClient Tests\n";
		echo "========================================\n\n";

		$this->test_endpoint_and_defaults();
		$this->test_api_key_resolution();
		$this->test_input_validation();
		$this->test_missing_key_error();
		$this->test_successful_interaction();
		$this->test_steps_text_extraction();
		$this->test_usage_parsing();
		$this->test_http_error_mapping();
		$this->test_transport_timeout_error();
		$this->test_empty_response_handling();
		$this->test_connection_method();
		$this->test_generation_diagnostics();
		$this->test_transient_recovery();
		$this->test_503_retry_and_fallback_resilience();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_transient_recovery(): void {
		putenv( 'GEMINI_API_KEY=retry_test_key' );
		$client = new GeminiClient();
		$ok = [ 'response' => [ 'code' => 200 ], 'body' => '{"candidates":[{"content":{"parts":[{"text":"OK"}]}}]}' ];
		foreach ( [ 'cURL error 6: Could not resolve host' => 'DNS', 'cURL error 60: SSL certificate problem' => 'TLS', 'cURL error 28: timed out retry_test_key' => 'NETWORK' ] as $message => $layer ) {
			$GLOBALS['node24_models_response'] = new WP_Error( 'http_request_failed', $message );
			$GLOBALS['retry_requests'] = [];
			$diag = $client->run_connection_diagnostic( 'gemini-3.8-flash' );
			$this->assert( $layer === $diag['failure_layer'] && 'Not Tested' === $diag['authentication'] && [] === $GLOBALS['retry_requests'], 'Models transport failure stops generation: ' . $layer );
			$this->assert( is_numeric( $diag['models_elapsed_seconds'] ) && null === $diag['models_http_status'] && ! str_contains( json_encode( $diag ), 'retry_test_key' ), 'Models timing and redacted transport details recorded' );
		}
		foreach ( [ 401 => 'AUTH', 403 => 'AUTH', 429 => 'QUOTA', 503 => 'GOOGLE SERVER' ] as $status => $layer ) {
			$GLOBALS['node24_models_response'] = [ 'response' => [ 'code' => $status ], 'body' => '{}' ];
			$GLOBALS['retry_requests'] = [];
			$diag = $client->run_connection_diagnostic( 'gemini-3.8-flash' );
			$this->assert( $layer === $diag['failure_layer'] && $status === $diag['models_http_status'] && [] === $GLOBALS['retry_requests'], 'Models HTTP failure classified without generation: ' . $status );
		}
		$GLOBALS['node24_models_response'] = [ 'response' => [ 'code' => 200 ], 'body' => '{"models":[]}' ];
		$GLOBALS['retry_responses'] = [ $ok ];
		$diag = $client->run_connection_diagnostic( 'gemini-3.8-flash' );
		$request = $GLOBALS['last_http_request'];
		$payload = json_decode( $request['args']['body'], true );
		$this->assert( $diag['success'] && $request['args']['timeout'] <= 45 && $request['args']['timeout'] > 44 && 'low' === $payload['generationConfig']['thinkingConfig']['thinkingLevel'], 'Explicit generation uses 45 seconds and low thinking' );
		$this->assert( is_numeric( $diag['generation_elapsed_seconds'] ) && 'NONE' === $diag['failure_layer'], 'Separate successful generation timing recorded' );
		$GLOBALS['retry_responses'] = [ $ok ];
		$client->create_interaction( 'hi', null, [ 'model' => 'gemini-3.8-flash' ] );
		$normal_request = $GLOBALS['last_http_request'];
		$normal_payload = json_decode( $normal_request['args']['body'], true );
		$this->assert( $normal_request['args']['timeout'] <= 45 && $normal_request['args']['timeout'] > 44 && 'low' === $normal_payload['generationConfig']['thinkingConfig']['thinkingLevel'], 'Normal generation defaults to 45 seconds and low thinking too' );
		$GLOBALS['retry_requests'] = [];
		$GLOBALS['retry_responses'] = [ new WP_Error( 'http_request_failed', 'cURL error 28: timed out' ), $ok ];
		$result = $client->create_interaction( 'hi' );
		$this->assert( 'GCA_GEMINI_TIMEOUT' === $result->get_error_code() && 504 === $result->get_error_data()['status'] && 1 === count( $GLOBALS['retry_requests'] ), 'Timeout is 504 and is never automatically resent' );
		unset( $GLOBALS['retry_responses'], $GLOBALS['retry_requests'], $GLOBALS['node24_models_response'] );
		putenv( 'GEMINI_API_KEY' );
	}

	private function test_endpoint_and_defaults(): void {
		$this->assert(
			'https://generativelanguage.googleapis.com/v1beta/models' === GeminiClient::API_BASE,
			'Gemini v1beta models base endpoint constant is configured'
		);
		$this->assert(
			'gemini-2.5-flash' === GeminiClient::DEFAULT_MODEL,
			'Default model is gemini-2.5-flash'
		);
	}

	private function test_api_key_resolution(): void {
		$client = new GeminiClient();

		// Case 1: Unset
		putenv( 'GEMINI_API_KEY' );
		unset( $_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY'] );
		$this->assert( ! $client->is_configured(), 'Client is not configured when no key is set' );

		// Case 2: Environment variable
		putenv( 'GEMINI_API_KEY=test_env_key_12345' );
		$this->assert( $client->is_configured(), 'Client detects key from GEMINI_API_KEY environment variable' );

		// Clean up
		putenv( 'GEMINI_API_KEY' );
	}

	private function test_input_validation(): void {
		$client = new GeminiClient();
		putenv( 'GEMINI_API_KEY=test_key' );

		$result = $client->create_interaction( '   ' );
		$this->assert( is_wp_error( $result ), 'Empty input returns WP_Error' );
		$this->assert(
			'GCA_GEMINI_INVALID_REQUEST' === $result->get_error_code(),
			'Empty input returns GCA_GEMINI_INVALID_REQUEST code'
		);

		putenv( 'GEMINI_API_KEY' );
	}

	private function test_missing_key_error(): void {
		$client = new GeminiClient();
		putenv( 'GEMINI_API_KEY' );
		unset( $_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY'] );

		$result = $client->create_interaction( 'Hello Gemini' );
		$this->assert( is_wp_error( $result ), 'Unconfigured client returns WP_Error on create_interaction' );
		$this->assert(
			'GCA_GEMINI_NOT_CONFIGURED' === $result->get_error_code(),
			'Unconfigured client returns GCA_GEMINI_NOT_CONFIGURED error code'
		);
	}

	private function test_successful_interaction(): void {
		global $mock_http_response, $last_http_request;

		putenv( 'GEMINI_API_KEY=test_secret_key_abc' );
		$client = new GeminiClient();

		$mock_body = json_encode( [
			'id'    => 'inter_1234567890',
			'model' => 'gemini-2.5-flash',
			'steps' => [
				[
					'type'    => 'model_output',
					'content' => [
						[
							'type' => 'text',
							'text' => 'Hello! How can I assist you with your WordPress site today?',
						],
					],
				],
			],
			'usage' => [
				'total_input_tokens'   => 15,
				'total_output_tokens'  => 12,
				'total_thought_tokens' => 0,
				'total_tokens'         => 27,
			],
		] );

		$mock_http_response = [
			'response' => [ 'code' => 200 ],
			'body'     => $mock_body,
		];

		$response = $client->create_interaction(
			'Hello',
			'prev_inter_999',
			[
				'system_instruction' => 'You are a helpful assistant.',
			]
		);

		$this->assert( ! is_wp_error( $response ), 'Successful request returns array' );
		$this->assert( 'inter_1234567890' === $response['interaction_id'], 'Interaction ID parsed correctly' );
		$this->assert( 'Hello! How can I assist you with your WordPress site today?' === $response['text'], 'Step text parsed correctly' );
		$this->assert( 27 === $response['usage']['total_tokens'], 'Usage tokens parsed correctly' );
		$this->assert( 'test_secret_key_abc' === $last_http_request['args']['headers']['x-goog-api-key'], 'API key sent in x-goog-api-key header' );

		$payload = json_decode( $last_http_request['args']['body'], true );
		$this->assert( str_ends_with( $last_http_request['url'], '/gemini-2.5-flash:generateContent' ), 'Endpoint uses the selected model without a key in the URL' );
		$this->assert( ! isset( $payload['previous_interaction_id'] ), 'generateContent excludes unsupported interaction ID' );
		$this->assert( isset( $payload['systemInstruction']['parts'][0]['text'] ) && 'You are a helpful assistant.' === $payload['systemInstruction']['parts'][0]['text'], 'Payload includes REST systemInstruction' );

		putenv( 'GEMINI_API_KEY' );
	}

	private function test_steps_text_extraction(): void {
		$client = new GeminiClient();

		$steps = [
			[
				'type'    => 'model_output',
				'content' => [
					[ 'type' => 'text', 'text' => 'First paragraph of response.' ],
					[ 'type' => 'text', 'text' => 'Second paragraph of response.' ],
				],
			],
		];

		$text = $client->extract_text_from_steps( $steps );
		$this->assert(
			"First paragraph of response.\n\nSecond paragraph of response." === $text,
			'extract_text_from_steps combines multiple text chunks'
		);
	}

	private function test_usage_parsing(): void {
		$client = new GeminiClient();

		$raw_usage = [
			'total_input_tokens'   => 45,
			'total_output_tokens'  => 30,
			'total_thought_tokens' => 10,
			'total_tokens'         => 85,
		];

		$usage = $client->parse_usage( $raw_usage );
		$this->assert( 45 === $usage['input_tokens'], 'Input tokens parsed' );
		$this->assert( 30 === $usage['output_tokens'], 'Output tokens parsed' );
		$this->assert( 10 === $usage['thought_tokens'], 'Thought tokens parsed' );
		$this->assert( 85 === $usage['total_tokens'], 'Total tokens parsed' );
	}

	private function test_http_error_mapping(): void {
		global $mock_http_response;
		putenv( 'GEMINI_API_KEY=test_key' );
		$client = new GeminiClient();

		// 401 Auth Error
		$mock_http_response = [
			'response' => [ 'code' => 401 ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'API key not valid' ] ] ),
		];
		$res = $client->create_interaction( 'test' );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_AUTH_ERROR' === $res->get_error_code(), '401 maps to GCA_GEMINI_AUTH_ERROR' );

		// 429 Quota Exceeded
		$mock_http_response = [
			'response' => [ 'code' => 429 ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Resource has exhausted quota' ] ] ),
		];
		$res = $client->create_interaction( 'test' );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_QUOTA_ERROR' === $res->get_error_code(), '429 quota maps to GCA_GEMINI_QUOTA_ERROR' );

		// 503 Unavailable
		$mock_http_response = [
			'response' => [ 'code' => 503 ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'The model is overloaded' ] ] ),
		];
		$res = $client->create_interaction( 'test' );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_UNAVAILABLE' === $res->get_error_code(), '503 maps to GCA_GEMINI_UNAVAILABLE' );

		putenv( 'GEMINI_API_KEY' );
	}

	private function test_transport_timeout_error(): void {
		global $mock_http_response;
		putenv( 'GEMINI_API_KEY=test_key' );
		$client = new GeminiClient();

		$mock_http_response = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 30000 milliseconds' );
		$res = $client->create_interaction( 'test' );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_TIMEOUT' === $res->get_error_code(), 'cURL error 28 maps to GCA_GEMINI_TIMEOUT' );

		putenv( 'GEMINI_API_KEY' );
	}

	private function test_empty_response_handling(): void {
		$client = new GeminiClient();

		$empty_body = [
			'id'    => 'inter_empty',
			'model' => 'gemini-3.8-flash',
			'steps' => [],
		];

		$res = $client->parse_response( $empty_body );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_EMPTY_RESPONSE' === $res->get_error_code(), 'Empty step array maps to GCA_GEMINI_EMPTY_RESPONSE' );
	}

	private function test_generation_diagnostics(): void {
		global $mock_http_response, $last_http_request;
		putenv( 'GEMINI_API_KEY=node24_secret_key' );
		$client = new GeminiClient();
		$GLOBALS['node24_models_response'] = [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'models' => [ [ 'name' => 'models/dynamic-test-model', 'supportedGenerationMethods' => [ 'generateContent' ] ] ] ] ) ];
		foreach ( [ 400 => 'INVALID_REQUEST', 401 => 'AUTH_ERROR', 403 => 'AUTH_ERROR', 404 => 'MODEL_UNAVAILABLE', 429 => 'RATE_LIMITED', 500 => 'UNAVAILABLE', 503 => 'UNAVAILABLE' ] as $status => $type ) {
			$mock_http_response = [ 'response' => [ 'code' => $status ], 'body' => json_encode( [ 'error' => [ 'code' => $status, 'status' => 'UPSTREAM_STATUS', 'message' => 'Upstream detail node24_secret_key' ] ] ) ];
			$diag = $client->run_connection_diagnostic( 'dynamic-test-model' );
			$this->assert( ! $diag['success'] && $diag['http_status'] === $status && $diag['error_type'] === $type, "Generation HTTP $status classified accurately" );
			$this->assert( 'Passed' === $diag['authentication'] && 'Yes' === $diag['model_available'], "Generation HTTP $status preserves discovery results" );
			$this->assert( 'UPSTREAM_STATUS' === $diag['google_error_status'] && (string) $status === $diag['google_error_code'] && str_contains( $diag['google_error_message'], '[REDACTED]' ) && ! str_contains( json_encode( $diag ), 'node24_secret_key' ), "Generation HTTP $status retains safe Google details" );
		}
		$mock_http_response = [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
		$diag = $client->run_connection_diagnostic( 'dynamic-test-model' );
		$this->assert( ! $diag['success'] && 200 === $diag['http_status'], 'HTTP 200 without generated text fails diagnostic' );
		$mock_http_response = [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'OK' ] ] ] ] ] ] ) ];
		$diag = $client->run_connection_diagnostic( 'dynamic-test-model' );
		$payload = json_decode( $last_http_request['args']['body'], true );
		$this->assert( $diag['success'] && str_ends_with( $diag['endpoint'], '/dynamic-test-model:generateContent' ) && 'Reply with OK.' === $payload['contents'][0]['parts'][0]['text'], 'Successful minimal generation uses exact dynamic model' );
		$this->assert( ! isset( $payload['generationConfig']['maxOutputTokens'] ), 'Diagnostic does not impose a one-token output limit' );
		$saved_settings = get_option( SettingsService::OPTION_KEY, [] );
		update_option( SettingsService::OPTION_KEY, [ 'provider_gemini_model' => 'dynamic-test-model', 'model' => 'legacy-model' ] );
		$this->assert( 'dynamic-test-model' === ( new GeminiClient() )->get_model(), 'New client reloads the saved provider model instead of the legacy setting' );
		$client->create_interaction( 'Hello', 'legacy-id', [ 'history' => [ [ 'role' => 'user', 'content' => 'Earlier question' ], [ 'role' => 'assistant', 'content' => 'Earlier answer' ] ] ] );
		$payload = json_decode( $last_http_request['args']['body'], true );
		$this->assert( 3 === count( $payload['contents'] ) && 'model' === $payload['contents'][1]['role'] && 'Hello' === $payload['contents'][2]['parts'][0]['text'], 'generateContent carries history and appends the current prompt exactly once' );
		update_option( SettingsService::OPTION_KEY, $saved_settings );
		$mock_http_response = [ 'response' => [ 'code' => 503 ], 'body' => json_encode( [ 'error' => [ 'code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'Overloaded node24_secret_key' ] ] ) ];
		$client->create_interaction( 'Hello', null, [ 'request_id' => 'chat-regression-id' ] );
		$chat_report = get_option( 'gca_gemini_last_chat' );
		$this->assert( 'Failed' === $chat_report['generation_result'] && 'chat-regression-id' === $chat_report['request_id'] && ! str_contains( json_encode( $chat_report ), 'node24_secret_key' ), 'Frontend failure retains its request ID and redacted upstream error' );
		$mock_http_response = [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'OK' ] ] ] ] ] ] ) ];
		$client->run_connection_diagnostic( 'dynamic-test-model' );
		$this->assert( $chat_report === get_option( 'gca_gemini_last_chat' ), 'Successful admin test cannot overwrite the last frontend failure' );
		$mock_http_response = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
		// Test transport directly because the mocked reachability probe also times out.
		$result = $client->create_interaction( 'Hello' );
		$this->assert( 'GCA_GEMINI_TIMEOUT' === $result->get_error_code() && null === get_option( 'gca_gemini_last_generation' )['http_status'], 'Timeout records no invented upstream HTTP status' );
		unset( $GLOBALS['node24_models_response'] );
		putenv( 'GEMINI_API_KEY' );
	}

	private function test_connection_method(): void {
		global $mock_http_response;
		putenv( 'GEMINI_API_KEY=test_conn_key' );
		$client = new GeminiClient();

		// Success response
		$mock_http_response = [
			'response' => [ 'code' => 200 ],
			'body'     => json_encode( [
				'candidates' => [
					[
						'content' => [
							'parts' => [ [ 'text' => 'pong' ] ],
						],
						'finishReason' => 'STOP',
					],
				],
			] ),
		];

		$res = $client->test_connection();
		$this->assert( true === $res, 'test_connection returns true on valid response' );

		// Error response (e.g. 401 Auth Error)
		$mock_http_response = [
			'response' => [ 'code' => 401 ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'API key invalid' ] ] ),
		];

		$err = $client->test_connection();
		$this->assert( is_wp_error( $err ) && 'GCA_GEMINI_AUTH_ERROR' === $err->get_error_code(), 'test_connection returns WP_Error on 401' );

		putenv( 'GEMINI_API_KEY' );
	}

	private function test_503_retry_and_fallback_resilience(): void {
		putenv( 'GEMINI_API_KEY=resilience_test_key' );
		$client   = new GeminiClient();
		$ok       = [ 'response' => [ 'code' => 200 ], 'body' => '{"candidates":[{"content":{"parts":[{"text":"Hello response"}]}}]}' ];
		$err_503  = [ 'response' => [ 'code' => 503 ], 'body' => '{"error":{"code":503,"status":"UNAVAILABLE","message":"The service is overloaded"}}' ];
		$err_429  = [ 'response' => [ 'code' => 429 ], 'body' => '{"error":{"code":429,"status":"RESOURCE_EXHAUSTED","message":"Rate limit exceeded"}}' ];
		$err_401  = [ 'response' => [ 'code' => 401 ], 'body' => '{"error":{"code":401,"status":"UNAUTHENTICATED","message":"Invalid key"}}' ];

		// 1. Persistent 503 without fallback -> 3 attempts on primary model, fails with GCA_GEMINI_UNAVAILABLE
		$saved_settings = get_option( SettingsService::OPTION_KEY, [] );
		update_option( SettingsService::OPTION_KEY, [
			'provider_gemini_model'            => 'gemini-3.8-flash',
			'provider_gemini_fallback_enabled' => false,
			'provider_gemini_fallback_model'   => 'gemini-3.7-flash',
		] );

		$GLOBALS['retry_requests']  = [];
		$GLOBALS['retry_responses'] = [ $err_503, $err_503, $err_503 ];
		$res = $client->create_interaction( 'test 503' );
		$this->assert( is_wp_error( $res ) && 'GCA_GEMINI_UNAVAILABLE' === $res->get_error_code(), 'Persistent 503 returns GCA_GEMINI_UNAVAILABLE' );
		$this->assert( 3 === count( $GLOBALS['retry_requests'] ), 'Persistent 503 retries up to 3 total attempts' );
		foreach ( $GLOBALS['retry_requests'] as $req ) {
			$this->assert( false !== strpos( $req['url'], 'gemini-3.8-flash' ), 'All 3 attempts use primary model when fallback disabled' );
		}
		$diag = get_option( 'gca_gemini_last_chat', [] );
		$this->assert( 3 === $diag['attempt_count'] && 'NO' === $diag['fallback_used'] && 'FAIL' === $diag['final_result'] && 'gemini-3.8-flash' === $diag['final_model'], 'Diagnostic records 3 attempts, no fallback, and FAIL' );

		// 2. 503 with fallback enabled -> attempt 1 & 2 on primary, attempt 3 on fallback (succeeds)
		update_option( SettingsService::OPTION_KEY, [
			'provider_gemini_model'            => 'gemini-3.8-flash',
			'provider_gemini_fallback_enabled' => true,
			'provider_gemini_fallback_model'   => 'gemini-3.7-flash',
		] );

		$GLOBALS['retry_requests']  = [];
		$GLOBALS['retry_responses'] = [ $err_503, $err_503, $ok ];
		$res2 = $client->create_interaction( 'test fallback' );
		$this->assert( ! is_wp_error( $res2 ) && 'Hello response' === $res2['text'], 'Fallback attempt succeeds with text' );
		$this->assert( 3 === count( $GLOBALS['retry_requests'] ), 'Fallback engages on attempt 3 after 2 primary failures' );
		$this->assert( false !== strpos( $GLOBALS['retry_requests'][0]['url'], 'gemini-3.8-flash' ), 'Attempt 1 uses primary model' );
		$this->assert( false !== strpos( $GLOBALS['retry_requests'][1]['url'], 'gemini-3.8-flash' ), 'Attempt 2 uses primary model' );
		$this->assert( false !== strpos( $GLOBALS['retry_requests'][2]['url'], 'gemini-3.7-flash' ), 'Attempt 3 uses fallback model' );
		$diag2 = get_option( 'gca_gemini_last_chat', [] );
		$this->assert( 'YES' === $diag2['fallback_used'] && 'PASS' === $diag2['final_result'] && 'gemini-3.7-flash' === $diag2['final_model'], 'Diagnostic records fallback used, final model fallback, and PASS' );

		// 3. 429 transient retry -> recovers on attempt 2
		$GLOBALS['retry_requests']  = [];
		$GLOBALS['retry_responses'] = [ $err_429, $ok ];
		$res3 = $client->create_interaction( 'test 429' );
		$this->assert( ! is_wp_error( $res3 ) && 2 === count( $GLOBALS['retry_requests'] ), '429 rate limit retries and recovers on attempt 2' );

		// 4. 401 unauthenticated -> NO retry, exactly 1 request
		$GLOBALS['retry_requests']  = [];
		$GLOBALS['retry_responses'] = [ $err_401, $ok ];
		$res4 = $client->create_interaction( 'test 401' );
		$this->assert( is_wp_error( $res4 ) && 'GCA_GEMINI_AUTH_ERROR' === $res4->get_error_code(), '401 returns GCA_GEMINI_AUTH_ERROR' );
		$this->assert( 1 === count( $GLOBALS['retry_requests'] ), '401 is NOT retried (exactly 1 request executed)' );

		// 5. cURL 28 timeout -> NO retry, exactly 1 request
		$GLOBALS['retry_requests']  = [];
		$GLOBALS['retry_responses'] = [ new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ), $ok ];
		$res5 = $client->create_interaction( 'test timeout' );
		$this->assert( is_wp_error( $res5 ) && 'GCA_GEMINI_TIMEOUT' === $res5->get_error_code() && 504 === $res5->get_error_data()['status'], 'Timeout returns GCA_GEMINI_TIMEOUT 504' );
		$this->assert( 1 === count( $GLOBALS['retry_requests'] ), 'Timeout is NOT automatically retried (exactly 1 request executed)' );

		// Restore settings
		update_option( SettingsService::OPTION_KEY, $saved_settings );
		unset( $GLOBALS['retry_requests'], $GLOBALS['retry_responses'] );
		putenv( 'GEMINI_API_KEY' );
	}
}

// Execute if run directly via PHP CLI.
if ( ! defined( 'GCA_GEMINI_TEST_HELPERS_ONLY' ) && ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) ) {
	$suite = new GeminiClientTest();
	$exit_code = $suite->run_all() ? 0 : 1;
	// Don't exit if included.
}
