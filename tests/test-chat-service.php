<?php
/**
 * Standalone Test Suite for ChatService (Node N5).
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', __DIR__ . '/../' );
}

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

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 0;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12 ) {
		return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);
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

// Mock WordPress options.
global $mock_options;
$mock_options = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

// Mock transient cache.
global $mock_transients;
$mock_transients = [];

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $mock_transients;
		$mock_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $mock_transients;
		return $mock_transients[ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		global $mock_transients;
		unset( $mock_transients[ $transient ] );
		return true;
	}
}

// Load dependencies.
require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Database/ConversationRepository.php';
require_once __DIR__ . '/../includes/Database/MessageRepository.php';
require_once __DIR__ . '/../includes/Database/SessionService.php';
require_once __DIR__ . '/../includes/class-gemini-client.php';
require_once __DIR__ . '/../includes/class-validator.php';
require_once __DIR__ . '/../includes/class-chat-service.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\SessionService;
use SkyFish\GeminiChat\GeminiClient;
use WP_Error;

class MockConversationRepo extends ConversationRepository {
	public array $conversations = [];

	public function create( string $session_hash, int $user_id = 0, ?string $title = null, ?string $interaction_id = null, ?string $public_id = null ): int {
		$id = count( $this->conversations ) + 1;
		$this->conversations[ $id ] = [
			'id'           => $id,
			'public_id'    => $public_id ?? self::generate_public_id(),
			'session_hash' => $session_hash,
			'status'       => 'active',
			'msg_count'    => 0,
		];
		return $id;
	}

	public function get_by_session_hash( string $session_hash ): ?array {
		foreach ( $this->conversations as $conv ) {
			if ( $conv['session_hash'] === $session_hash ) {
				return $conv;
			}
		}
		return null;
	}

	public function update_status( int $id, string $status ): bool {
		if ( isset( $this->conversations[ $id ] ) ) {
			$this->conversations[ $id ]['status'] = $status;
			return true;
		}
		return false;
	}

	public function update_last_active( int $id ): bool {
		return true;
	}

	public function increment_message_count( int $id, int $by = 1 ): bool {
		if ( isset( $this->conversations[ $id ] ) ) {
			$this->conversations[ $id ]['msg_count'] += $by;
			return true;
		}
		return false;
	}
}

class MockMessageRepo extends MessageRepository {
	public array $messages = [];

	public function create( int $conversation_id, string $role, string $content, ?string $model = null, int $input_tokens = 0, int $output_tokens = 0, int $latency_ms = 0 ): int {
		$id = count( $this->messages ) + 1;
		$this->messages[ $id ] = [
			'id'              => $id,
			'conversation_id' => $conversation_id,
			'role'            => $role,
			'content'         => $content,
			'model'           => $model,
		];
		return $id;
	}
}

class MockGeminiClient extends GeminiClient {
	public $next_response = null;

	public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
		return $this->next_response;
	}
}

class ChatServiceTest {

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
		echo "Running Node N5 ChatService Tests\n";
		echo "========================================\n\n";

		$this->test_chat_disabled();
		$this->test_successful_chat_turn();
		$this->test_store_messages_disabled();
		$this->test_error_mapping_auth_error();
		$this->test_error_mapping_timeout();
		$this->test_error_mapping_quota();
		$this->test_reset_session();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_chat_disabled(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => false ];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();
		$service   = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );

		$res = $service->handle_chat( 'Hello', 'gca_sess_0123456789abcdef0123456789abcdef' );
		$this->assert( is_wp_error( $res ) && 'CHAT_DISABLED' === $res->get_error_code(), 'Disabled chatbot returns CHAT_DISABLED' );
	}

	private function test_successful_chat_turn(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => true,
			'model'          => 'gemini-3.8-flash',
		];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();

		$gemini->next_response = [
			'interaction_id' => 'inter_test_123',
			'model'          => 'gemini-3.8-flash',
			'text'           => 'This is the Gemini response.',
			'usage'          => [ 'input_tokens' => 10, 'output_tokens' => 15, 'total_tokens' => 25 ],
			'raw'            => [],
		];

		$service = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );
		$res     = $service->handle_chat( 'Hello bot', 'gca_sess_0123456789abcdef0123456789abcdef', [], 'req_uuid_123' );

		$this->assert( ! is_wp_error( $res ), 'Successful chat turn returns array' );
		$this->assert( 'This is the Gemini response.' === $res['message'], 'Assistant message returned' );
		$this->assert( 'req_uuid_123' === $res['request_id'], 'Request ID preserved' );
		$this->assert( ! empty( $res['conversation_id'] ) && is_string( $res['conversation_id'] ), 'Safe public conversation ID returned' );
		$this->assert( 'gemini-3.8-flash' === $res['meta']['model'], 'Model metadata returned' );
		$this->assert( 2 === count( $msg_repo->messages ), 'User and Assistant messages persisted when store_messages is true' );
	}

	private function test_store_messages_disabled(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => false,
			'model'          => 'gemini-3.8-flash',
		];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();

		$gemini->next_response = [
			'interaction_id' => 'inter_test_456',
			'model'          => 'gemini-3.8-flash',
			'text'           => 'Privacy-first response.',
			'usage'          => [],
			'raw'            => [],
		];

		$service = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );
		$res     = $service->handle_chat( 'Private message', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$this->assert( ! is_wp_error( $res ), 'Chat handles response when store_messages is false' );
		$this->assert( 0 === count( $msg_repo->messages ), 'Zero messages persisted when store_messages is false' );
	}

	private function test_error_mapping_auth_error(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => true ];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();
		$gemini->next_response = new WP_Error( 'GCA_GEMINI_AUTH_ERROR', 'Bad key', [ 'status' => 401 ] );

		$service = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );
		$res     = $service->handle_chat( 'Hello', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$this->assert( is_wp_error( $res ), 'Auth failure returns WP_Error' );
		$this->assert( 'AI_AUTH_ERROR' === $res->get_error_code(), 'Mapped to public AI_AUTH_ERROR' );
	}

	private function test_error_mapping_timeout(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => true ];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();
		$gemini->next_response = new WP_Error( 'GCA_GEMINI_TIMEOUT', 'Timed out', [ 'status' => 504 ] );

		$service = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );
		$res     = $service->handle_chat( 'Hello', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$this->assert( is_wp_error( $res ) && 'AI_TIMEOUT' === $res->get_error_code(), 'Mapped to public AI_TIMEOUT' );
	}

	private function test_error_mapping_quota(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => true ];

		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();
		$gemini->next_response = new WP_Error( 'GCA_GEMINI_QUOTA_ERROR', 'Quota exceeded', [ 'status' => 429 ] );

		$service = new ChatService( null, null, $conv_repo, $msg_repo, $gemini );
		$res     = $service->handle_chat( 'Hello', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$this->assert( is_wp_error( $res ) && 'AI_QUOTA_ERROR' === $res->get_error_code(), 'Mapped to public AI_QUOTA_ERROR' );
	}

	private function test_reset_session(): void {
		$conv_repo = new MockConversationRepo();
		$msg_repo  = new MockMessageRepo();
		$gemini    = new MockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$service   = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess_token = 'gca_sess_0123456789abcdef0123456789abcdef';
		$session->get_or_create_session( $sess_token );

		$reset_res = $service->reset_session( $sess_token );
		$this->assert( true === $reset_res, 'Reset session returns true' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ChatServiceTest();
	$suite->run_all();
}
