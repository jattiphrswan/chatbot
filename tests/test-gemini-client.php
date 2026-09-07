<?php
/**
 * Standalone Test Suite for Node N4: Gemini Client.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

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
use WP_Error;

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

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_endpoint_and_defaults(): void {
		$this->assert(
			'https://generativelanguage.googleapis.com/v1/interactions' === GeminiClient::API_ENDPOINT,
			'Interactions API v1 endpoint constant is configured'
		);
		$this->assert(
			'gemini-3.8-flash' === GeminiClient::DEFAULT_MODEL,
			'Default model is gemini-3.8-flash'
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
			'model' => 'gemini-3.8-flash',
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
		$this->assert( 'gemini-3.8-flash' === $payload['model'], 'Payload includes configured model' );
		$this->assert( 'prev_inter_999' === $payload['previous_interaction_id'], 'Payload includes previous_interaction_id' );
		$this->assert( 'You are a helpful assistant.' === $payload['system_instruction'], 'Payload includes system_instruction' );

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
}

// Execute if run directly via PHP CLI.
if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new GeminiClientTest();
	$exit_code = $suite->run_all() ? 0 : 1;
	// Don't exit if included.
}
