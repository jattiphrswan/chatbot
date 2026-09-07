<?php
/**
 * Standalone Test Suite for Admin Conversation Management (Node N11).
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

if ( ! defined( 'GCA_PLUGIN_URL' ) ) {
	define( 'GCA_PLUGIN_URL', 'https://example.com/wp-content/plugins/gemini-chat-assistant/' );
}

if ( ! defined( 'GCA_VERSION' ) ) {
	define( 'GCA_VERSION', '1.0.0' );
}

if ( ! defined( 'GCA_DB_VERSION' ) ) {
	define( 'GCA_DB_VERSION', '1.0.0' );
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

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $val ) {
		return is_string( $val ) ? stripslashes( $val ) : $val;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) {
		return date( $format, $timestamp ?: time() );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $user_id ) {
		if ( 42 === (int) $user_id ) {
			$user = new \stdClass();
			$user->display_name = 'Jane Doe';
			$user->user_login   = 'janedoe';
			$user->user_email   = 'jane@example.com';
			return $user;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test_nonce_' . esc_attr( $action ) . '" />';
		if ( $echo ) {
			echo $field;
		}
		return $field;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return 'test_nonce_' . $action === $nonce;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		global $test_current_user_can_result;
		return isset( $test_current_user_can_result ) ? $test_current_user_can_result : true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = [] ) {
		throw new \RuntimeException( 'wp_die called: ' . $message );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		global $test_last_redirect;
		$test_last_redirect = $location;
		return true;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$parsed = parse_url( $url );
		$query = [];
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}
		if ( is_array( $args ) ) {
			foreach ( $args as $k => $v ) {
				$query[ $k ] = $v;
			}
		}
		$base = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' . $parsed['host'] : '' ) . ( $parsed['path'] ?? '' );
		return $base . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $keys, $url = '' ) {
		$parsed = parse_url( $url );
		$query = [];
		if ( isset( $parsed['query'] ) ) {
			parse_str( $parsed['query'], $query );
		}
		foreach ( (array) $keys as $k ) {
			unset( $query[ $k ] );
		}
		$base = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' . $parsed['host'] : '' ) . ( $parsed['path'] ?? '' );
		return empty( $query ) ? $base : $base . '?' . http_build_query( $query );
	}
}

// Require classes.
require_once __DIR__ . '/../includes/Database/ConversationRepository.php';
require_once __DIR__ . '/../includes/Database/MessageRepository.php';
require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Admin/AdminMenu.php';

use SkyFish\GeminiChat\Database\ConversationRepository;
use SkyFish\GeminiChat\Database\MessageRepository;
use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Admin\SettingsService;

/**
 * Mock WPDB for Conversation Management testing.
 */
class MockWPDBConversations {
	public string $prefix = 'wp_';
	public array $queries = [];
	public array $prepared = [];
	public array $mock_conversations = [];
	public int $mock_count = 0;
	public int $affected_rows = 1;

	public function prepare( $query, ...$args ) {
		if ( is_array( $args[0] ?? null ) && 1 === count( $args ) ) {
			$args = $args[0];
		}
		$formatted = vsprintf( str_replace( [ '%s', '%d', '%f' ], [ "'%s'", '%d', '%f' ], $query ), $args );
		$this->prepared[] = [
			'query' => $query,
			'args'  => $args,
			'sql'   => $formatted,
		];
		return $formatted;
	}

	public function get_results( $query, $output = 'ARRAY_A' ) {
		$this->queries[] = $query;
		return $this->mock_conversations;
	}

	public function get_var( $query ) {
		$this->queries[] = $query;
		return (string) $this->mock_count;
	}

	public function get_row( $query, $output = 'ARRAY_A' ) {
		$this->queries[] = $query;
		return $this->mock_conversations[0] ?? null;
	}

	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$this->queries[] = [ 'type' => 'update', 'table' => $table, 'data' => $data, 'where' => $where ];
		return 1;
	}

	public function delete( $table, $where, $where_format = null ) {
		$this->queries[] = [ 'type' => 'delete', 'table' => $table, 'where' => $where ];
		return 1;
	}

	public function query( $query ) {
		$this->queries[] = $query;
		return 1;
	}

	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
}

/**
 * Test Suite Runner.
 */
class TestConversationsPage {
	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	private function assert( bool $condition, string $message ): void {
		if ( $condition ) {
			$this->passed++;
		} else {
			$this->failed++;
			$this->errors[] = $message;
			echo "FAIL: {$message}\n";
		}
	}

	public function run(): void {
		$this->test_allowed_orderby_whitelist();
		$this->test_admin_list_pagination_and_query_preparation();
		$this->test_admin_list_status_filter();
		$this->test_admin_list_search_query();
		$this->test_admin_count_queries();
		$this->test_update_status_by_public_id();
		$this->test_delete_by_public_id_cascading();
		$this->test_admin_menu_registration();
		$this->test_post_action_authorization_and_nonce();
		$this->test_template_rendering_list_view();
		$this->test_template_rendering_detail_view();

		echo "\n============================================\n";
		echo "Node N11 Conversation Management Test Summary:\n";
		echo "Passed: {$this->passed}, Failed: {$this->failed}\n";
		if ( $this->failed > 0 ) {
			echo "Errors:\n" . implode( "\n", $this->errors ) . "\n";
			exit( 1 );
		}
		echo "ALL N11 CONVERSATION MANAGEMENT TESTS PASSED!\n";
		echo "============================================\n";
	}

	private function test_allowed_orderby_whitelist(): void {
		$this->assert(
			in_array( 'created_at', ConversationRepository::ALLOWED_ORDERBY, true ),
			'ConversationRepository whitelists created_at'
		);
		$this->assert(
			in_array( 'updated_at', ConversationRepository::ALLOWED_ORDERBY, true ),
			'ConversationRepository whitelists updated_at'
		);
		$this->assert(
			in_array( 'last_message_at', ConversationRepository::ALLOWED_ORDERBY, true ),
			'ConversationRepository whitelists last_message_at'
		);
		$this->assert(
			! in_array( 'malicious_column', ConversationRepository::ALLOWED_ORDERBY, true ),
			'ConversationRepository rejects unlisted columns'
		);
	}

	private function test_admin_list_pagination_and_query_preparation(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$repo->get_admin_list( [
			'page'     => 2,
			'per_page' => 20,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		] );

		$this->assert( ! empty( $mock_db->prepared ), 'get_admin_list prepares SQL statement' );
		$last_prep = end( $mock_db->prepared );
		$this->assert(
			strpos( $last_prep['query'], 'LIMIT %d OFFSET %d' ) !== false,
			'Query includes LIMIT %d OFFSET %d placeholders'
		);
		$this->assert(
			20 === $last_prep['args'][ count( $last_prep['args'] ) - 2 ] &&
			20 === $last_prep['args'][ count( $last_prep['args'] ) - 1 ],
			'Pagination offset calculated correctly for page 2 (LIMIT 20 OFFSET 20)'
		);
	}

	private function test_admin_list_status_filter(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$repo->get_admin_list( [ 'status' => 'active' ] );

		$last_prep = end( $mock_db->prepared );
		$this->assert(
			strpos( $last_prep['query'], 'status = %s' ) !== false,
			'Status filter query binds status placeholder'
		);
		$this->assert(
			in_array( 'active', $last_prep['args'], true ),
			'Status argument contains active'
		);
	}

	private function test_admin_list_search_query(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$repo->get_admin_list( [ 'search' => 'user_100%_test' ] );

		$last_prep = end( $mock_db->prepared );
		$this->assert(
			strpos( $last_prep['query'], 'public_id LIKE %s' ) !== false,
			'Search filter query searches public_id and title'
		);
	}

	private function test_admin_count_queries(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$mock_db->mock_count = 42;
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$count = $repo->count_admin_list( [ 'status' => 'closed' ] );
		$this->assert( 42 === $count, 'count_admin_list returns count matching filter' );

		$all_count = $repo->count_all();
		$this->assert( 42 === $all_count, 'count_all returns total table rows' );
	}

	private function test_update_status_by_public_id(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$updated = $repo->update_status_by_public_id( '550e8400-e29b-41d4-a716-446655440000', 'closed' );
		$this->assert( true === $updated, 'update_status_by_public_id executes update' );

		$invalid = $repo->update_status_by_public_id( '550e8400-e29b-41d4-a716-446655440000', 'invalid_status' );
		$this->assert( false === $invalid, 'update_status_by_public_id rejects unwhitelisted status' );
	}

	private function test_delete_by_public_id_cascading(): void {
		global $wpdb;
		$mock_db = new MockWPDBConversations();
		$mock_db->mock_conversations = [
			[
				'id'        => 15,
				'public_id' => '550e8400-e29b-41d4-a716-446655440000',
			],
		];
		$wpdb = $mock_db;

		$repo = new ConversationRepository();
		$deleted = $repo->delete_by_public_id( '550e8400-e29b-41d4-a716-446655440000' );

		$this->assert( true === $deleted, 'delete_by_public_id returns true on successful cascade' );

		$deleted_tables = array_column(
			array_filter( $mock_db->queries, fn( $q ) => is_array( $q ) && 'delete' === ( $q['type'] ?? '' ) ),
			'table'
		);
		$this->assert(
			in_array( 'wp_gca_messages', $deleted_tables, true ),
			'delete_by_public_id cascades deletion to wp_gca_messages'
		);
		$this->assert(
			in_array( 'wp_gca_conversations', $deleted_tables, true ),
			'delete_by_public_id deletes from wp_gca_conversations'
		);
	}

	private function test_admin_menu_registration(): void {
		$settings_svc = new SettingsService();
		$menu = new AdminMenu( $settings_svc );

		$this->assert( method_exists( $menu, 'render_conversations_page' ), 'AdminMenu has render_conversations_page method' );
		$this->assert( method_exists( $menu, 'handle_close_conversation' ), 'AdminMenu has handle_close_conversation method' );
		$this->assert( method_exists( $menu, 'handle_reopen_conversation' ), 'AdminMenu has handle_reopen_conversation method' );
		$this->assert( method_exists( $menu, 'handle_delete_conversation' ), 'AdminMenu has handle_delete_conversation method' );
	}

	private function test_post_action_authorization_and_nonce(): void {
		global $test_current_user_can_result, $test_last_redirect, $wpdb;
		$mock_db = new MockWPDBConversations();
		$wpdb = $mock_db;

		$settings_svc = new SettingsService();
		$menu = new AdminMenu( $settings_svc );

		// 1. Unauthorized user test.
		$test_current_user_can_result = false;
		$_POST = [
			'conversation_id' => '550e8400-e29b-41d4-a716-446655440000',
			'_wpnonce'        => 'test_nonce_gca_close_conversation_550e8400-e29b-41d4-a716-446655440000',
		];
		$exception_thrown = false;
		try {
			$menu->handle_close_conversation();
		} catch ( \RuntimeException $e ) {
			$exception_thrown = true;
		}
		$this->assert( $exception_thrown, 'Unauthorized POST action dies with wp_die' );

		// 2. Invalid nonce test.
		$test_current_user_can_result = true;
		$_POST['_wpnonce'] = 'invalid_nonce';
		$exception_thrown = false;
		try {
			$menu->handle_close_conversation();
		} catch ( \RuntimeException $e ) {
			$exception_thrown = true;
		}
		$this->assert( $exception_thrown, 'Invalid nonce dies with wp_die' );

		// 3. Valid close conversation test.
		$_POST['_wpnonce'] = 'test_nonce_gca_close_conversation_550e8400-e29b-41d4-a716-446655440000';
		$test_last_redirect = null;
		$menu->handle_close_conversation();
		$this->assert(
			strpos( (string) $test_last_redirect, 'closed=1' ) !== false,
			'handle_close_conversation redirects with closed=1 flag'
		);

		// 4. Valid reopen conversation test.
		$_POST['_wpnonce'] = 'test_nonce_gca_reopen_conversation_550e8400-e29b-41d4-a716-446655440000';
		$test_last_redirect = null;
		$menu->handle_reopen_conversation();
		$this->assert(
			strpos( (string) $test_last_redirect, 'reopened=1' ) !== false,
			'handle_reopen_conversation redirects with reopened=1 flag'
		);

		// 5. Valid delete conversation test.
		$_POST['_wpnonce'] = 'test_nonce_gca_delete_conversation_550e8400-e29b-41d4-a716-446655440000';
		$test_last_redirect = null;
		$menu->handle_delete_conversation();
		$this->assert(
			strpos( (string) $test_last_redirect, 'deleted=1' ) !== false,
			'handle_delete_conversation redirects with deleted=1 flag'
		);
	}

	private function test_template_rendering_list_view(): void {
		$conversations = [
			[
				'public_id'       => '550e8400-e29b-41d4-a716-446655440000',
				'user_id'         => 42,
				'status'          => 'active',
				'message_count'   => 4,
				'created_at'      => '2026-09-07 10:00:00',
				'updated_at'      => '2026-09-07 10:05:00',
				'last_message_at' => '2026-09-07 10:05:00',
				'title'           => 'Product Pricing Query',
			],
		];
		$total_items    = 1;
		$current_page   = 1;
		$per_page       = 20;
		$total_pages    = 1;
		$current_status = 'all';
		$search_term    = '';
		$base_url       = admin_url( 'admin.php?page=gca-conversations' );

		ob_start();
		include __DIR__ . '/../templates/admin/conversations.php';
		$output = ob_get_clean();

		$this->assert( strpos( $output, 'Conversations Management' ) !== false, 'List view renders page title' );
		$this->assert( strpos( $output, '550e8400' ) !== false, 'List view renders conversation public_id prefix' );
		$this->assert( strpos( $output, 'Jane Doe (janedoe)' ) !== false, 'List view renders user info' );
		$this->assert( strpos( $output, 'Product Pricing Query' ) !== false, 'List view renders conversation title' );
		$this->assert( strpos( $output, 'gca_delete_conversation' ) !== false, 'List view includes delete action form' );
	}

	private function test_template_rendering_detail_view(): void {
		$conversation = [
			'public_id'     => '550e8400-e29b-41d4-a716-446655440000',
			'user_id'       => 42,
			'status'        => 'active',
			'message_count' => 2,
			'created_at'    => '2026-09-07 10:00:00',
			'updated_at'    => '2026-09-07 10:05:00',
		];
		$messages = [
			[
				'role'       => 'user',
				'content'    => 'Hello <script>alert(1)</script>!',
				'created_at' => '2026-09-07 10:00:00',
				'model'      => '',
			],
			[
				'role'       => 'assistant',
				'content'    => 'Hello! How can I assist you today?',
				'created_at' => '2026-09-07 10:00:02',
				'model'      => 'gemini-3.8-flash',
			],
		];
		$back_url = admin_url( 'admin.php?page=gca-conversations' );

		ob_start();
		include __DIR__ . '/../templates/admin/conversation-detail.php';
		$output = ob_get_clean();

		$this->assert( strpos( $output, '550e8400-e29b-41d4-a716-446655440000' ) !== false, 'Detail view renders full UUID' );
		$this->assert( strpos( $output, 'Jane Doe (janedoe' ) !== false, 'Detail view renders user info' );
		$this->assert( strpos( $output, 'gemini-3.8-flash' ) !== false, 'Detail view renders model badge' );
		$this->assert( strpos( $output, 'Hello &lt;script&gt;alert(1)&lt;/script&gt;!' ) !== false, 'Detail view escapes message HTML/XSS content' );
		$this->assert( strpos( $output, '<script>alert(1)</script>' ) === false, 'Raw script tag is NOT rendered unescaped' );
		$this->assert( strpos( $output, 'gca_close_conversation' ) !== false, 'Detail view includes close conversation action' );
	}
}

$suite = new TestConversationsPage();
$suite->run();
