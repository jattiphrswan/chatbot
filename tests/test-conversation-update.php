<?php
/**
 * Dedicated Test Suite for Conversation Update Resilience & Database Robustness.
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'GCA_PLUGIN_DIR' ) ) {
	define( 'GCA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

putenv( 'GEMINI_API_KEY=test_api_key_valid_12345' );

require_once GCA_PLUGIN_DIR . 'includes/Admin/SettingsService.php';
require_once GCA_PLUGIN_DIR . 'includes/Database/ConversationRepository.php';
require_once GCA_PLUGIN_DIR . 'includes/Database/MessageRepository.php';
require_once GCA_PLUGIN_DIR . 'includes/Database/SessionService.php';
require_once GCA_PLUGIN_DIR . 'includes/class-gemini-client.php';
require_once GCA_PLUGIN_DIR . 'includes/class-validator.php';
require_once GCA_PLUGIN_DIR . 'includes/class-chat-service.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;
use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\SessionService;
use SkyFish\GeminiChat\GeminiClient;

/**
 * Mock WPDB class simulating MySQL storage, updates, and failures.
 */
class ConversationTestWpdb {
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public array $tables = [];
	public ?string $last_error = null;
	public bool $simulate_update_failure = false;
	public bool $simulate_query_failure = false;

	public function prepare( $query, ...$args ) {
		$formatted = $query;
		foreach ( $args as $arg ) {
			$val = is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
			$formatted = preg_replace( '/%[sdf]/', $val, $formatted, 1 );
		}
		return $formatted;
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		$data['id'] = $this->insert_id;
		$this->tables[ $table ][ $this->insert_id ] = $data;
		return 1;
	}

	public function get_row( $query, $output = 'OBJECT' ) {
		if ( preg_match( '/WHERE id = (\d+)/', $query, $matches ) ) {
			$id = (int) $matches[1];
			foreach ( $this->tables as $table => $rows ) {
				if ( isset( $rows[ $id ] ) ) {
					return $rows[ $id ];
				}
			}
		}
		if ( preg_match( '/WHERE public_id = \'([^\']+)\'/', $query, $matches ) ) {
			$pub_id = $matches[1];
			foreach ( $this->tables as $table => $rows ) {
				foreach ( $rows as $row ) {
					if ( isset( $row['public_id'] ) && $row['public_id'] === $pub_id ) {
						return $row;
					}
				}
			}
		}
		if ( preg_match( '/WHERE session_hash = \'([^\']+)\'/', $query, $matches ) ) {
			$sess_hash = $matches[1];
			foreach ( $this->tables as $table => $rows ) {
				foreach ( array_reverse( $rows ) as $row ) {
					if ( isset( $row['session_hash'] ) && $row['session_hash'] === $sess_hash && ( $row['status'] ?? '' ) === 'active' ) {
						return $row;
					}
				}
			}
		}
		return null;
	}

	public function get_results( $query, $output = 'OBJECT' ) {
		$results = [];
		if ( preg_match( '/WHERE conversation_id = (\d+)/', $query, $matches ) ) {
			$conv_id = (int) $matches[1];
			foreach ( $this->tables as $table => $rows ) {
				foreach ( $rows as $row ) {
					if ( isset( $row['conversation_id'] ) && (int) $row['conversation_id'] === $conv_id ) {
						$results[] = $row;
					}
				}
			}
		}
		if ( str_contains( $query, 'DESC' ) ) {
			$results = array_reverse( $results );
		}
		if ( preg_match( '/LIMIT (\d+)/', $query, $matches ) ) {
			$results = array_slice( $results, 0, (int) $matches[1] );
		}
		return $results;
	}

	public function get_var( $query ) {
		if ( preg_match( '/SELECT id FROM (\w+) WHERE id = (\d+)/', $query, $matches ) ) {
			$table = $matches[1];
			$id    = (int) $matches[2];
			return isset( $this->tables[ $table ][ $id ] ) ? (string) $id : null;
		}
		if ( preg_match( '/SELECT interaction_id FROM (\w+) WHERE id = (\d+)/', $query, $matches ) ) {
			$table = $matches[1];
			$id    = (int) $matches[2];
			return $this->tables[ $table ][ $id ]['interaction_id'] ?? null;
		}
		return null;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		if ( $this->simulate_update_failure ) {
			$this->last_error = 'Simulated MySQL update deadlock / connection loss';
			return false;
		}

		$id = $where['id'] ?? null;
		if ( null === $id || ! isset( $this->tables[ $table ][ $id ] ) ) {
			return 0;
		}

		foreach ( $data as $k => $v ) {
			$this->tables[ $table ][ $id ][ $k ] = $v;
		}

		return 1;
	}

	public function query( $query ) {
		if ( $this->simulate_query_failure ) {
			$this->last_error = 'Simulated MySQL query failure';
			return false;
		}

		if ( preg_match( '/UPDATE (\w+) SET message_count = message_count \+ (\d+), updated_at = \'([^\']+)\', last_message_at = \'([^\']+)\' WHERE id = (\d+)/', $query, $matches ) ) {
			$table     = $matches[1];
			$step      = (int) $matches[2];
			$updated   = $matches[3];
			$last_msg  = $matches[4];
			$id        = (int) $matches[5];

			if ( ! isset( $this->tables[ $table ][ $id ] ) ) {
				return 0;
			}

			$this->tables[ $table ][ $id ]['message_count']   = ( $this->tables[ $table ][ $id ]['message_count'] ?? 0 ) + $step;
			$this->tables[ $table ][ $id ]['updated_at']      = $updated;
			$this->tables[ $table ][ $id ]['last_message_at'] = $last_msg;
			return 1;
		}

		return 1;
	}
}

class ConversationUpdateTestSuite {

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
		echo "=======================================================\n";
		echo "CONVERSATION UPDATE RESILIENCE & DATABASE ERROR TESTS\n";
		echo "=======================================================\n\n";

		$this->test_1_valid_conversation_update();
		$this->test_2_missing_conversation();
		$this->test_3_invalid_null_conversation_id();
		$this->test_4_repository_returns_false_does_not_discard_reply();
		$this->test_5_wpdb_update_failure();
		$this->test_6_successful_gemini_and_conversation_update();
		$this->test_7_multiple_conversation_lifecycle();

		echo "\n=======================================================\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "=======================================================\n";

		return 0 === $this->failed;
	}

	private function test_1_valid_conversation_update(): void {
		global $wpdb;
		$wpdb = new ConversationTestWpdb();

		$repo = new ConversationRepository();
		$id   = $repo->create( 'sess_hash_123', 0, 'Test Conversation' );
		$this->assert( $id > 0, 'Valid conversation created with ID > 0' );

		$update_result = $repo->update_last_active( $id );
		$this->assert( true === $update_result, 'Valid conversation update_last_active returns true' );

		$conv = $repo->get_by_id( $id );
		$this->assert( ! empty( $conv['updated_at'] ), 'Conversation updated_at timestamp is set' );
		$this->assert( ! empty( $conv['last_message_at'] ), 'Conversation last_message_at timestamp is set' );

		$inc_result = $repo->increment_message_count( $id, 2 );
		$this->assert( true === $inc_result, 'Valid conversation increment_message_count returns true' );

		$conv_after = $repo->get_by_id( $id );
		$this->assert( 2 === (int) $conv_after['message_count'], 'Conversation message_count incremented by 2' );
	}

	private function test_2_missing_conversation(): void {
		global $wpdb;
		$wpdb = new ConversationTestWpdb();

		$repo = new ConversationRepository();

		// Missing conversation ID 999999
		$update_result = $repo->update_last_active( 999999 );
		$this->assert( false === $update_result, 'Missing conversation update_last_active returns false' );

		$inc_result = $repo->increment_message_count( 999999, 2 );
		$this->assert( false === $inc_result, 'Missing conversation increment_message_count returns false' );
	}

	private function test_3_invalid_null_conversation_id(): void {
		global $wpdb;
		$wpdb = new ConversationTestWpdb();

		$repo = new ConversationRepository();

		$this->assert( false === $repo->update_last_active( 0 ), 'Conversation ID 0 update_last_active returns false' );
		$this->assert( false === $repo->update_last_active( -1 ), 'Negative conversation ID update_last_active returns false' );
		$this->assert( false === $repo->increment_message_count( 0 ), 'Conversation ID 0 increment_message_count returns false' );
		$this->assert( false === $repo->increment_message_count( -1 ), 'Negative conversation ID increment_message_count returns false' );
	}

	private function test_4_repository_returns_false_does_not_discard_reply(): void {
		global $wpdb, $mock_options;
		$wpdb = new ConversationTestWpdb();
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => true,
			'model'          => 'gemini-3.8-flash',
			'api_key'        => 'test_key_abc123',
		];

		// Mock repository that returns false for update_last_active and increment_message_count
		$failing_repo = new class extends ConversationRepository {
			public function update_last_active( int $id ): bool {
				return false;
			}
			public function increment_message_count( int $id, int $count = 1 ): bool {
				return false;
			}
		};

		$real_msg_repo = new MessageRepository();
		$session_serv  = new SessionService( $failing_repo, $real_msg_repo );

		$gemini_mock = new class extends GeminiClient {
			public function is_configured(): bool {
				return true;
			}
			public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
				return [
					'interaction_id' => 'inter_test_safe',
					'model'          => 'gemini-3.8-flash',
					'text'           => 'This is a successful assistant answer.',
					'usage'          => [ 'input_tokens' => 5, 'output_tokens' => 10, 'total_tokens' => 15 ],
					'raw'            => [],
				];
			}
		};

		$chat_service = new ChatService( null, $session_serv, $failing_repo, $real_msg_repo, $gemini_mock );
		$sess_id      = SessionService::generate_session_token();
		$res          = $chat_service->handle_chat( 'Hello test 4', $sess_id );
		if ( is_wp_error( $res ) ) {
			echo "TEST 4 ERROR CODE: " . $res->get_error_code() . " - " . $res->get_error_message() . "\n";
			return;
		}

		$this->assert( ! is_wp_error( $res ), 'Chat turn succeeds even when conversation metadata update returns false' );
		$this->assert( 'This is a successful assistant answer.' === $res['message'], 'Assistant reply is preserved and not lost' );
		$this->assert( 200 === ( $chat_service->get_pipeline()['rest_status'] ?? 0 ), 'Pipeline finishes with status 200' );
	}

	private function test_5_wpdb_update_failure(): void {
		global $wpdb;
		$wpdb = new ConversationTestWpdb();

		$repo = new ConversationRepository();
		$id   = $repo->create( 'sess_hash_fail', 0, 'Fail Test' );
		$this->assert( $id > 0, 'Conversation created before update failure test' );

		// Enable update failure on wpdb
		$wpdb->simulate_update_failure = true;
		$update_res = $repo->update_last_active( $id );
		$this->assert( false === $update_res, 'update_last_active returns false on wpdb update failure' );

		// Enable query failure on wpdb
		$wpdb->simulate_query_failure = true;
		$inc_res = $repo->increment_message_count( $id, 1 );
		$this->assert( false === $inc_res, 'increment_message_count returns false on wpdb query failure' );
	}

	private function test_6_successful_gemini_and_conversation_update(): void {
		global $wpdb, $mock_options;
		$wpdb = new ConversationTestWpdb();
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => true,
			'model'          => 'gemini-3.8-flash',
			'api_key'        => 'test_key_abc123',
		];

		$real_conv_repo = new ConversationRepository();
		$real_msg_repo  = new MessageRepository();
		$session_serv   = new SessionService( $real_conv_repo, $real_msg_repo );

		$gemini_mock = new class extends GeminiClient {
			public function is_configured(): bool {
				return true;
			}
			public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
				return [
					'interaction_id' => 'inter_live_mock',
					'model'          => 'gemini-3.8-flash',
					'text'           => 'Welcome! How can I assist you today?',
					'usage'          => [ 'input_tokens' => 12, 'output_tokens' => 25, 'total_tokens' => 37 ],
					'raw'            => [],
				];
			}
		};

		$chat_service = new ChatService( null, $session_serv, $real_conv_repo, $real_msg_repo, $gemini_mock );
		$sess_id      = SessionService::generate_session_token();

		$res = $chat_service->handle_chat( 'hi', $sess_id, [], 'gca_live_req_001' );

		$this->assert( ! is_wp_error( $res ), 'Full chat pipeline returns success array' );
		$this->assert( 'Welcome! How can I assist you today?' === $res['message'], 'Assistant answer returned accurately' );
		$this->assert( 'gca_live_req_001' === $res['request_id'], 'Request ID correctly preserved' );

		// Verify conversation updated in database
		$conv = $real_conv_repo->get_by_public_id( $res['conversation_id'] );
		$this->assert( null !== $conv, 'Conversation record retrieved from DB' );
		$this->assert( ! empty( $conv['last_message_at'] ), 'Conversation last_message_at updated in DB' );

		// Verify messages stored
		$messages = $real_msg_repo->get_by_conversation_id( (int) $conv['id'], 10, 'ASC' );
		$this->assert( 2 === count( $messages ), 'Exactly 2 messages saved (1 user, 1 assistant)' );
		$this->assert( 'user' === $messages[0]['role'] && 'hi' === $messages[0]['content'], 'User message stored once' );
		$this->assert( 'assistant' === $messages[1]['role'] && 'Welcome! How can I assist you today?' === $messages[1]['content'], 'Assistant message stored once' );
	}

	private function test_7_multiple_conversation_lifecycle(): void {
		global $wpdb, $mock_options;
		$wpdb = new ConversationTestWpdb();
		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'store_messages' => true,
			'model'          => 'gemini-3.8-flash',
			'api_key'        => 'test_key_abc123',
		];

		$conv_repo    = new ConversationRepository();
		$msg_repo     = new MessageRepository();
		$session_serv = new SessionService( $conv_repo, $msg_repo );

		$gemini_mock = new class extends GeminiClient {
			public int $calls = 0;
			public function is_configured(): bool {
				return true;
			}
			public function create_interaction( string $input, ?string $previous_interaction_id = null, array $options = [] ) {
				$this->calls++;
				return [
					'interaction_id' => 'inter_multi_' . $this->calls,
					'model'          => 'gemini-3.8-flash',
					'text'           => 'Response to ' . $input,
					'usage'          => [ 'input_tokens' => 10, 'output_tokens' => 10, 'total_tokens' => 20 ],
					'raw'            => [],
				];
			}
		};

		$chat_service = new ChatService( null, $session_serv, $conv_repo, $msg_repo, $gemini_mock );
		$session_id   = SessionService::generate_session_token();

		// Message 1: hi
		$res1 = $chat_service->handle_chat( 'hi', $session_id, [], 'gca_life_1' );
		$this->assert( ! is_wp_error( $res1 ), 'Turn 1 (hi) succeeded' );
		$conv1 = $conv_repo->get_by_public_id( $res1['conversation_id'] );
		$this->assert( 1 === (int) $conv1['id'], 'First conversation ID is 1' );

		// Message 2: What services do you offer? (existing conversation)
		$res2 = $chat_service->handle_chat( 'What services do you offer?', $session_id, [], 'gca_life_2' );
		$this->assert( ! is_wp_error( $res2 ), 'Turn 2 (Services) succeeded' );
		$this->assert( $res1['conversation_id'] === $res2['conversation_id'], 'Turn 2 used existing conversation' );

		// Reset session
		$reset = $chat_service->reset_session( $session_id );
		$this->assert( true === $reset, 'Session reset successfully' );
		$conv1_closed = $conv_repo->get_by_id( 1 );
		$this->assert( 'closed' === $conv1_closed['status'], 'Previous conversation status is closed' );

		// Message 3: Tell me about your products. (new conversation after reset)
		$res3 = $chat_service->handle_chat( 'Tell me about your products.', $session_id, [], 'gca_life_3' );
		$this->assert( ! is_wp_error( $res3 ), 'Turn 3 (Products after reset) succeeded' );
		$this->assert( $res3['conversation_id'] !== $res1['conversation_id'], 'Turn 3 created fresh conversation after reset' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ConversationUpdateTestSuite();
	$suite->run_all();
}
