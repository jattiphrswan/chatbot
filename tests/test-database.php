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
	public array $tables_created = [];
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
		$this->test_3_session_id_generation_and_validation();
		$this->test_4_ip_hashing_security();
		$this->test_5_session_transient_cache();
		$this->test_6_conversation_and_message_crud();
		$this->test_7_no_n4_or_unapproved_tables();

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

	private function test_3_session_id_generation_and_validation(): void {
		$session_id = SessionService::generate_session_id();
		$is_valid   = SessionService::is_valid_session_id( $session_id );

		$this->assert( str_starts_with( $session_id, 'gca_sess_' ), 'Test 3.1: Session ID has gca_sess_ prefix' );
		$this->assert( $is_valid === true, 'Test 3.2: Generated session ID passes regex validation' );
		$this->assert( SessionService::is_valid_session_id( 'invalid-id' ) === false, 'Test 3.3: Invalid session ID rejected' );
	}

	private function test_4_ip_hashing_security(): void {
		$ip1   = '192.168.1.100';
		$ip2   = '192.168.1.101';
		$hash1 = SessionService::hash_ip( $ip1 );
		$hash2 = SessionService::hash_ip( $ip2 );

		$this->assert( strlen( $hash1 ) === 64, 'Test 4.1: IP hash produces 64-character SHA-256 HMAC' );
		$this->assert( $hash1 !== $hash2, 'Test 4.2: Distinct IPs produce distinct hashes' );
		$this->assert( ! str_contains( $hash1, $ip1 ), 'Test 4.3: Raw IP is not visible in hash' );
	}

	private function test_5_session_transient_cache(): void {
		$session_service = new SessionService();
		$session_id      = SessionService::generate_session_id();

		$cached = $session_service->set_session_cache( $session_id, [ 'turn_count' => 3 ] );
		$this->assert( $cached === true, 'Test 5.1: Session transient cache saved' );

		$data = $session_service->get_session_cache( $session_id );
		$this->assert( isset( $data['turn_count'] ) && $data['turn_count'] === 3, 'Test 5.2: Session transient retrieved correctly' );

		$session_service->reset_session( $session_id );
		$cleared = $session_service->get_session_cache( $session_id );
		$this->assert( $cleared === null, 'Test 5.3: Session transient cleared on reset' );
	}

	private function test_6_conversation_and_message_crud(): void {
		$conv_repo = new ConversationRepository();
		$msg_repo  = new MessageRepository();

		$session_id = SessionService::generate_session_id();
		$conv_id    = $conv_repo->create( $session_id, 1, 'hash_abc', [ 'source' => 'web' ] );

		$this->assert( $conv_id > 0, 'Test 6.1: Conversation record created' );

		$msg_id = $msg_repo->create( $conv_id, 'user', 'Hello there!', 5 );
		$this->assert( $msg_id > 0, 'Test 6.2: Message record created' );
	}

	private function test_7_no_n4_or_unapproved_tables(): void {
		// Confirm zero client classes or unapproved table classes exist in repository.
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\Services\GeminiClient' ), 'Test 7.1: GeminiClient does not exist in N3' );
		$this->assert( ! class_exists( 'SkyFish\GeminiChat\REST\ChatController' ), 'Test 7.2: ChatController does not exist in N3' );
	}
}

// Execute tests if invoked directly.
$suite = new DatabaseSystemTest();
$suite->run_all();
