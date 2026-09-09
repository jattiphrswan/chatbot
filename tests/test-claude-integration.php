<?php
/**
 * Test Suite: Node N20 - Live Anthropic Claude Integration.
 *
 * Verifies ClaudeClient endpoint, headers, payload construction, max_tokens,
 * system prompt mapping, multi-turn history normalization, output parsing,
 * multi-block text joining, token usage extraction, stop reason mapping,
 * error code mappings, secret scrubbing, test connection, rate limiting,
 * and Gemini/OpenAI regression prevention.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Providers\ProviderInterface;
use SkyFish\GeminiChat\Providers\ProviderRegistry;
use SkyFish\GeminiChat\Providers\ProviderException;
use SkyFish\GeminiChat\Providers\ProviderResponse;
use SkyFish\GeminiChat\Providers\ClaudeClient;
use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Providers\OpenAIClient;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mock ClaudeClient for testing request construction and simulated HTTP responses.
 */
class MockClaudeClient extends ClaudeClient {

	public array $last_payload = [];
	public string $last_api_key = '';
	public array $last_options = [];
	public $next_response = null;

	public function set_next_response( $response ): void {
		$this->next_response = $response;
	}

	protected function execute_http_request( array $payload, string $api_key, array $options = [] ): array {
		$this->last_payload = $payload;
		$this->last_api_key = $api_key;
		$this->last_options = $options;

		if ( null !== $this->next_response ) {
			return $this->next_response;
		}

		// Default successful mock Anthropic Messages response.
		return [
			'status_code' => 200,
			'request_id'  => 'msg_req_mock_claude_123',
			'body'        => json_encode( [
				'id'          => 'msg_mock_claude_123',
				'type'        => 'message',
				'role'        => 'assistant',
				'model'       => $payload['model'] ?? 'claude-3-5-haiku-20241022',
				'content'     => [
					[
						'type' => 'text',
						'text' => 'Hello from mocked Anthropic Messages API!',
					],
				],
				'stop_reason' => 'end_turn',
				'usage'       => [
					'input_tokens'  => 18,
					'output_tokens' => 9,
				],
			] ),
		];
	}
}

/**
 * Class TestClaudeIntegration
 */
class TestClaudeIntegration {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	/**
	 * Runs all test cases.
	 */
	public function run(): void {
		echo "Starting Node N20 Live Anthropic Claude Integration Test Suite...\n\n";

		$this->test_client_constants_and_endpoint();
		$this->test_request_headers_and_security();
		$this->test_payload_structure_max_tokens_and_system();
		$this->test_multi_turn_history_normalization();
		$this->test_response_parsing_and_token_usage();
		$this->test_multiple_text_blocks_handling();
		$this->test_error_mappings_and_status_codes();
		$this->test_secret_scrubbing_in_exceptions();
		$this->test_test_connection_minimal_query();
		$this->test_provider_validation_and_disabled_checks();
		$this->test_gemini_and_openai_regression();

		echo "\n--------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "--------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			echo "Failure details:\n";
			foreach ( $this->errors as $err ) {
				echo " - " . $err . "\n";
			}
		}
	}

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] {$message}\n";
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "[FAIL] {$message}\n";
		}
	}

	/**
	 * 1. Test ClaudeClient constants, endpoint, and version header.
	 */
	public function test_client_constants_and_endpoint(): void {
		echo "\n--- Section 1: Endpoint & Client Constants ---\n";

		$this->assert( class_exists( 'SkyFish\GeminiChat\Providers\ClaudeClient' ), '1.1 ClaudeClient class exists' );
		$this->assert( ClaudeClient::MESSAGES_ENDPOINT === 'https://api.anthropic.com/v1/messages', '1.2 ClaudeClient::MESSAGES_ENDPOINT is https://api.anthropic.com/v1/messages' );
		$this->assert( ClaudeClient::ENDPOINT === 'https://api.anthropic.com/v1/messages', '1.3 ClaudeClient::ENDPOINT is https://api.anthropic.com/v1/messages' );
		$this->assert( ClaudeClient::ANTHROPIC_VERSION === '2023-06-01', '1.4 ClaudeClient::ANTHROPIC_VERSION is 2023-06-01' );
		$this->assert( ClaudeClient::DEFAULT_MAX_TOKENS === 1024, '1.5 ClaudeClient::DEFAULT_MAX_TOKENS is 1024' );

		$client = new MockClaudeClient( 'sk-ant-test-key-12345' );
		$client->create_response( [ [ 'role' => 'user', 'content' => 'Hello' ] ], [ 'model' => 'claude-3-5-haiku-20241022' ] );

		$this->assert( ! empty( $client->last_payload ), '1.6 HTTP request dispatched with payload' );
		$this->assert( $client->last_payload['model'] === 'claude-3-5-haiku-20241022', '1.7 Model matched configured model' );
	}

	/**
	 * 2. Test request headers and credential isolation.
	 */
	public function test_request_headers_and_security(): void {
		echo "\n--- Section 2: Headers & Credential Security ---\n";

		$secret_key = 'sk-ant-api03-secret-key-abc987654321';
		$client     = new MockClaudeClient( $secret_key );
		$client->create_response( [ [ 'role' => 'user', 'content' => 'Hello' ] ], [ 'model' => 'claude-3-5-sonnet-20241022' ] );

		$this->assert( $client->last_api_key === $secret_key, '2.1 API key passed to execute_http_request' );

		// Verify that payload itself does NOT contain the raw API key.
		$payload_json = json_encode( $client->last_payload );
		$this->assert( strpos( $payload_json, $secret_key ) === false, '2.2 JSON request body does not leak the API key' );
	}

	/**
	 * 3. Test payload structure, max_tokens, and system prompt mapping.
	 */
	public function test_payload_structure_max_tokens_and_system(): void {
		echo "\n--- Section 3: Payload Structure, max_tokens, and system mapping ---\n";

		$client = new MockClaudeClient( 'sk-ant-test-key' );
		$client->create_response(
			[ [ 'role' => 'user', 'content' => 'Explain quantum computing.' ] ],
			[
				'model'              => 'claude-3-5-sonnet-20241022',
				'system_instruction' => 'You are an AI physics professor.',
				'max_tokens'         => 2048,
			]
		);

		$payload = $client->last_payload;

		$this->assert( isset( $payload['model'] ) && $payload['model'] === 'claude-3-5-sonnet-20241022', '3.1 Model is mapped in payload' );
		$this->assert( isset( $payload['max_tokens'] ) && $payload['max_tokens'] === 2048, '3.2 max_tokens is explicitly set to 2048' );
		$this->assert( isset( $payload['system'] ) && $payload['system'] === 'You are an AI physics professor.', '3.3 system instruction maps to top-level system parameter' );
		$this->assert( is_array( $payload['messages'] ), '3.4 messages is formatted as an array' );

		// Ensure system instruction was NOT inserted into messages array as a fake user message.
		$messages_roles = array_column( $payload['messages'], 'role' );
		$this->assert( ! in_array( 'system', $messages_roles, true ), '3.5 messages array does not contain system role' );
	}

	/**
	 * 4. Test multi-turn history normalization.
	 */
	public function test_multi_turn_history_normalization(): void {
		echo "\n--- Section 4: Multi-Turn History Normalization ---\n";

		$client = new MockClaudeClient( 'sk-ant-test-key' );
		$history = [
			[ 'role' => 'user', 'content' => 'Hello', 'metadata' => [ 'id' => 123 ] ],
			[ 'role' => 'assistant', 'content' => 'Hi, how may I help you?' ],
			[ 'role' => 'invalid_role', 'content' => 'Skip this' ],
			[ 'role' => 'user', 'content' => '' ], // Empty content should be skipped
			[ 'role' => 'user', 'content' => 'What is WordPress?' ],
		];

		$normalized = $client->normalize_input_messages( $history );

		$this->assert( count( $normalized ) === 3, '4.1 Exactly 3 valid messages retained' );
		$this->assert( $normalized[0]['role'] === 'user' && $normalized[0]['content'] === 'Hello', '4.2 First turn normalized correctly' );
		$this->assert( $normalized[1]['role'] === 'assistant' && $normalized[1]['content'] === 'Hi, how may I help you?', '4.3 Second turn normalized correctly' );
		$this->assert( $normalized[2]['role'] === 'user' && $normalized[2]['content'] === 'What is WordPress?', '4.4 Third turn normalized correctly' );
		$this->assert( ! isset( $normalized[0]['metadata'] ), '4.5 Internal metadata stripped from messages' );
	}

	/**
	 * 5. Test response parsing, token usage, stop reason, and request ID.
	 */
	public function test_response_parsing_and_token_usage(): void {
		echo "\n--- Section 5: Response Parsing & Token Usage ---\n";

		$client = new ClaudeClient( 'sk-ant-test-key' );
		$mock_body = json_encode( [
			'id'          => 'msg_01X9vhPqQubn1noq',
			'type'        => 'message',
			'role'        => 'assistant',
			'model'       => 'claude-3-5-haiku-20241022',
			'content'     => [
				[
					'type' => 'text',
					'text' => 'WordPress is a popular open-source CMS.',
				],
			],
			'stop_reason' => 'end_turn',
			'usage'       => [
				'input_tokens'  => 25,
				'output_tokens' => 12,
			],
		] );

		$response = $client->parse_response_body( $mock_body, 200, 'req_header_123', 'claude-3-5-haiku-20241022' );

		$this->assert( $response->get_content() === 'WordPress is a popular open-source CMS.', '5.1 Assistant content extracted' );
		$this->assert( $response->get_provider_id() === 'claude', '5.2 Provider ID is claude' );
		$this->assert( $response->get_model() === 'claude-3-5-haiku-20241022', '5.3 Model matched response model' );
		$this->assert( $response->get_input_tokens() === 25, '5.4 Input tokens is 25' );
		$this->assert( $response->get_output_tokens() === 12, '5.5 Output tokens is 12' );
		$this->assert( $response->get_total_tokens() === 37, '5.6 Total tokens is 37 (25 + 12)' );
		$this->assert( $response->get_finish_reason() === 'stop', '5.7 stop_reason "end_turn" normalized to "stop"' );
		$this->assert( $response->get_request_id() === 'req_header_123', '5.8 Request ID extracted from header/body' );
	}

	/**
	 * 6. Test multiple text blocks in content.
	 */
	public function test_multiple_text_blocks_handling(): void {
		echo "\n--- Section 6: Multiple Content Blocks Handling ---\n";

		$client = new ClaudeClient( 'sk-ant-test-key' );
		$mock_body = json_encode( [
			'id'          => 'msg_01multi',
			'type'        => 'message',
			'role'        => 'assistant',
			'model'       => 'claude-3-5-sonnet-20241022',
			'content'     => [
				[
					'type' => 'text',
					'text' => 'First paragraph of explanation. ',
				],
				[
					'type' => 'text',
					'text' => 'Second paragraph continues the thought.',
				],
			],
			'stop_reason' => 'end_turn',
			'usage'       => [
				'input_tokens'  => 30,
				'output_tokens' => 20,
			],
		] );

		$response = $client->parse_response_body( $mock_body, 200 );
		$expected = 'First paragraph of explanation. Second paragraph continues the thought.';

		$this->assert( $response->get_content() === $expected, '6.1 Multiple text content blocks concatenated in order' );
	}

	/**
	 * 7. Test error mappings and status codes.
	 */
	public function test_error_mappings_and_status_codes(): void {
		echo "\n--- Section 7: Error Mappings & Status Codes ---\n";

		$client = new ClaudeClient( 'sk-ant-test-key' );

		// 401 Authentication Error
		$threw_401 = false;
		$type_401  = '';
		try {
			$client->parse_response_body( json_encode( [
				'type'  => 'error',
				'error' => [ 'type' => 'authentication_error', 'message' => 'invalid x-api-key' ],
			] ), 401 );
		} catch ( ProviderException $e ) {
			$threw_401 = true;
			$type_401  = $e->get_error_type();
		}
		$this->assert( $threw_401 && $type_401 === 'authentication_failed', '7.1 HTTP 401 maps to authentication_failed' );

		// 400 Invalid Request
		$threw_400 = false;
		$type_400  = '';
		try {
			$client->parse_response_body( json_encode( [
				'type'  => 'error',
				'error' => [ 'type' => 'invalid_request_error', 'message' => 'max_tokens is too large' ],
			] ), 400 );
		} catch ( ProviderException $e ) {
			$threw_400 = true;
			$type_400  = $e->get_error_type();
		}
		$this->assert( $threw_400 && $type_400 === 'invalid_request', '7.2 HTTP 400 maps to invalid_request' );

		// 404 Model Unavailable
		$threw_404 = false;
		$type_404  = '';
		try {
			$client->parse_response_body( json_encode( [
				'type'  => 'error',
				'error' => [ 'type' => 'not_found_error', 'message' => 'model not found' ],
			] ), 404 );
		} catch ( ProviderException $e ) {
			$threw_404 = true;
			$type_404  = $e->get_error_type();
		}
		$this->assert( $threw_404 && $type_404 === 'model_unavailable', '7.3 HTTP 404 maps to model_unavailable' );

		// 429 Rate Limit
		$threw_429 = false;
		$type_429  = '';
		try {
			$client->parse_response_body( json_encode( [
				'type'  => 'error',
				'error' => [ 'type' => 'rate_limit_error', 'message' => 'Too many requests' ],
			] ), 429 );
		} catch ( ProviderException $e ) {
			$threw_429 = true;
			$type_429  = $e->get_error_type();
		}
		$this->assert( $threw_429 && $type_429 === 'rate_limited', '7.4 HTTP 429 maps to rate_limited' );

		// 529 Overloaded / 500 Provider Unavailable
		$threw_529 = false;
		$type_529  = '';
		try {
			$client->parse_response_body( json_encode( [
				'type'  => 'error',
				'error' => [ 'type' => 'overloaded_error', 'message' => 'Anthropic is overloaded' ],
			] ), 529 );
		} catch ( ProviderException $e ) {
			$threw_529 = true;
			$type_529  = $e->get_error_type();
		}
		$this->assert( $threw_529 && $type_529 === 'provider_unavailable', '7.5 HTTP 529 maps to provider_unavailable' );

		// Empty Content / Missing text
		$threw_empty = false;
		$type_empty  = '';
		try {
			$client->parse_response_body( json_encode( [
				'id'      => 'msg_empty',
				'content' => [],
			] ), 200 );
		} catch ( ProviderException $e ) {
			$threw_empty = true;
			$type_empty  = $e->get_error_type();
		}
		$this->assert( $threw_empty && $type_empty === 'invalid_response', '7.6 Empty content array maps to invalid_response' );
	}

	/**
	 * 8. Test secret scrubbing in ProviderException.
	 */
	public function test_secret_scrubbing_in_exceptions(): void {
		echo "\n--- Section 8: Secret Scrubbing in Exceptions ---\n";

		$leak_msg = 'Error with sk-ant-api03-abcdef1234567890 and Bearer sk-ant-secretkey';
		$sanitized = ProviderException::strip_credentials( $leak_msg );

		$this->assert( strpos( $sanitized, 'sk-ant-api03-abcdef1234567890' ) === false, '8.1 Claude sk-ant API key scrubbed' );
		$this->assert( strpos( $sanitized, 'sk-ant-secretkey' ) === false, '8.2 Bearer token scrubbed' );
		$this->assert( strpos( $sanitized, '[REDACTED' ) !== false, '8.3 Redaction marker placed' );
	}

	/**
	 * 9. Test connection minimal query.
	 */
	public function test_test_connection_minimal_query(): void {
		echo "\n--- Section 9: Test Connection Minimal Query ---\n";

		$mock_client = new MockClaudeClient( 'sk-ant-test-key' );
		$provider    = new ClaudeProvider( $mock_client );

		$connected = $mock_client->test_connection();

		$this->assert( $connected === true, '9.1 test_connection returns true on success' );
		$this->assert( isset( $mock_client->last_payload['max_tokens'] ) && $mock_client->last_payload['max_tokens'] === 1, '9.2 test_connection restricts max_tokens to 1' );
	}

	/**
	 * 10. Test provider validation and disabled states.
	 */
	public function test_provider_validation_and_disabled_checks(): void {
		echo "\n--- Section 10: Provider Validation & Disabled States ---\n";

		$provider = new ClaudeProvider();

		$this->assert( $provider instanceof ProviderInterface, '10.1 ClaudeProvider implements ProviderInterface' );
		$this->assert( $provider->get_id() === 'claude', '10.2 ClaudeProvider returns correct ID' );
		$this->assert( ! empty( $provider->get_models() ), '10.3 ClaudeProvider defines supported models list' );

		// When unconfigured in settings, chat throws ProviderException::not_configured
		$chat_threw = false;
		$chat_type  = '';
		try {
			$provider->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
		} catch ( ProviderException $e ) {
			$chat_threw = true;
			$chat_type  = $e->get_error_type();
		}
		$this->assert( $chat_threw, '10.4 Unconfigured ClaudeProvider::chat() throws ProviderException' );
		$this->assert( $chat_type === 'not_configured', '10.5 Unconfigured ClaudeProvider error type is not_configured' );
	}

	/**
	 * 11. Test Gemini and OpenAI regression prevention.
	 */
	public function test_gemini_and_openai_regression(): void {
		echo "\n--- Section 11: Gemini & OpenAI Regression Checks ---\n";

		$gemini = new GeminiProvider();
		$this->assert( $gemini instanceof ProviderInterface, '11.1 GeminiProvider implements ProviderInterface' );
		$this->assert( $gemini->get_id() === 'gemini', '11.2 GeminiProvider ID is gemini' );

		$openai = new OpenAIProvider();
		$this->assert( $openai instanceof ProviderInterface, '11.3 OpenAIProvider implements ProviderInterface' );
		$this->assert( $openai->get_id() === 'openai', '11.4 OpenAIProvider ID is openai' );

		$registry = new ProviderRegistry();
		$registry->register( $gemini );
		$registry->register( $openai );
		$registry->register( new ClaudeProvider() );

		$this->assert( $registry->has( 'gemini' ), '11.5 Registry contains gemini' );
		$this->assert( $registry->has( 'openai' ), '11.6 Registry contains openai' );
		$this->assert( $registry->has( 'claude' ), '11.7 Registry contains claude' );
		$this->assert( count( $registry->get_registered_ids() ) === 3, '11.8 Registry contains all 3 providers' );
	}
}
