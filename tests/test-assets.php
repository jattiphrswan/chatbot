<?php
/**
 * Standalone Test Suite for Assets Loader (Node N6).
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

if ( ! defined( 'GCA_PLUGIN_URL' ) ) {
	define( 'GCA_PLUGIN_URL', 'https://example.com/wp-content/plugins/gemini-chat-assistant/' );
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

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( $path = '' ) {
		return 'https://example.com/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		return array_merge( $defaults, is_array( $args ) ? $args : [] );
	}
}

global $mock_styles, $mock_scripts, $mock_enqueued, $mock_localized;
$mock_styles    = [];
$mock_scripts   = [];
$mock_enqueued  = [];
$mock_localized = [];

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = [], $ver = false, $media = 'all' ) {
		global $mock_styles;
		$mock_styles[ $handle ] = $src;
	}
}

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = [], $ver = false, $args = [] ) {
		global $mock_scripts;
		$mock_scripts[ $handle ] = $src;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {
		global $mock_enqueued;
		$mock_enqueued[ $handle ] = true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {
		global $mock_enqueued;
		$mock_enqueued[ $handle ] = true;
	}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $name, $data ) {
		global $mock_localized;
		$mock_localized[ $handle ] = [
			'name' => $name,
			'data' => $data,
		];
		return true;
	}
}

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

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/class-assets.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Assets;

class AssetsTest {

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
		echo "Running Node N6 Assets Tests\n";
		echo "========================================\n\n";

		$this->test_asset_registration();
		$this->test_localized_config_structure();
		$this->test_localized_config_zero_secrets();
		$this->test_maybe_enqueue_floating_widget();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_asset_registration(): void {
		global $mock_styles, $mock_scripts, $mock_localized;
		$assets = new Assets();
		$assets->register_assets();

		$this->assert( isset( $mock_styles['gca-public-chat-css'] ), 'CSS handle registered' );
		$this->assert( isset( $mock_scripts['gca-public-chat-js'] ), 'JS handle registered' );
		$this->assert( isset( $mock_localized['gca-public-chat-js'] ), 'gcaConfig localized on JS handle' );
	}

	private function test_localized_config_structure(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'assistant_name'     => 'Support Bot',
			'max_message_length' => 500,
			'widget_enabled'     => true,
		];

		$config = Assets::get_localized_config();

		$this->assert( 'https://example.com/wp-json/gca/v1' === $config['restUrl'], 'restUrl points to gca/v1 namespace' );
		$this->assert( 'Support Bot' === $config['assistantName'], 'assistantName is passed' );
		$this->assert( 500 === $config['maxMessageLength'], 'maxMessageLength is passed' );
		$this->assert( isset( $config['i18n']['startConversation'] ), 'i18n strings dictionary populated' );
	}

	private function test_localized_config_zero_secrets(): void {
		$config = Assets::get_localized_config();
		$json   = json_encode( $config );

		$this->assert( ! isset( $config['api_key'] ), 'Zero api_key in config' );
		$this->assert( ! isset( $config['session_hash'] ), 'Zero session_hash in config' );
		$this->assert( false === strpos( $json, 'GEMINI_API_KEY' ), 'Zero GEMINI_API_KEY in serialized config' );
		$this->assert( false === strpos( $json, 'generativelanguage.googleapis.com' ), 'Zero direct Google endpoints in config' );
	}

	private function test_maybe_enqueue_floating_widget(): void {
		global $mock_options, $mock_enqueued;
		$mock_enqueued = [];

		$mock_options['gca_settings'] = [
			'enabled'        => true,
			'widget_enabled' => true,
		];

		$assets = new Assets();
		$assets->maybe_enqueue_floating_widget_assets();

		$this->assert( ! empty( $mock_enqueued['gca-public-chat-css'] ), 'CSS enqueued for floating widget' );
		$this->assert( ! empty( $mock_enqueued['gca-public-chat-js'] ), 'JS enqueued for floating widget' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new AssetsTest();
	$suite->run_all();
}
