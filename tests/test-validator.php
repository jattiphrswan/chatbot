<?php
/**
 * Standalone Test Suite for Validator (Node N5).
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

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
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

require_once __DIR__ . '/../includes/class-validator.php';

use SkyFish\GeminiChat\Validator;

class ValidatorTest {

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
		echo "Running Node N5 Validator Tests\n";
		echo "========================================\n\n";

		$this->test_valid_message();
		$this->test_empty_message();
		$this->test_whitespace_message();
		$this->test_overlength_message();
		$this->test_non_string_message();

		$this->test_valid_session_id();
		$this->test_too_short_session_id();
		$this->test_too_long_session_id();
		$this->test_invalid_characters_session_id();

		$this->test_context_sanitization();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_valid_message(): void {
		$msg = 'Hello, can you explain WordPress REST APIs?';
		$res = Validator::validate_message( $msg, 1000 );
		$this->assert( $res === $msg, 'Valid message returns sanitized string' );
	}

	private function test_empty_message(): void {
		$res = Validator::validate_message( '', 1000 );
		$this->assert( is_wp_error( $res ) && 'INVALID_INPUT' === $res->get_error_code(), 'Empty message rejected with INVALID_INPUT' );
	}

	private function test_whitespace_message(): void {
		$res = Validator::validate_message( "   \n\t  ", 1000 );
		$this->assert( is_wp_error( $res ) && 'INVALID_INPUT' === $res->get_error_code(), 'Whitespace-only message rejected' );
	}

	private function test_overlength_message(): void {
		$long_msg = str_repeat( 'A', 1001 );
		$res      = Validator::validate_message( $long_msg, 1000 );
		$this->assert( is_wp_error( $res ) && 'INVALID_INPUT' === $res->get_error_code(), 'Overlength message rejected' );
	}

	private function test_non_string_message(): void {
		$res = Validator::validate_message( [ 'nested' => 'text' ], 1000 );
		$this->assert( is_wp_error( $res ) && 'INVALID_INPUT' === $res->get_error_code(), 'Non-string message rejected' );
	}

	private function test_valid_session_id(): void {
		$sess = 'gca_sess_a1b2c3d4e5f6789012345678abcdef01';
		$res  = Validator::validate_session_id( $sess );
		$this->assert( $res === $sess, 'Valid session token accepted' );

		$uuid = '550e8400-e29b-41d4-a716-446655440000';
		$res_uuid = Validator::validate_session_id( $uuid );
		$this->assert( $res_uuid === $uuid, 'UUID format session token accepted' );
	}

	private function test_too_short_session_id(): void {
		$res = Validator::validate_session_id( 'short' );
		$this->assert( is_wp_error( $res ) && 'SESSION_INVALID' === $res->get_error_code(), 'Short session token rejected' );
	}

	private function test_too_long_session_id(): void {
		$long_sess = str_repeat( 'a', 150 );
		$res       = Validator::validate_session_id( $long_sess );
		$this->assert( is_wp_error( $res ) && 'SESSION_INVALID' === $res->get_error_code(), 'Overlength session token rejected' );
	}

	private function test_invalid_characters_session_id(): void {
		$res = Validator::validate_session_id( 'sess_token<script>alert(1)</script>' );
		$this->assert( is_wp_error( $res ) && 'SESSION_INVALID' === $res->get_error_code(), 'Session token with special chars rejected' );
	}

	private function test_context_sanitization(): void {
		$context = [
			'page_id'    => '42',
			'page_title' => '<b>About Us</b>',
			'page_url'   => 'https://example.com/about?src=bot',
		];
		$sanitized = Validator::validate_context( $context );
		$this->assert( 42 === $sanitized['page_id'], 'Context page_id cast to int' );
		$this->assert( 'About Us' === $sanitized['page_title'], 'Context page_title stripped of tags' );
		$this->assert( 'https://example.com/about?src=bot' === $sanitized['page_url'], 'Context page_url sanitized' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ValidatorTest();
	$suite->run_all();
}
