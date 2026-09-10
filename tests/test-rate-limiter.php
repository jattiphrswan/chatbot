<?php
/**
 * Standalone Test Suite for RateLimiter & Abuse Prevention (Node N8).
 *
 * @package SkyFish\GeminiChat\Tests
 */

namespace SkyFish\GeminiChat\Tests;

require_once __DIR__ . '/bootstrap.php';

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

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'test_salt_secret_key_12345';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
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

// Mock WordPress Transient cache.
global $mock_transients;
$mock_transients = [];

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $mock_transients;
		if ( isset( $mock_transients[ $transient ] ) ) {
			$item = $mock_transients[ $transient ];
			if ( isset( $item['ttl_expire'] ) && time() > $item['ttl_expire'] ) {
				unset( $mock_transients[ $transient ] );
				return false;
			}
			return $item['data'];
		}
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $mock_transients;
		$mock_transients[ $transient ] = [
			'data'       => $value,
			'ttl_expire' => $expiration > 0 ? time() + $expiration : 0,
		];
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		global $mock_transients;
		unset( $mock_transients[ $transient ] );
		return true;
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

require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/class-rate-limiter.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\RateLimiter;

/**
 * Class RateLimiterTest
 */
class RateLimiterTest {

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
		echo "Running Node N8 RateLimiter Tests\n";
		echo "========================================\n\n";

		$this->test_5m_session_limit();
		$this->test_1h_session_limit();
		$this->test_ip_abuse_ceiling_with_rotating_sessions();
		$this->test_disabled_rate_limiter();
		$this->test_retry_after_data();
		$this->test_privacy_hash_safety();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function reset_env(): void {
		global $mock_transients, $mock_options;
		$mock_transients = [];
		$mock_options    = [];
	}

	private function test_5m_session_limit(): void {
		$this->reset_env();
		global $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 3,
			'rate_limit_1h'      => 50,
		];

		$limiter    = new RateLimiter();
		$session_id = 'gca_sess_test_session_1111111111111';
		$client_ip  = '198.51.100.1';

		// First 3 requests should pass
		$res1 = $limiter->check_and_consume( $session_id, $client_ip );
		$res2 = $limiter->check_and_consume( $session_id, $client_ip );
		$res3 = $limiter->check_and_consume( $session_id, $client_ip );

		$this->assert( true === $res1, 'Request 1/3 permitted within 5m window' );
		$this->assert( true === $res2, 'Request 2/3 permitted within 5m window' );
		$this->assert( true === $res3, 'Request 3/3 permitted within 5m window' );

		// 4th request should be rate limited (429)
		$res4 = $limiter->check_and_consume( $session_id, $client_ip );
		$this->assert( is_wp_error( $res4 ), 'Request 4/3 blocked by 5m session limit' );
		if ( is_wp_error( $res4 ) ) {
			$this->assert( 'RATE_LIMITED' === $res4->get_error_code(), 'Error code is RATE_LIMITED' );
			$data = $res4->get_error_data();
			$this->assert( 429 === $data['status'], 'Status code is 429' );
			$this->assert( isset( $data['retry_after'] ) && $data['retry_after'] > 0, 'Retry-After is positive' );
		}
	}

	private function test_1h_session_limit(): void {
		$this->reset_env();
		global $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 10,
			'rate_limit_1h'      => 2,
		];

		$limiter    = new RateLimiter();
		$session_id = 'gca_sess_test_session_2222222222222';
		$client_ip  = '198.51.100.2';

		$res1 = $limiter->check_and_consume( $session_id, $client_ip );
		$res2 = $limiter->check_and_consume( $session_id, $client_ip );
		$res3 = $limiter->check_and_consume( $session_id, $client_ip );

		$this->assert( true === $res1, 'Request 1 permitted within 1h window' );
		$this->assert( true === $res2, 'Request 2 permitted within 1h window' );
		$this->assert( is_wp_error( $res3 ), 'Request 3 blocked by 1h session limit' );
	}

	private function test_ip_abuse_ceiling_with_rotating_sessions(): void {
		$this->reset_env();
		global $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 2,  // IP ceiling = 2 * 3 = 6
			'rate_limit_1h'      => 50,
		];

		$limiter   = new RateLimiter();
		$client_ip = '198.51.100.99';

		// Attacker generates a new session for every request
		for ( $i = 1; $i <= 6; $i++ ) {
			$sess = "gca_sess_attacker_session_{$i}_abcdef123";
			$res  = $limiter->check_and_consume( $sess, $client_ip );
			$this->assert( true === $res, "Rotated session {$i}/6 permitted under IP ceiling" );
		}

		// 7th request from same IP with yet another session token must hit IP ceiling
		$sess7 = 'gca_sess_attacker_session_7_abcdef123';
		$res7  = $limiter->check_and_consume( $sess7, $client_ip );
		$this->assert( is_wp_error( $res7 ), 'Request 7 blocked by IP abuse ceiling despite rotating session tokens' );
	}

	private function test_disabled_rate_limiter(): void {
		$this->reset_env();
		global $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => false,
			'rate_limit_5m'      => 1,
			'rate_limit_1h'      => 1,
		];

		$limiter    = new RateLimiter();
		$session_id = 'gca_sess_test_session_disabled';
		$client_ip  = '198.51.100.5';

		$res1 = $limiter->check_and_consume( $session_id, $client_ip );
		$res2 = $limiter->check_and_consume( $session_id, $client_ip );
		$res3 = $limiter->check_and_consume( $session_id, $client_ip );

		$this->assert( true === $res1 && true === $res2 && true === $res3, 'All requests permitted when rate limiting is disabled' );
	}

	private function test_retry_after_data(): void {
		$this->reset_env();
		global $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 1,
			'rate_limit_1h'      => 10,
		];

		$limiter    = new RateLimiter();
		$session_id = 'gca_sess_test_retry_after';
		$client_ip  = '198.51.100.6';

		$limiter->check_and_consume( $session_id, $client_ip );
		$blocked = $limiter->check_and_consume( $session_id, $client_ip );

		$this->assert( is_wp_error( $blocked ), 'Blocked request is WP_Error' );
		if ( is_wp_error( $blocked ) ) {
			$data = $blocked->get_error_data();
			$this->assert( isset( $data['retry_after'] ) && $data['retry_after'] <= 300 && $data['retry_after'] > 0, 'Retry-After accurately reflects remaining TTL (<= 300s)' );
		}
	}

	private function test_privacy_hash_safety(): void {
		$this->reset_env();
		global $mock_transients, $mock_options;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 5,
			'rate_limit_1h'      => 20,
		];

		$limiter    = new RateLimiter();
		$raw_ip     = '203.0.113.195';
		$session_id = 'gca_sess_raw_ip_test_999999999';

		$limiter->check_and_consume( $session_id, $raw_ip );

		// Check all transient keys stored in memory
		$keys = array_keys( $mock_transients );
		$found_raw_ip = false;
		foreach ( $keys as $k ) {
			if ( false !== strpos( $k, $raw_ip ) ) {
				$found_raw_ip = true;
			}
		}

		$this->assert( false === $found_raw_ip, 'Raw IP address is NEVER stored in transient keys (HMAC hash used)' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new RateLimiterTest();
	$suite->run_all();
}
