<?php
/**
 * Standalone Test Suite for Node N3: Data & Session Foundation.
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

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// Mock WordPress functions.
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

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
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

// Mock wpdb.
class MockWpdb {
	public string $prefix = 'custom_prefix_';
	public int $insert_id = 0;
	public array $rows = [];

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
	}

	public function prepare( $query, ...$args ) {
		return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
	}

	public function insert( $table, $data, $format = null ) {
		$this->insert_id++;
		$data['id'] = $this->insert_id;
		$this->rows[ $table ][ $this->insert_id ] = $data;
		return 1;
	}

	public function get_row( $query, $output = 'OBJECT' ) {
		return null;
	}

	public function get_results( $query, $output = 'OBJECT' ) {
		return [];
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		return 1;
	}

	public function delete( $table, $where, $where_format = null ) {
		return 1;
	}

	public function query( $query ) {
		return 1;
	}
}

global $wpdb;
$wpdb = new MockWpdb();

require_once __DIR__ . '/../includes/Database/Migrator.php';
require_once __DIR__ . '/../includes/Database/ConversationRepository.php';
require_once __DIR__ . '/../includes/Database/MessageRepository.php';
require_once __DIR__ . '/../includes/Database/SessionService.php';

use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Database\Migrator;
use SkyFish\GeminiChat\Database\SessionService;

class DatabaseSystemTest {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	public function run_all(): bool {
		echo "====================================================\n";
		echo "Running Node N3: Data & Session Foundation Test Suite\n";
		echo "====================================================\n\n";

		$this->test_1_schema_version_constant();
		$this->test_2_dynamic_table_prefixing();
		$this->test_3_session_token_and_hashing();
		$this->test_4_public_uuid_generation();
		$this->test_5_role_validation();
		$this->test_6_session_transient_cache();
		$this->test_7_conversation_and_message_crud();
		$this->test_8_no_n4_or_unapproved_tables();

		echo "\n----------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "----------------------------------------------------\n";

		return $this->failed === 0;
	}

	private function assert( bool $condition, string $test_name ): void {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] $test_name\n";
		} else {
			$this->failed++;
			$this->errors[] = $test_name;
			echo "[FAIL] $test_name\n";
		}
	}

	private function test_1_schema_version_constant(): void {
		$this->assert( Migrator::SCHEMA_VERSION === '1.0.0', 'Test 1.1: Migrator target schema version is 1.0.0' );
		$this->assert( Migrator::VERSION_OPTION === 'gca_db_version', 'Test 1.2: Migrator version option key is gca_db_version' );
	}

	private function test_2_dynamic_table_prefixing(): void {
		global $wpdb;
		$wpdb->prefix = 'testwp_';

		$conv_table = ConversationRepository::get_table_name();
		$msg_table  = MessageRepository::get_table_name();

		$this->assert( $conv_table === 'testwp_gca_conversations', 'Test 2.1: Conversation table respects custom dynamic prefix' );
		$this->assert( $msg_table === 'testwp_gca_messages', 'Test 2.2: Message table respects custom dynamic prefix' );
	}

	private function test_3_session_token_and_hashing(): void {
		$token = SessionService::generate_session_token();
		$this->assert( str_starts_with( $token, 'gca_sess_' ), 'Test 3.1: Session token has gca_sess_ prefix' );
		$this->assert( SessionService::is_valid_session_token( $token ) === true, 'Test 3.2: Generated session token passes validation' );

		$hash = SessionService::hash_session_token( $token );
		$this->assert( strlen( $hash ) === 64, 'Test 3.3: Session token hash is 64-char SHA-256 HMAC' );
		$this->assert( $hash !== $token, 'Test 3.4: Raw session token is not stored plain in hash' );
	}

	private function test_4_public_uuid_generation(): void {
		$uuid1 = ConversationRepository::generate_public_id();
		$uuid2 = ConversationRepository::generate_public_id();

		$this->assert( ! empty( $uuid1 ) && is_string( $uuid1 ), 'Test 4.1: Public UUID is non-empty string' );
		$this->assert( $uuid1 !== $uuid2, 'Test 4.2: Public UUIDs are unique' );
	}

	private function test_5_role_validation(): void {
		$repo   = new MessageRepository();
		$caught = false;
		try {
			$repo->create( 1, 'invalid_role_attacker', 'Hello' );
		} catch ( \InvalidArgumentException $e ) {
			$caught = true;
		}
		$this->assert( $caught, 'Test 5: Arbitrary message role rejected with InvalidArgumentException' );
	}

	private function test_6_session_transient_cache(): void {
		$session_service = new SessionService();
		$token           = SessionService::generate_session_token();

		$cached = $session_service->set_session_cache( $token, [ 'turn_count' => 3 ] );
		$this->assert( $cached === true, 'Test 6.1: Session transient cache saved' );

		$data = $session_service->get_session_cache( $token );
		$this->assert( isset( $data['turn_count'] ) && $data['turn_count'] === 3, 'Test 6.2: Session transient retrieved correctly' );

		$session_service->reset_session( $token );
		$cleared = $session_service->get_session_cache( $token );
		$this->assert( $cleared === null, 'Test 6.3: Session transient cleared on reset' );
	}

	private function test_7_conversation_and_message_crud(): void {
		$conv_repo = new ConversationRepository();
		$msg_repo  = new MessageRepository();

		$session_hash = SessionService::hash_session_token( 'test_token' );
		$conv_id      = $conv_repo->create( $session_hash, 1, 'Support Session' );

		$this->assert( $conv_id > 0, 'Test 7.1: Conversation record created' );

		$msg_id = $msg_repo->create( $conv_id, 'user', 'Hello there!', 'gemini-3.8-flash', 10, 20, 150 );
		$this->assert( $msg_id > 0, 'Test 7.2: Message record created with metrics' );
	}

	private function test_8_no_n4_or_unapproved_tables(): void {
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Services\GeminiClient' ), 'Test 8.1: GeminiClient does not exist in N3' );
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\REST\ChatController' ), 'Test 8.2: ChatController does not exist in N3' );
	}
}

// Execute tests if invoked directly.
$suite = new DatabaseSystemTest();
$suite->run_all();
