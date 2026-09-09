<?php
/**
 * Test Suite: Node N19 - Live OpenAI Integration.
 *
 * Verifies OpenAIClient endpoint, headers, payload construction, store=false,
 * multi-turn history normalization, output parsing, token usage extraction,
 * error code mappings, secret scrubbing, test connection, rate limiting,
 * Gemini regression prevention, and Claude placeholder isolation.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

use SkyFish\GeminiChat\Providers\ProviderInterface;
use SkyFish\GeminiChat\Providers\ProviderRegistry;
use SkyFish\GeminiChat\Providers\ProviderException;
use SkyFish\GeminiChat\Providers\ProviderResponse;
use SkyFish\GeminiChat\Providers\OpenAIClient;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mock OpenAIClient for testing request construction and simulated HTTP responses.
 */
class MockOpenAIClient extends OpenAIClient {

	public array $last_url = [];
	public array $last_args = [];
	public $next_response = null;

	public function set_next_response( $response ): void {
		$this->next_response = $response;
	}

	protected function execute_http_request( string $url, array $args ) {
		$this->last_url  = [ $url ];
		$this->last_args = $args;

		if ( null !== $this->next_response ) {
			return $this->next_response;
		}

		// Default successful mock response.
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'headers'  => [ 'x-request-id' => 'req_mock_test_123' ],
			'body'     => json_encode( [
				'id'     => 'resp_mock_test_123',
				'model'  => 'gpt-4o-mini',
				'output' => [
					[
						'type'    => 'message',
						'role'    => 'assistant',
						'content' => [
							[
								'type' => 'output_text',
								'text' => 'Hello from mocked OpenAI Responses API!',
							],
						],
					],
				],
				'usage'  => [
					'input_tokens'  => 15,
					'output_tokens' => 8,
					'total_tokens'  => 23,
				],
			] ),
		];
	}
}

/**
 * Class TestOpenAIIntegration
 */
class TestOpenAIIntegration {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	/**
	 * Runs all test cases.
	 */
	public function run(): void {
		echo "Starting Node N19 Live OpenAI Integration Test Suite...\n\n";

		$this->test_client_constants_and_endpoint();
		$this->test_request_headers_and_security();
		$this->test_payload_structure_and_store_flag();
		$this->test_multi_turn_history_normalization();
		$this->test_successful_response_parsing_and_token_usage();
		$this->test_multi_chunk_output_text_joining();
		$this->test_error_mappings_and_status_codes();
		$this->test_secret_scrubbing_in_exceptions();
		$this->test_test_connection_minimal_query();
		$this->test_provider_validation_and_disabled_checks();
		$this->test_gemini_regression_and_claude_isolation();

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
	 * 1. Test OpenAIClient constants, endpoint, and HTTP method.
	 */
	public function test_client_constants_and_endpoint(): void {
		echo "\n--- Section 1: Endpoint & HTTP Method ---\n";

		$this->assert( class_exists( 'SkyFish\GeminiChat\Providers\OpenAIClient' ), '1.1 OpenAIClient class exists' );
		$this->assert( OpenAIClient::ENDPOINT === 'https://api.openai.com/v1/responses', '1.2 OpenAIClient uses /v1/responses endpoint' );

		$client = new MockOpenAIClient( 'sk-test-secret-key-12345' );
		$client->create_response( 'gpt-4o-mini', [ [ 'role' => 'user', 'content' => 'Test' ] ] );

		$this->assert( ! empty( $client->last_url ), '1.3 HTTP request dispatched' );
		$this->assert( $client->last_url[0] === 'https://api.openai.com/v1/responses', '1.4 Request dispatched to exact /v1/responses URL' );
		$this->assert( ( $client->last_args['method'] ?? '' ) === 'POST', '1.5 HTTP request method is POST' );
	}

	/**
	 * 2. Test request headers and credential isolation.
	 */
	public function test_request_headers_and_security(): void {
		echo "\n--- Section 2: Headers & Credential Security ---\n";

		$secret_key = 'sk-test-secret-key-abc987654321';
		$client     = new MockOpenAIClient( $secret_key );
		$client->create_response( 'gpt-4o-mini', [ [ 'role' => 'user', 'content' => 'Test query' ] ] );

		$headers = $client->last_args['headers'] ?? [];
		$this->assert( isset( $headers['Authorization'] ), '2.1 Authorization header is present' );
		$this->assert( $headers['Authorization'] === 'Bearer ' . $secret_key, '2.2 Authorization header formatted as Bearer <key>' );
		$this->assert( isset( $headers['Content-Type'] ) && $headers['Content-Type'] === 'application/json', '2.3 Content-Type header is application/json' );

		// Ensure key is NOT in URL query parameters.
		$parsed_url = parse_url( $client->last_url[0] );
		$this->assert( empty( $parsed_url['query'] ), '2.4 URL contains no query parameters' );
		$this->assert( strpos( $client->last_url[0], 'key=' ) === false, '2.5 API key never sent in URL query parameters' );

		// Ensure raw key is NOT present in body payload.
		$raw_body = $client->last_args['body'] ?? '';
		$this->assert( strpos( $raw_body, $secret_key ) === false, '2.6 Raw API key never included in JSON request body' );
	}

	/**
	 * 3. Test payload structure, instructions, model, and store: false.
	 */
	public function test_payload_structure_and_store_flag(): void {
		echo "\n--- Section 3: Payload Structure & store: false ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );
		$client->create_response(
			'gpt-4o',
			[ [ 'role' => 'user', 'content' => 'What is WordPress?' ] ],
			[
				'instructions' => 'You are a helpful assistant.',
				'store'        => false,
			]
		);

		$payload = json_decode( $client->last_args['body'] ?? '{}', true );

		$this->assert( isset( $payload['model'] ) && $payload['model'] === 'gpt-4o', '3.1 Model matched gpt-4o' );
		$this->assert( array_key_exists( 'store', $payload ), '3.2 store key exists in payload' );
		$this->assert( $payload['store'] === false, '3.3 store is explicitly false by default' );
		$this->assert( isset( $payload['instructions'] ) && $payload['instructions'] === 'You are a helpful assistant.', '3.4 instructions string mapped correctly' );
		$this->assert( is_array( $payload['input'] ), '3.5 input is formatted as an array' );
	}

	/**
	 * 4. Test multi-turn history normalization.
	 */
	public function test_multi_turn_history_normalization(): void {
		echo "\n--- Section 4: Multi-turn History Normalization ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );
		$history = [
			[ 'role' => 'system', 'content' => 'Ignore me or elevate to instructions' ],
			[ 'role' => 'user', 'content' => 'Turn 1 user' ],
			[ 'role' => 'assistant', 'content' => 'Turn 1 bot' ],
			[ 'role' => 'user', 'content' => 'Turn 2 user' ],
		];

		$client->create_response( 'gpt-4o-mini', $history, [ 'instructions' => 'Base instructions' ] );
		$payload = json_decode( $client->last_args['body'] ?? '{}', true );
		$input   = $payload['input'] ?? [];

		$this->assert( count( $input ) === 3, '4.1 System role filtered out of input turns' );
		$this->assert( $input[0]['role'] === 'user' && $input[0]['content'] === 'Turn 1 user', '4.2 First turn is user' );
		$this->assert( $input[1]['role'] === 'assistant' && $input[1]['content'] === 'Turn 1 bot', '4.3 Second turn is assistant' );
		$this->assert( $input[2]['role'] === 'user' && $input[2]['content'] === 'Turn 2 user', '4.4 Third turn is user' );
	}

	/**
	 * 5. Test successful response parsing, output text extraction, and token usage.
	 */
	public function test_successful_response_parsing_and_token_usage(): void {
		echo "\n--- Section 5: Successful Parsing & Token Usage ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );
		$client->set_next_response( [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'headers'  => [ 'x-request-id' => 'req_custom_999' ],
			'body'     => json_encode( [
				'id'     => 'resp_custom_999',
				'model'  => 'gpt-4o-mini',
				'output' => [
					[
						'type'    => 'message',
						'role'    => 'assistant',
						'content' => [
							[
								'type' => 'output_text',
								'text' => 'The quick brown fox jumps over the lazy dog.',
							],
						],
					],
				],
				'usage'  => [
					'input_tokens'  => 42,
					'output_tokens' => 12,
					'total_tokens'  => 54,
				],
			] ),
		] );

		$response = $client->create_response( 'gpt-4o-mini', [ [ 'role' => 'user', 'content' => 'Test' ] ] );

		$this->assert( $response instanceof ProviderResponse, '5.1 Returns ProviderResponse instance' );
		$this->assert( $response->content === 'The quick brown fox jumps over the lazy dog.', '5.2 Content parsed accurately' );
		$this->assert( $response->input_tokens === 42, '5.3 input_tokens extracted' );
		$this->assert( $response->output_tokens === 12, '5.4 output_tokens extracted' );
		$this->assert( $response->total_tokens === 54, '5.5 total_tokens extracted' );
		$this->assert( $response->request_id === 'req_custom_999', '5.6 request_id extracted from headers' );
		$this->assert( $response->provider === 'openai', '5.7 provider is openai' );
		$this->assert( $response->model === 'gpt-4o-mini', '5.8 model is gpt-4o-mini' );
	}

	/**
	 * 6. Test multi-chunk output_text segment joining.
	 */
	public function test_multi_chunk_output_text_joining(): void {
		echo "\n--- Section 6: Multi-chunk output_text Joining ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );
		$client->set_next_response( [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'headers'  => [],
			'body'     => json_encode( [
				'id'     => 'resp_multichunk_1',
				'model'  => 'gpt-4o',
				'output' => [
					[
						'type'    => 'message',
						'role'    => 'assistant',
						'content' => [
							[ 'type' => 'output_text', 'text' => 'Part 1: Hello. ' ],
							[ 'type' => 'output_text', 'text' => 'Part 2: Welcome to ' ],
							[ 'type' => 'output_text', 'text' => 'Multi-AI WordPress Chatbot!' ],
						],
					],
				],
				'usage'  => [ 'input_tokens' => 10, 'output_tokens' => 15, 'total_tokens' => 25 ],
			] ),
		] );

		$response = $client->create_response( 'gpt-4o', [ [ 'role' => 'user', 'content' => 'Test' ] ] );
		$this->assert(
			$response->content === 'Part 1: Hello. Part 2: Welcome to Multi-AI WordPress Chatbot!',
			'6.1 Multi-chunk output_text joined into single contiguous string'
		);
	}

	/**
	 * 7. Test error mappings and HTTP status codes.
	 */
	public function test_error_mappings_and_status_codes(): void {
		echo "\n--- Section 7: HTTP Status Code & Error Mappings ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );

		// 400 Bad Request -> invalid_request
		$client->set_next_response( [
			'response' => [ 'code' => 400, 'message' => 'Bad Request' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Invalid schema provided.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'invalid_request', '7.1 HTTP 400 maps to invalid_request' );

		// 401 Unauthorized -> authentication_error
		$client->set_next_response( [
			'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Incorrect API key provided.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'authentication_error', '7.2 HTTP 401 maps to authentication_error' );

		// 404 Not Found -> model_unavailable
		$client->set_next_response( [
			'response' => [ 'code' => 404, 'message' => 'Not Found' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'The model gpt-nonexistent does not exist.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'model_unavailable', '7.3 HTTP 404 maps to model_unavailable' );

		// 408 Timeout -> timeout
		$client->set_next_response( [
			'response' => [ 'code' => 408, 'message' => 'Request Timeout' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Request timed out.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'timeout', '7.4 HTTP 408 maps to timeout' );

		// 429 Rate Limited -> rate_limited
		$client->set_next_response( [
			'response' => [ 'code' => 429, 'message' => 'Too Many Requests' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'Rate limit exceeded.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'rate_limited', '7.5 HTTP 429 maps to rate_limited' );

		// 500 Internal Server Error -> server_error
		$client->set_next_response( [
			'response' => [ 'code' => 500, 'message' => 'Internal Server Error' ],
			'body'     => json_encode( [ 'error' => [ 'message' => 'OpenAI server issue.' ] ] ),
		] );
		$this->assert_exception_type( $client, 'server_error', '7.6 HTTP 500 maps to server_error' );

		// WP_Error connection failure -> connection_failed
		if ( class_exists( 'WP_Error' ) ) {
			$client->set_next_response( new \WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' ) );
			$this->assert_exception_type( $client, 'connection_failed', '7.7 WP_Error maps to connection_failed' );
		}

		// Malformed JSON -> invalid_response
		$client->set_next_response( [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '<html><body>Service Gateway Error</body></html>',
		] );
		$this->assert_exception_type( $client, 'invalid_response', '7.8 Malformed non-JSON body maps to invalid_response' );

		// Missing assistant output -> invalid_response
		$client->set_next_response( [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => json_encode( [ 'id' => '123', 'output' => [] ] ),
		] );
		$this->assert_exception_type( $client, 'invalid_response', '7.9 Missing assistant output in 200 body maps to invalid_response' );
	}

	private function assert_exception_type( OpenAIClient $client, string $expected_type, string $message ): void {
		$caught_type = '';
		try {
			$client->create_response( 'gpt-4o-mini', [ [ 'role' => 'user', 'content' => 'Test' ] ] );
		} catch ( ProviderException $e ) {
			$caught_type = $e->get_error_type();
		} catch ( \Throwable $t ) {
			$caught_type = 'unexpected_throwable_' . get_class( $t );
		}
		$this->assert( $caught_type === $expected_type, $message );
	}

	/**
	 * 8. Test secret scrubbing in ProviderException messages.
	 */
	public function test_secret_scrubbing_in_exceptions(): void {
		echo "\n--- Section 8: Secret Scrubbing in Exceptions ---\n";

		$secret = 'sk-proj-super-secret-api-key-999988887777';
		$client = new MockOpenAIClient( $secret );
		$client->set_next_response( [
			'response' => [ 'code' => 401, 'message' => 'Unauthorized' ],
			'body'     => json_encode( [ 'error' => [ 'message' => "Invalid key {$secret} rejected by server." ] ] ),
		] );

		$exception_msg = '';
		try {
			$client->create_response( 'gpt-4o-mini', [ [ 'role' => 'user', 'content' => 'Ping' ] ] );
		} catch ( ProviderException $e ) {
			$exception_msg = $e->getMessage();
		}

		$this->assert( strpos( $exception_msg, $secret ) === false, '8.1 Raw OpenAI secret key stripped from ProviderException' );
		$this->assert( strpos( $exception_msg, '[REDACTED_API_KEY]' ) !== false, '8.2 Sanitized string contains [REDACTED_API_KEY]' );
	}

	/**
	 * 9. Test admin "Test Connection" executes minimal 1-token query.
	 */
	public function test_test_connection_minimal_query(): void {
		echo "\n--- Section 9: Admin Test Connection ---\n";

		$client = new MockOpenAIClient( 'sk-test-key' );
		$client->set_next_response( [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => json_encode( [
				'id'     => 'resp_test_conn',
				'model'  => 'gpt-4o-mini',
				'output' => [
					[
						'type'    => 'message',
						'role'    => 'assistant',
						'content' => [
							[ 'type' => 'output_text', 'text' => 'P' ],
						],
					],
				],
				'usage'  => [ 'input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2 ],
			] ),
		] );

		$connected = $client->test_connection( 'gpt-4o-mini' );
		$payload   = json_decode( $client->last_args['body'] ?? '{}', true );

		$this->assert( $connected === true, '9.1 test_connection returns true on 200' );
		$this->assert( isset( $payload['max_output_tokens'] ) && $payload['max_output_tokens'] === 1, '9.2 test_connection limits max_output_tokens to 1' );
	}

	/**
	 * 10. Test provider validation when disabled or missing credentials.
	 */
	public function test_provider_validation_and_disabled_checks(): void {
		echo "\n--- Section 10: Provider Validation & Disabled Checks ---\n";

		$mock_client = new MockOpenAIClient( 'sk-test-key' );
		$provider    = new OpenAIProvider( $mock_client );

		// When disabled in settings, chat throws ProviderException::not_configured
		$threw_disabled = false;
		$disabled_type  = '';
		try {
			$provider->chat( [ [ 'role' => 'user', 'content' => 'Hello' ] ] );
		} catch ( ProviderException $e ) {
			$threw_disabled = true;
			$disabled_type  = $e->get_error_type();
		}

		$this->assert( $threw_disabled, '10.1 OpenAIProvider throws when provider is not enabled/configured' );
		$this->assert( $disabled_type === 'not_configured', '10.2 Error type is not_configured' );
	}

	/**
	 * 11. Test Gemini regression prevention and Claude isolation.
	 */
	public function test_gemini_regression_and_claude_isolation(): void {
		echo "\n--- Section 11: Gemini Regression & Claude Isolation ---\n";

		$gemini = new GeminiProvider();
		$this->assert( $gemini->get_id() === 'gemini', '11.1 GeminiProvider ID is gemini' );
		$this->assert( ! empty( $gemini->get_models() ), '11.2 Gemini models list intact' );

		$claude = new ClaudeProvider();
		$claude_threw = false;
		$claude_type  = '';
		try {
			$claude->chat( [ [ 'role' => 'user', 'content' => 'Hello Claude' ] ] );
		} catch ( ProviderException $e ) {
			$claude_threw = true;
			$claude_type  = $e->get_error_type();
		}
		$this->assert( $claude_threw, '11.3 ClaudeProvider::chat() throws ProviderException' );
		$this->assert( $claude_type === 'not_configured', '11.4 ClaudeProvider throws not_configured without outbound calls' );
	}
}

// Auto-run if executed directly via CLI or test runner.
if ( defined( 'PHPUNIT_RUNNER' ) || ( defined( 'DOING_TESTS' ) && DOING_TESTS ) ) {
	$suite = new TestOpenAIIntegration();
	$suite->run();
}
