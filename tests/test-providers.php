<?php
/**
 * Lightweight Standalone Test Suite for AI Provider Abstraction (Node 1).
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

// Mock WordPress functions if not available in CLI environment.
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Mock registration.
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain() {
		return true;
	}
}

// Require classes under test.
require_once __DIR__ . '/../includes/Providers/ProviderInterface.php';
require_once __DIR__ . '/../includes/Providers/ProviderResponse.php';
require_once __DIR__ . '/../includes/Providers/ProviderException.php';
require_once __DIR__ . '/../includes/Providers/ProviderRegistry.php';
require_once __DIR__ . '/../includes/Providers/GeminiProvider.php';
require_once __DIR__ . '/../includes/Providers/OpenAIProvider.php';
require_once __DIR__ . '/../includes/Providers/ClaudeProvider.php';
require_once __DIR__ . '/../includes/class-plugin.php';

use SkyFish\GeminiChat\Plugin;
use SkyFish\GeminiChat\Providers\ClaudeProvider;
use SkyFish\GeminiChat\Providers\GeminiProvider;
use SkyFish\GeminiChat\Providers\OpenAIProvider;
use SkyFish\GeminiChat\Providers\ProviderException;
use SkyFish\GeminiChat\Providers\ProviderInterface;
use SkyFish\GeminiChat\Providers\ProviderRegistry;
use SkyFish\GeminiChat\Providers\ProviderResponse;

class ProviderAbstractionTest {

	private int $passed = 0;
	private int $failed = 0;
	private array $errors = [];

	public function run_all(): bool {
		echo "====================================================\n";
		echo "Running Node 1: AI Provider Abstraction Test Suite\n";
		echo "====================================================\n\n";

		$this->test_1_provider_interfaces();
		$this->test_2_registry_registration();
		$this->test_3_registry_retrieve_gemini();
		$this->test_4_registry_retrieve_openai();
		$this->test_5_registry_retrieve_claude();
		$this->test_6_unknown_provider_error();
		$this->test_7_duplicate_provider_handling();
		$this->test_8_provider_response_normalization();
		$this->test_9_placeholder_chat_no_network();
		$this->test_10_no_secrets_in_code();
		$this->test_11_plugin_registry_accessor();

		echo "\n----------------------------------------------------\n";
		echo sprintf( "Results: %d Passed, %d Failed\n", $this->passed, $this->failed );
		echo "----------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			echo "Failure details:\n";
			foreach ( $this->errors as $error ) {
				echo " - $error\n";
			}
			return false;
		}

		return true;
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

	private function test_1_provider_interfaces(): void {
		$gemini = new GeminiProvider();
		$openai = new OpenAIProvider();
		$claude = new ClaudeProvider();

		$this->assert( $gemini instanceof ProviderInterface, 'Test 1.1: GeminiProvider implements ProviderInterface' );
		$this->assert( $openai instanceof ProviderInterface, 'Test 1.2: OpenAIProvider implements ProviderInterface' );
		$this->assert( $claude instanceof ProviderInterface, 'Test 1.3: ClaudeProvider implements ProviderInterface' );
	}

	private function test_2_registry_registration(): void {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );
		$registry->register( new OpenAIProvider() );
		$registry->register( new ClaudeProvider() );

		$this->assert( count( $registry->get_all() ) === 3, 'Test 2: ProviderRegistry registers all providers' );
	}

	private function test_3_registry_retrieve_gemini(): void {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );

		$provider = $registry->get( 'gemini' );
		$this->assert( $provider->get_id() === 'gemini', 'Test 3.1: ProviderRegistry retrieves gemini by id' );
		$this->assert( $provider->get_name() === 'Google Gemini', 'Test 3.2: Gemini provider name matches' );
		$this->assert( ! empty( $provider->get_models() ), 'Test 3.3: Gemini provider returns model list' );
	}

	private function test_4_registry_retrieve_openai(): void {
		$registry = new ProviderRegistry();
		$registry->register( new OpenAIProvider() );

		$provider = $registry->get( 'openai' );
		$this->assert( $provider->get_id() === 'openai', 'Test 4.1: ProviderRegistry retrieves openai by id' );
		$this->assert( $provider->get_name() === 'OpenAI', 'Test 4.2: OpenAI provider name matches' );
		$this->assert( ! empty( $provider->get_models() ), 'Test 4.3: OpenAI provider returns model list' );
	}

	private function test_5_registry_retrieve_claude(): void {
		$registry = new ProviderRegistry();
		$registry->register( new ClaudeProvider() );

		$provider = $registry->get( 'claude' );
		$this->assert( $provider->get_id() === 'claude', 'Test 5.1: ProviderRegistry retrieves claude by id' );
		$this->assert( $provider->get_name() === 'Anthropic Claude', 'Test 5.2: Claude provider name matches' );
		$this->assert( ! empty( $provider->get_models() ), 'Test 5.3: Claude provider returns model list' );
	}

	private function test_6_unknown_provider_error(): void {
		$registry = new ProviderRegistry();
		$caught   = false;
		try {
			$registry->get( 'unknown_provider' );
		} catch ( ProviderException $e ) {
			$caught = ( $e->get_error_type() === ProviderException::TYPE_PROVIDER_UNAVAILABLE );
		}
		$this->assert( $caught, 'Test 6: Unknown provider throws controlled ProviderException' );
	}

	private function test_7_duplicate_provider_handling(): void {
		$registry = new ProviderRegistry();
		$registry->register( new GeminiProvider() );
		$caught = false;
		try {
			$registry->register( new GeminiProvider() );
		} catch ( \InvalidArgumentException $e ) {
			$caught = true;
		}
		$this->assert( $caught, 'Test 7: Duplicate provider registration safely rejected' );
	}

	private function test_8_provider_response_normalization(): void {
		$response = new ProviderResponse(
			'Hello world',
			'gemini',
			'gemini-3.8-flash',
			10,
			20,
			30,
			'stop',
			'req_12345',
			[ 'finish_type' => 'stop_sequence' ]
		);

		$this->assert( $response->get_text() === 'Hello world', 'Test 8.1: ProviderResponse text matches' );
		$this->assert( $response->get_provider_id() === 'gemini', 'Test 8.2: ProviderResponse provider matches' );
		$this->assert( $response->get_model_id() === 'gemini-3.8-flash', 'Test 8.3: ProviderResponse model matches' );
		$this->assert( $response->get_total_tokens() === 30, 'Test 8.4: ProviderResponse token count matches' );
		$this->assert( $response->get_finish_reason() === 'stop', 'Test 8.5: ProviderResponse finish reason matches' );

		$array = $response->to_array();
		$this->assert( isset( $array['tokens']['total'] ) && $array['tokens']['total'] === 30, 'Test 8.6: ProviderResponse serializes to array' );
	}

	private function test_9_placeholder_chat_no_network(): void {
		$gemini = new GeminiProvider();
		$openai = new OpenAIProvider();
		$claude = new ClaudeProvider();

		$gemini_caught = false;
		try {
			$gemini->chat( [ [ 'role' => 'user', 'content' => 'hi' ] ] );
		} catch ( ProviderException $e ) {
			$gemini_caught = ( $e->get_error_type() === ProviderException::TYPE_NOT_CONFIGURED );
		}

		$openai_caught = false;
		try {
			$openai->chat( [ [ 'role' => 'user', 'content' => 'hi' ] ] );
		} catch ( ProviderException $e ) {
			$openai_caught = ( $e->get_error_type() === ProviderException::TYPE_NOT_CONFIGURED );
		}

		$claude_caught = false;
		try {
			$claude->chat( [ [ 'role' => 'user', 'content' => 'hi' ] ] );
		} catch ( ProviderException $e ) {
			$claude_caught = ( $e->get_error_type() === ProviderException::TYPE_NOT_CONFIGURED );
		}

		$this->assert( $gemini_caught && $openai_caught && $claude_caught, 'Test 9: Placeholder chat() calls throw controlled not_configured exceptions without making network calls' );
	}

	private function test_10_no_secrets_in_code(): void {
		$files_to_check = [
			__DIR__ . '/../includes/Providers/ProviderInterface.php',
			__DIR__ . '/../includes/Providers/ProviderResponse.php',
			__DIR__ . '/../includes/Providers/ProviderException.php',
			__DIR__ . '/../includes/Providers/ProviderRegistry.php',
			__DIR__ . '/../includes/Providers/GeminiProvider.php',
			__DIR__ . '/../includes/Providers/OpenAIProvider.php',
			__DIR__ . '/../includes/Providers/ClaudeProvider.php',
			__DIR__ . '/../includes/class-plugin.php',
			__DIR__ . '/../gemini-chat-assistant.php',
		];

		$has_secret = false;
		foreach ( $files_to_check as $file ) {
			$content = file_get_contents( $file );
			if ( preg_match( '/(sk-[a-zA-Z0-9]{20,}|AIzaSy[a-zA-Z0-9_-]{33}|sk-ant-[a-zA-Z0-9_-]{20,})/', $content ) ) {
				$has_secret = true;
				break;
			}
		}

		$this->assert( ! $has_secret, 'Test 10: Zero credentials or real API keys exist in source files' );
	}

	private function test_11_plugin_registry_accessor(): void {
		$plugin   = Plugin::get_instance();
		$registry = $plugin->get_provider_registry();

		$this->assert( $registry instanceof ProviderRegistry, 'Test 11.1: Plugin exposes ProviderRegistry' );
		$this->assert( $registry->has( 'gemini' ) && $registry->has( 'openai' ) && $registry->has( 'claude' ), 'Test 11.2: Plugin auto-registers all 3 providers' );
	}
}

// Execute tests if invoked directly.
$suite = new ProviderAbstractionTest();
$suite->run_all();
