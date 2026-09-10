<?php
/**
 * Standalone Test Suite for RestController (Node N5).
 *
 * @package SkyFish\GeminiChat\Tests
 */

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

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		return '550e8400-e29b-41d4-a716-446655440000';
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

// Mock WordPress REST classes and functions.
if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		public const READABLE  = 'GET';
		public const CREATABLE = 'POST';
	}
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	class WP_REST_Controller {
		protected $namespace;
		protected $rest_base;
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $params = [];

		public function __construct( string $method = 'GET', string $route = '' ) {
			$this->params = [];
		}

		public function set_param( string $key, $value ): void {
			$this->params[ $key ] = $value;
		}

		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		public function get_params(): array {
			return $this->params;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public mixed $data;
		public int $status;
		public array $headers = [];

		public function __construct( $data = null, int $status = 200, array $headers = [] ) {
			$this->data    = $data;
			$this->status  = $status;
			$this->headers = $headers;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

global $mock_routes;
$mock_routes = [];

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = [], $override = false ) {
		global $mock_routes;
		$mock_routes[ "{$namespace}{$route}" ] = $args;
		return true;
	}
}

global $mock_is_logged_in, $mock_user_caps;
$mock_is_logged_in = false;
$mock_user_caps    = [];

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		global $mock_is_logged_in;
		return $mock_is_logged_in;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		global $mock_user_caps;
		return ! empty( $mock_user_caps[ $capability ] );
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

require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Database/ConversationRepository.php';
require_once __DIR__ . '/../includes/Database/MessageRepository.php';
require_once __DIR__ . '/../includes/Database/SessionService.php';
require_once __DIR__ . '/../includes/class-gemini-client.php';
require_once __DIR__ . '/../includes/class-rate-limiter.php';
require_once __DIR__ . '/../includes/class-validator.php';
require_once __DIR__ . '/../includes/class-chat-service.php';
require_once __DIR__ . '/../includes/class-rest-controller.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\ChatService;
use SkyFish\GeminiChat\GeminiClient;
use SkyFish\GeminiChat\RestController;

class RestControllerTest {

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
		echo "Running Node N5 RestController Tests\n";
		echo "========================================\n\n";

		$this->test_route_registration();
		$this->test_chat_permission_guest_allowed();
		$this->test_chat_permission_guest_denied();
		$this->test_admin_permission_denied_for_guest();
		$this->test_admin_permission_allowed_for_admin();
		$this->test_handle_chat_valid();
		$this->test_handle_chat_invalid_message();
		$this->test_handle_chat_invalid_session();
		$this->test_handle_chat_rate_limited();
		$this->test_handle_reset();
		$this->test_handle_health();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_route_registration(): void {
		global $mock_routes;
		$controller = new RestController();
		$controller->register_routes();

		$this->assert( isset( $mock_routes['gca/v1/chat'] ), 'Route /gca/v1/chat registered' );
		$this->assert( isset( $mock_routes['gca/v1/reset'] ), 'Route /gca/v1/reset registered' );
		$this->assert( isset( $mock_routes['gca/v1/health'] ), 'Route /gca/v1/health registered' );
	}

	private function test_chat_permission_guest_allowed(): void {
		global $mock_options, $mock_is_logged_in;
		$mock_options['gca_settings'] = [ 'guest_access' => true ];
		$mock_is_logged_in = false;

		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$res        = $controller->check_chat_permissions( $request );

		$this->assert( true === $res, 'Guest chat permitted when guest_access is true' );
	}

	private function test_chat_permission_guest_denied(): void {
		global $mock_options, $mock_is_logged_in;
		$mock_options['gca_settings'] = [ 'guest_access' => false ];
		$mock_is_logged_in = false;

		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$res        = $controller->check_chat_permissions( $request );

		$this->assert( is_wp_error( $res ) && 'ACCESS_DENIED' === $res->get_error_code(), 'Guest chat denied with ACCESS_DENIED when guest_access is false' );
	}

	private function test_admin_permission_denied_for_guest(): void {
		global $mock_user_caps;
		$mock_user_caps = [];

		$controller = new RestController();
		$request    = new WP_REST_Request( 'GET', '/gca/v1/health' );
		$res        = $controller->check_admin_permissions( $request );

		$this->assert( is_wp_error( $res ) && 'ACCESS_DENIED' === $res->get_error_code(), 'Non-admin denied from /health' );
	}

	private function test_admin_permission_allowed_for_admin(): void {
		global $mock_user_caps;
		$mock_user_caps = [ 'manage_options' => true ];

		$controller = new RestController();
		$request    = new WP_REST_Request( 'GET', '/gca/v1/health' );
		$res        = $controller->check_admin_permissions( $request );

		$this->assert( true === $res, 'Admin permitted to access /health' );
	}

	private function test_handle_chat_valid(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'            => true,
			'max_message_length' => 1000,
		];

		// Mock chat service
		$chat_service = new class extends ChatService {
			public function handle_chat( string $message, string $session_id, array $context = [], ?string $request_id = null, ?string $requested_provider = null, ?string $requested_model = null ) {
				return [
					'message'         => 'Hello there! I am the Gemini assistant.',
					'conversation_id' => '550e8400-e29b-41d4-a716-446655440000',
					'request_id'      => $request_id,
					'meta'            => [ 'model' => 'gemini-3.8-flash' ],
				];
			}
		};

		$controller = new RestController( null, $chat_service );
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$request->set_param( 'message', 'Hello bot' );
		$request->set_param( 'session_id', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$res = $controller->handle_chat( $request );
		$this->assert( 200 === $res->get_status(), 'Valid chat request returns 200' );
		$data = $res->get_data();
		$this->assert( true === $data['success'], 'Response success flag is true' );
		$this->assert( 'Hello there! I am the Gemini assistant.' === $data['data']['message'], 'Response assistant text matched' );
		$this->assert( '550e8400-e29b-41d4-a716-446655440000' === $data['data']['conversation_id'], 'Public conversation UUID returned' );
	}

	private function test_handle_chat_invalid_message(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$request->set_param( 'message', '   ' );
		$request->set_param( 'session_id', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$res = $controller->handle_chat( $request );
		$this->assert( 400 === $res->get_status(), 'Empty message returns 400 Bad Request' );
		$data = $res->get_data();
		$this->assert( false === $data['success'], 'Success flag is false' );
		$this->assert( 'INVALID_INPUT' === $data['error']['code'], 'Error code is INVALID_INPUT' );
	}

	private function test_handle_chat_invalid_session(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$request->set_param( 'message', 'Valid message' );
		$request->set_param( 'session_id', 'short' );

		$res = $controller->handle_chat( $request );
		$this->assert( 400 === $res->get_status(), 'Invalid session returns 400 Bad Request' );
		$data = $res->get_data();
		$this->assert( 'SESSION_INVALID' === $data['error']['code'], 'Error code is SESSION_INVALID' );
	}

	private function test_handle_chat_rate_limited(): void {
		global $mock_options, $mock_transients;
		$mock_options['gca_settings'] = [
			'rate_limit_enabled' => true,
			'rate_limit_5m'      => 1,
			'rate_limit_1h'      => 10,
		];
		$mock_transients = [];

		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/chat' );
		$request->set_param( 'message', 'First query' );
		$request->set_param( 'session_id', 'gca_sess_rate_limit_test_123456789' );

		// 1st request passes
		$controller->handle_chat( $request );

		// 2nd request gets 429'd
		$res = $controller->handle_chat( $request );
		$this->assert( 429 === $res->get_status(), 'Rate limited request returns 429' );
		$data = $res->get_data();
		$this->assert( false === $data['success'], 'Rate limit response success is false' );
		$this->assert( 'RATE_LIMITED' === $data['error']['code'], 'Rate limit error code is RATE_LIMITED' );
		$this->assert( isset( $data['error']['retry_after'] ) && $data['error']['retry_after'] > 0, 'retry_after is present in error payload' );
	}

	private function test_handle_reset(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request( 'POST', '/gca/v1/reset' );
		$request->set_param( 'session_id', 'gca_sess_0123456789abcdef0123456789abcdef' );

		$res = $controller->handle_reset( $request );
		$this->assert( 200 === $res->get_status(), 'Valid reset returns 200' );
		$data = $res->get_data();
		$this->assert( true === $data['success'], 'Reset success is true' );
		$this->assert( 'reset_successful' === $data['data']['status'], 'Status is reset_successful' );
	}

	private function test_handle_health(): void {
		$controller = new RestController();
		$request    = new WP_REST_Request( 'GET', '/gca/v1/health' );

		$res = $controller->handle_health( $request );
		$this->assert( 200 === $res->get_status(), 'Health check returns 200' );
		$data = $res->get_data();
		$this->assert( 'healthy' === $data['data']['status'], 'Health status is healthy' );
		$this->assert( isset( $data['data']['plugin_version'] ), 'Plugin version present' );
		$this->assert( ! isset( $data['data']['api_key'] ), 'API key never present in health payload' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new RestControllerTest();
	$suite->run_all();
}
