<?php
/**
 * Standalone Test Suite for Node N7: Conversation Memory.
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

class MemoryMockConversationRepo extends ConversationRepository {
	public array $conversations = [];

	public function create( string $session_hash, int $user_id = 0, ?string $title = null, ?string $interaction_id = null, ?string $public_id = null ): int {
		$id = count( $this->conversations ) + 1;
		$this->conversations[ $id ] = [
			'id'             => $id,
			'public_id'      => $public_id ?? self::generate_public_id(),
			'session_hash'   => $session_hash,
			'status'         => 'active',
			'interaction_id' => $interaction_id,
			'message_count'  => 0,
		];
		return $id;
	}

	public function get_by_session_hash( string $session_hash ): ?array {
		foreach ( $this->conversations as $conv ) {
			if ( $conv['session_hash'] === $session_hash && 'active' === $conv['status'] ) {
				return $conv;
			}
		}
		return null;
	}

	public function update_interaction_id( int $id, string $interaction_id ): bool {
		if ( isset( $this->conversations[ $id ] ) ) {
			$this->conversations[ $id ]['interaction_id'] = $interaction_id;
			return true;
		}
		return false;
	}

	public function get_interaction_id( int $id ): ?string {
		return $this->conversations[ $id ]['interaction_id'] ?? null;
	}

	public function clear_interaction_id( int $id ): bool {
		if ( isset( $this->conversations[ $id ] ) ) {
			$this->conversations[ $id ]['interaction_id'] = null;
			return true;
		}
		return false;
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
			$this->conversations[ $id ]['message_count'] += $by;
			return true;
		}
		return false;
	}
}

class MemoryMockMessageRepo extends MessageRepository {
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

class MemoryMockGeminiClient extends GeminiClient {
	public array $calls = [];
	public $next_response_callback = null;

	public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
		$this->calls[] = [
			'input'                   => $input,
			'previous_interaction_id' => $previous_interaction_id,
			'options'                 => $options,
		];

		if ( is_callable( $this->next_response_callback ) ) {
			return call_user_func( $this->next_response_callback, $input, $previous_interaction_id );
		}

		return [
			'interaction_id' => 'inter_default_' . count( $this->calls ),
			'model'          => 'gemini-3.8-flash',
			'text'           => 'Echo response for: ' . $input,
			'usage'          => [ 'input_tokens' => 10, 'output_tokens' => 15, 'total_tokens' => 25 ],
			'raw'            => [],
		];
	}
}

class ConversationMemoryTest {

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
		echo "Running Node N7 Conversation Memory Tests\n";
		echo "========================================\n\n";

		$this->test_first_turn_continuation();
		$this->test_multi_turn_continuation();
		$this->test_session_isolation();
		$this->test_failure_preservation();
		$this->test_stale_interaction_recovery();
		$this->test_reset_isolation();
		$this->test_store_messages_privacy();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_first_turn_continuation(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => true, 'store_messages' => true ];

		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess_token = 'gca_sess_0123456789abcdef0123456789abcdef';

		$gemini->next_response_callback = function ( $input, $prev_id ) {
			return [
				'interaction_id' => 'inter_turn_1',
				'model'          => 'gemini-3.8-flash',
				'text'           => 'Nice to meet you, John!',
				'usage'          => [ 'input_tokens' => 5, 'output_tokens' => 8, 'total_tokens' => 13 ],
				'raw'            => [],
			];
		};

		$res = $chat->handle_chat( 'My name is John.', $sess_token );

		$this->assert( ! is_wp_error( $res ), 'First turn succeeds' );
		$this->assert( null === $gemini->calls[0]['previous_interaction_id'], 'First turn passes null previous_interaction_id' );
		$this->assert( 'inter_turn_1' === $session->get_interaction_id( $sess_token ), 'Turn 1 interaction_id saved in session' );
		$this->assert( ! isset( $res['interaction_id'] ), 'Zero interaction_id exposed in public REST response' );
	}

	private function test_multi_turn_continuation(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [ 'enabled' => true, 'store_messages' => true ];

		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess_token = 'gca_sess_0123456789abcdef0123456789abcdef';

		// Turn 1
		$gemini->next_response_callback = function ( $input, $prev_id ) {
			return [
				'interaction_id' => 'inter_turn_1',
				'model'          => 'gemini-3.8-flash',
				'text'           => 'Turn 1 response',
				'usage'          => [],
				'raw'            => [],
			];
		};
		$chat->handle_chat( 'Hello', $sess_token );

		// Turn 2
		$gemini->next_response_callback = function ( $input, $prev_id ) {
			return [
				'interaction_id' => 'inter_turn_2',
				'model'          => 'gemini-3.8-flash',
				'text'           => 'Turn 2 response',
				'usage'          => [],
				'raw'            => [],
			];
		};
		$chat->handle_chat( 'What is my name?', $sess_token );

		$this->assert( 'inter_turn_1' === $gemini->calls[1]['previous_interaction_id'], 'Turn 2 passes Turn 1 interaction_id' );
		$this->assert( 'inter_turn_2' === $session->get_interaction_id( $sess_token ), 'Turn 2 interaction_id replaces Turn 1' );

		// Turn 3
		$gemini->next_response_callback = function ( $input, $prev_id ) {
			return [
				'interaction_id' => 'inter_turn_3',
				'model'          => 'gemini-3.8-flash',
				'text'           => 'Turn 3 response',
				'usage'          => [],
				'raw'            => [],
			];
		};
		$chat->handle_chat( 'Tell me a joke.', $sess_token );

		$this->assert( 'inter_turn_2' === $gemini->calls[2]['previous_interaction_id'], 'Turn 3 passes Turn 2 interaction_id' );
		$this->assert( 'inter_turn_3' === $session->get_interaction_id( $sess_token ), 'Turn 3 interaction_id saved' );
		$this->assert( 1 === count( $conv_repo->conversations ), 'All turns resolve to the SAME single conversation record' );
	}

	private function test_session_isolation(): void {
		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess_A = 'gca_sess_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$sess_B = 'gca_sess_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

		// Session A Turn 1
		$gemini->next_response_callback = function () {
			return [ 'interaction_id' => 'inter_A_1', 'text' => 'Hi A', 'usage' => [], 'raw' => [] ];
		};
		$chat->handle_chat( 'Message from A', $sess_A );

		// Session B Turn 1
		$gemini->next_response_callback = function () {
			return [ 'interaction_id' => 'inter_B_1', 'text' => 'Hi B', 'usage' => [], 'raw' => [] ];
		};
		$chat->handle_chat( 'Message from B', $sess_B );

		// Session A Turn 2
		$gemini->next_response_callback = function () {
			return [ 'interaction_id' => 'inter_A_2', 'text' => 'Hi A 2', 'usage' => [], 'raw' => [] ];
		};
		$chat->handle_chat( 'Follow-up from A', $sess_A );

		$this->assert( 'inter_A_1' === $gemini->calls[2]['previous_interaction_id'], 'Session A Turn 2 isolates to Session A context' );
		$this->assert( 'inter_A_2' === $session->get_interaction_id( $sess_A ), 'Session A interaction_id is inter_A_2' );
		$this->assert( 'inter_B_1' === $session->get_interaction_id( $sess_B ), 'Session B interaction_id remains inter_B_1' );
	}

	private function test_failure_preservation(): void {
		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess = 'gca_sess_0123456789abcdef0123456789abcdef';

		// Turn 1 success
		$gemini->next_response_callback = function () {
			return [ 'interaction_id' => 'inter_valid_1', 'text' => 'Success', 'usage' => [], 'raw' => [] ];
		};
		$chat->handle_chat( 'Hello', $sess );

		// Turn 2 timeout failure
		$gemini->next_response_callback = function () {
			return new WP_Error( 'GCA_GEMINI_TIMEOUT', 'Timed out', [ 'status' => 504 ] );
		};
		$res = $chat->handle_chat( 'Follow up', $sess );

		$this->assert( is_wp_error( $res ), 'Timeout returns WP_Error' );
		$this->assert( 'inter_valid_1' === $session->get_interaction_id( $sess ), 'Valid interaction_id preserved on timeout failure' );
	}

	private function test_stale_interaction_recovery(): void {
		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess = 'gca_sess_0123456789abcdef0123456789abcdef';

		// Seed session with an old/expired interaction
		$session->get_or_create_session( $sess );
		$session->set_interaction_id( $sess, 1, 'inter_stale_999' );

		// Gemini rejects stale interaction on first try, but succeeds on fresh retry
		$attempts = 0;
		$gemini->next_response_callback = function ( $input, $prev_id ) use ( &$attempts ) {
			$attempts++;
			if ( ! empty( $prev_id ) ) {
				return new WP_Error( 'GCA_GEMINI_INVALID_REQUEST', 'Invalid previous_interaction_id provided', [ 'status' => 400 ] );
			}
			return [
				'interaction_id' => 'inter_fresh_100',
				'text'           => 'Recovered and answering prompt',
				'usage'          => [],
				'raw'            => [],
			];
		};

		$res = $chat->handle_chat( 'Message after expiration', $sess );

		$this->assert( ! is_wp_error( $res ), 'Stale interaction safely recovered' );
		$this->assert( 2 === $attempts, 'Gemini retried exactly ONCE without previous_interaction_id' );
		$this->assert( 'inter_fresh_100' === $session->get_interaction_id( $sess ), 'New fresh interaction_id stored after recovery' );
	}

	private function test_reset_isolation(): void {
		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess_A = 'gca_sess_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
		$sess_B = 'gca_sess_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

		$session->get_or_create_session( $sess_A );
		$session->set_interaction_id( $sess_A, 1, 'inter_A' );

		$session->get_or_create_session( $sess_B );
		$session->set_interaction_id( $sess_B, 2, 'inter_B' );

		// Reset only Session A
		$chat->reset_session( $sess_A );

		$this->assert( null === $session->get_interaction_id( $sess_A ), 'Session A interaction_id cleared on reset' );
		$this->assert( 'inter_B' === $session->get_interaction_id( $sess_B ), 'Session B interaction_id untouched' );
	}

	private function test_store_messages_privacy(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => false,
		];

		$conv_repo = new MemoryMockConversationRepo();
		$msg_repo  = new MemoryMockMessageRepo();
		$gemini    = new MemoryMockGeminiClient();
		$session   = new SessionService( $conv_repo, $msg_repo );
		$chat      = new ChatService( null, $session, $conv_repo, $msg_repo, $gemini );

		$sess = 'gca_sess_0123456789abcdef0123456789abcdef';

		$gemini->next_response_callback = function () {
			return [ 'interaction_id' => 'inter_turn_priv_1', 'text' => 'Private reply', 'usage' => [], 'raw' => [] ];
		};

		$res = $chat->handle_chat( 'Private message', $sess );

		$this->assert( ! is_wp_error( $res ), 'Chat turn succeeds with privacy mode' );
		$this->assert( 0 === count( $msg_repo->messages ), 'Zero message rows created in database when store_messages is false' );
		$this->assert( 'inter_turn_priv_1' === $session->get_interaction_id( $sess ), 'Memory context still preserved via interaction_id' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ConversationMemoryTest();
	$suite->run_all();
}
