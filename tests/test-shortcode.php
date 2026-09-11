<?php
/**
 * Standalone Test Suite for Shortcode (Node N6).
 *
 * @package SkyFish\GeminiChat\Tests
 */



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

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
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

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
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

if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( $prefix = '' ) {
		static $id = 0;
		return $prefix . ( ++$id );
	}
}

// Mock WordPress Shortcode & Script registration.
global $mock_shortcodes, $mock_styles, $mock_scripts, $mock_enqueued;
$mock_shortcodes = [];
$mock_styles     = [];
$mock_scripts    = [];
$mock_enqueued   = [];

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		global $mock_shortcodes;
		$mock_shortcodes[ $tag ] = $callback;
	}
}

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

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $mock_options;
		unset( $mock_options[ $option ] );
		return true;
	}
}

require_once __DIR__ . '/../includes/Admin/SettingsService.php';
require_once __DIR__ . '/../includes/Admin/AppearanceService.php';
require_once __DIR__ . '/../includes/class-assets.php';
require_once __DIR__ . '/../includes/class-shortcode.php';

use SkyFish\GeminiChat\Admin\SettingsService;
use SkyFish\GeminiChat\Assets;
use SkyFish\GeminiChat\Shortcode;

class ShortcodeTest {

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
		echo "Running Node N6 Shortcode Tests\n";
		echo "========================================\n\n";

		$this->test_shortcode_registration();
		$this->test_shortcode_render_enabled();
		$this->test_shortcode_render_disabled();
		$this->test_embedded_mode_markup();
		$this->test_secret_leak_check();

		echo "\n========================================\n";
		echo "Results: {$this->passed} Passed, {$this->failed} Failed\n";
		echo "========================================\n";

		return 0 === $this->failed;
	}

	private function test_shortcode_registration(): void {
		global $mock_shortcodes;
		$shortcode = new Shortcode();
		$shortcode->init();

		$this->assert( isset( $mock_shortcodes['gemini_chat'] ), 'Shortcode [gemini_chat] is registered' );
	}

	private function test_shortcode_render_enabled(): void {
		global $mock_options, $mock_enqueued;
		$mock_options['gca_settings'] = [
			'enabled'               => true,
			'embedded_chat_enabled' => true,
			'assistant_name'        => 'Custom Assistant',
		];

		$shortcode = new Shortcode();
		$html      = $shortcode->render();

		$this->assert( ! empty( $html ), 'Shortcode returns non-empty HTML when enabled' );
		$this->assert( false !== strpos( $html, 'gca-widget--embedded' ), 'Markup contains gca-widget--embedded class' );
		$this->assert( false !== strpos( $html, 'Custom Assistant' ), 'Assistant name safely rendered in header' );
		$this->assert( ! empty( $mock_enqueued['gca-public-chat-css'] ), 'CSS enqueued on render' );
		$this->assert( ! empty( $mock_enqueued['gca-public-chat-js'] ), 'JS enqueued on render' );
	}

	private function test_shortcode_render_disabled(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'               => true,
			'embedded_chat_enabled' => false,
		];

		$shortcode = new Shortcode();
		$html      = $shortcode->render();

		$this->assert( '' === $html, 'Shortcode returns empty string when embedded_chat_enabled is false' );
	}

	private function test_embedded_mode_markup(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'               => true,
			'embedded_chat_enabled' => true,
			'greeting'              => 'Howdy Partner!',
		];

		$shortcode = new Shortcode();
		$html      = $shortcode->render();

		$this->assert( false === strpos( $html, 'gca-launcher' ), 'Embedded mode omits floating launcher button' );
		$this->assert( false !== strpos( $html, 'Howdy Partner!' ), 'Greeting headline rendered' );
		$this->assert( false !== strpos( $html, 'gca-start-card' ), 'Start conversation card rendered' );
		$this->assert( false !== strpos( $html, 'gca-messages' ), 'Messages viewport rendered' );
		$this->assert( false !== strpos( $html, 'gca-composer' ), 'Composer form rendered' );
	}

	private function test_secret_leak_check(): void {
		global $mock_options;
		$mock_options['gca_settings'] = [
			'enabled'               => true,
			'embedded_chat_enabled' => true,
		];

		$shortcode = new Shortcode();
		$html      = $shortcode->render();

		$this->assert( false === strpos( $html, 'GEMINI_API_KEY' ), 'Markup contains zero GEMINI_API_KEY tokens' );
		$this->assert( false === strpos( $html, 'x-goog-api-key' ), 'Markup contains zero x-goog-api-key headers' );
		$this->assert( false === strpos( $html, 'generativelanguage.googleapis.com' ), 'Markup contains zero direct Google endpoints' );
	}
}

if ( 'cli' === php_sapi_name() || ! defined( 'WPINC' ) ) {
	$suite = new ShortcodeTest();
	$suite->run_all();
}
