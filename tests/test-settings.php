<?php
/**
 * Standalone Test Suite for Node N2: Admin Settings System.
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

// Mock WordPress functions if not available.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
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
		if ( is_array( $args ) ) {
			return array_merge( $defaults, $args );
		}
		return $defaults;
	}
}

$mock_options = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $mock_options;
		return $mock_options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value ) {
		global $mock_options;
		if ( ! isset( $mock_options[ $option ] ) ) {
			$mock_options[ $option ] = $value;
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		global $mock_options;
		$mock_options[ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain() {
		return true;
	}
}

require_once __DIR__ . '/../includes/class-activator.php';
require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Admin/AdminMenu.php';

use SkyFish\GeminiChat\Activator;
use SkyFish\GeminiChat\Admin\AdminMenu;
use SkyFish\GeminiChat\Admin\SettingsService;

class SettingsSystemTest {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	public function run_all(): bool {
		echo "====================================================\n";
		echo "Running Node N2: Admin Settings System Test Suite\n";
		echo "====================================================\n\n";

		$this->test_1_default_settings_schema();
		$this->test_2_default_model_is_gemini_3_7();
		$this->test_3_sanitization_and_clamping();
		$this->test_4_credential_detection();
		$this->test_5_no_api_key_in_settings();

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

	private function test_1_default_settings_schema(): void {
		$defaults = Activator::get_default_settings();

		$required_keys = [
			'enabled', 'assistant_name', 'greeting', 'welcome_message', 'placeholder',
			'model', 'system_instruction',
			'widget_enabled', 'embedded_chat_enabled', 'desktop_enabled', 'mobile_enabled',
			'prechat_enabled', 'collect_name', 'require_name', 'collect_email', 'require_email',
			'collect_phone', 'require_phone', 'collect_requirement', 'require_requirement',
			'faq_enabled', 'faq_show_home',
			'guest_access',
			'max_message_length', 'rate_limit_5m', 'rate_limit_1h',
			'store_messages', 'store_leads', 'retention_days'
		];

		$all_present = true;
		foreach ( $required_keys as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				$all_present = false;
				break;
			}
		}

		$this->assert( $all_present, 'Test 1: Default settings include all N2 configuration categories' );
	}

	private function test_2_default_model_is_gemini_3_8(): void {
		$defaults = Activator::get_default_settings();
		$this->assert( $defaults['model'] === 'gemini-3.8-flash', 'Test 2.1: Default model is gemini-3.8-flash' );
		$this->assert( SettingsService::get_model() === 'gemini-3.8-flash', 'Test 2.2: SettingsService returns gemini-3.8-flash' );
	}

	private function test_3_sanitization_and_clamping(): void {
		$input = [
			'enabled'            => '1',
			'assistant_name'     => '<script>alert(1)</script>Safe Bot',
			'model'              => '  custom-gemini-model  ',
			'max_message_length' => '9999999', // out of bounds -> clamped
			'rate_limit_5m'      => '0',       // out of bounds -> clamped
			'rate_limit_1h'      => '250',
			'retention_days'     => '60',
		];

		$sanitized = SettingsService::sanitize_settings( $input );

		$this->assert( $sanitized['assistant_name'] === 'alert(1)Safe Bot', 'Test 3.1: Assistant name sanitized' );
		$this->assert( $sanitized['model'] === 'custom-gemini-model', 'Test 3.2: Model slug trimmed and sanitized' );
		$this->assert( $sanitized['max_message_length'] === 2000, 'Test 3.3: Excessive message length clamped to default' );
		$this->assert( $sanitized['rate_limit_5m'] === 15, 'Test 3.4: 0 rate limit clamped to default' );
		$this->assert( $sanitized['rate_limit_1h'] === 250, 'Test 3.5: Valid rate limit preserved' );
	}

	private function test_4_credential_detection(): void {
		// Test when unconfigured.
		$is_configured_init = SettingsService::is_api_key_configured();

		// Simulate environment key.
		putenv( 'GEMINI_API_KEY=test_env_key' );
		$is_configured_env = SettingsService::is_api_key_configured();
		$retrieved_key = SettingsService::get_api_key();

		putenv( 'GEMINI_API_KEY=' ); // reset

		$this->assert( $is_configured_env === true, 'Test 4.1: Detected GEMINI_API_KEY from environment' );
		$this->assert( $retrieved_key === 'test_env_key', 'Test 4.2: Successfully retrieved server-side key' );
	}

	private function test_5_no_api_key_in_settings(): void {
		$defaults = Activator::get_default_settings();
		$this->assert( ! isset( $defaults['api_key'] ), 'Test 5.1: No api_key field in gca_settings defaults' );

		$input = [
			'api_key' => 'secret_key_should_be_ignored',
			'enabled' => '1',
		];

		$sanitized = SettingsService::sanitize_settings( $input );
		$this->assert( ! isset( $sanitized['api_key'] ), 'Test 5.2: Sanitize settings ignores any submitted api_key field' );
	}
}

// Execute tests if invoked directly.
$suite = new SettingsSystemTest();
$suite->run_all();
